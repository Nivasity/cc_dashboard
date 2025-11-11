<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/../config/fw.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/mail.php');

$status = 'failed';
$message = '';
$data = [];

$admin_id = $_SESSION['nivas_adminId'] ?? null;
if (!$admin_id) {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Not authenticated']);
  exit;
}

$batch_id = intval($_POST['batch_id'] ?? 0);
$tx_ref = trim($_POST['tx_ref'] ?? '');

if ($batch_id <= 0 || $tx_ref === '') {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Invalid parameters']);
  exit;
}

// Fetch batch
$stmt = $conn->prepare('SELECT id, manual_id, hoc_id, school_id, dept_id, total_amount, status FROM manual_payment_batches WHERE id = ? AND tx_ref = ? LIMIT 1');
$stmt->bind_param('is', $batch_id, $tx_ref);
$stmt->execute();
$res = $stmt->get_result();
$batch = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$batch) {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Batch not found']);
  exit;
}

if ($batch['status'] !== 'pending') {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Batch is not pending']);
  exit;
}

// Get Flutterwave secret
$secret = defined('FLW_SECRET_KEY') ? FLW_SECRET_KEY : (defined('FLW_SECRET_KEY_TEST') ? FLW_SECRET_KEY_TEST : '');
if (!$secret) {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Payment secret not configured']);
  exit;
}

// Call Flutterwave verify endpoint
// Reference: https://developer.flutterwave.com/reference#verify-transaction
// GET https://api.flutterwave.com/v3/transactions?tx_ref={tx_ref}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.flutterwave.com/v3/transactions?tx_ref=' . urlencode($tx_ref));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Authorization: Bearer ' . $secret
]);

$resp = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

$json = json_decode($resp, true);
if (!is_array($json)) {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Invalid response from payment provider', 'http_code' => $info['http_code'] ?? null, 'raw' => $resp]);
  exit;
}

// Check if API call was successful
if (($json['status'] ?? '') !== 'success') {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Payment provider returned error', 'response' => $json]);
  exit;
}

// Extract the first transaction from the data array
$transactions = $json['data'] ?? [];
if (!is_array($transactions) || count($transactions) === 0) {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'No transaction found for this reference', 'response' => $json]);
  exit;
}

$tx_data = $transactions[0];

// Check if transaction status is successful
if (($tx_data['status'] ?? '') !== 'successful') {
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => 'Payment not successful yet', 'response' => $json]);
  exit;
}

// Payment is successful; replicate webhook processing
$flw_tx_id = (string)($tx_data['id'] ?? '');
$amount = isset($tx_data['amount']) ? (int)$tx_data['amount'] : (isset($tx_data['charged_amount']) ? (int)$tx_data['charged_amount'] : 0);

// Optional: verify amount matches (allowing for 3% fee difference)
if ($amount > 0 && (int)$batch['total_amount'] > 0) {
  // Allow some tolerance for fee
  $tolerance = (int)ceil((int)$batch['total_amount'] * 0.05);
  if (abs($amount - (int)$batch['total_amount']) > $tolerance) {
    // Could be the amount with fee; check if it's base + 3%
    $expected_with_fee = (int)$batch['total_amount'] + (int)ceil((int)$batch['total_amount'] * 0.03);
    if ($amount !== $expected_with_fee) {
      header('Content-Type: application/json');
      echo json_encode(['status' => $status, 'message' => 'Amount mismatch', 'expected' => (int)$batch['total_amount'], 'received' => $amount]);
      exit;
    }
  }
}

// Begin transaction and replicate webhook operations
mysqli_begin_transaction($conn);
try {
  // Mark batch paid
  $up = $conn->prepare('UPDATE manual_payment_batches SET status = "paid", flw_tx_id = ? WHERE id = ?');
  $bid = (int)$batch['id'];
  $up->bind_param('si', $flw_tx_id, $bid);
  if (!$up->execute()) { throw new Exception('batch-update'); }
  $up->close();

  // Fetch manual seller
  $mid = (int)$batch['manual_id'];
  $mres = mysqli_query($conn, "SELECT user_id AS seller FROM manuals WHERE id = $mid");
  $mrow = $mres ? mysqli_fetch_assoc($mres) : null;
  $seller = (int)($mrow['seller'] ?? 0);
  $school_id = (int)$batch['school_id'];

  // Get items
  $items = [];
  $ires = mysqli_query($conn, "SELECT id, manual_id, student_id, price, ref_id FROM manual_payment_batch_items WHERE batch_id = $bid");
  while ($ires && $row = mysqli_fetch_assoc($ires)) { $items[] = $row; }

  // Prepare statements
  $up_item = $conn->prepare('UPDATE manual_payment_batch_items SET status = "paid" WHERE id = ?');
  $up_tx = $conn->prepare('UPDATE transactions SET status = "successful" WHERE ref_id = ?');
  $ins_mb = $conn->prepare('INSERT INTO manuals_bought (manual_id, price, seller, buyer, school_id, ref_id, status) VALUES (?, ?, ?, ?, ?, ?, "successful")');

  foreach ($items as $it) {
    $iid = (int)$it['id'];
    $manual_id = (int)$it['manual_id'];
    $student_id = (int)$it['student_id'];
    $price = (int)$it['price'];
    $ref_id = (string)$it['ref_id'];

    $up_item->bind_param('i', $iid);
    if (!$up_item->execute()) { throw new Exception('item-update'); }

    $up_tx->bind_param('s', $ref_id);
    if (!$up_tx->execute()) { throw new Exception('tx-update'); }

    $ins_mb->bind_param('iiiiis', $manual_id, $price, $seller, $student_id, $school_id, $ref_id);
    if (!$ins_mb->execute()) { throw new Exception('mb-insert'); }
  }

  $up_item->close();
  $up_tx->close();
  $ins_mb->close();

  mysqli_commit($conn);
  log_audit_event($conn, (int)$batch['hoc_id'], 'verify', 'manual_payment_batch', $bid, [
    'tx_ref' => $tx_ref,
    'flw_tx_id' => $flw_tx_id,
    'status' => 'paid',
    'verified_by_admin' => $admin_id
  ]);

  // Send success email notification
  try {
    $subject = "Batch payment verified: {$tx_ref}";
    $body = "<p>Batch ID: {$bid}</p><p>tx_ref: {$tx_ref}</p><p>Flutterwave TX ID: {$flw_tx_id}</p><p>Amount: {$amount}</p><p>Status: paid (verified by admin)</p>";
    sendMail($subject, $body, 'akinyemisamuel170@gmail.com');
  } catch (Exception $ee) {
    // swallow mail errors
  }

  $status = 'success';
  $message = 'Payment verified and processed successfully';
  $data = ['batch_id' => $bid, 'tx_ref' => $tx_ref, 'amount' => $amount, 'status' => 'paid'];
} catch (Exception $e) {
  mysqli_rollback($conn);
  // Send failure email notification
  try {
    $subject = "Batch payment verification FAILED: {$tx_ref}";
    $body = "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $body .= "<p>Batch ID: {$bid}</p>";
    $body .= "<p>Verified by admin: {$admin_id}</p>";
    sendMail($subject, $body, 'akinyemisamuel170@gmail.com');
  } catch (Exception $ee) {
    // ignore
  }
  $message = 'Failed to process payment: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
exit;
?>
