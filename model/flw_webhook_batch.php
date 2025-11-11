<?php
// Flutterwave Webhook for Batch Payments
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/../config/fw.php');
require_once(__DIR__ . '/mail.php');

http_response_code(200); // Default OK to avoid repeated retries

$signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';
// Prefer a dedicated secret hash if defined; otherwise fall back to secret key
$expected = defined('FLW_SECRET_HASH') ? FLW_SECRET_HASH : (defined('FLW_SECRET_KEY') ? FLW_SECRET_KEY : '');
if ($expected !== '' && $signature !== $expected) {
  exit('ignored');
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  exit('invalid');
}

// Normalize expected fields
$data = $payload['data'] ?? $payload;

// Handle case where data is an array of transactions (query endpoint format)
if (is_array($data) && count($data) > 0 && isset($data[0]['tx_ref'])) {
  $data = $data[0];
}

$tx_status = strtolower((string)($data['status'] ?? ($payload['status'] ?? '')));
$tx_ref = (string)($data['tx_ref'] ?? ($data['reference'] ?? ''));
$flw_tx_id = (string)($data['id'] ?? ($data['flw_ref'] ?? ''));
$amount = isset($data['amount']) ? (int)$data['amount'] : (isset($data['charged_amount']) ? (int)$data['charged_amount'] : 0);

if ($tx_ref === '') {
  exit('no-ref');
}

// Process only successful/completed events
if (!in_array($tx_status, ['successful', 'completed'])) {
  exit('ignored');
}

// Look up the batch by tx_ref
$stmt = $conn->prepare('SELECT id, manual_id, hoc_id, school_id, dept_id, total_amount, status FROM manual_payment_batches WHERE tx_ref = ? LIMIT 1');
if (!$stmt) { exit('stmt'); }
$stmt->bind_param('s', $tx_ref);
$stmt->execute();
$res = $stmt->get_result();
$batch = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$batch) {
  exit('no-batch');
}

if ($batch['status'] !== 'pending') {
  exit('done');
}

// Optional: verify amount matches
if ($amount > 0 && (int)$batch['total_amount'] !== (int)$amount) {
  // Amount mismatch; do not process
  exit('amt-mismatch');
}

mysqli_begin_transaction($conn);
try {
  // Mark batch paid
  $up = $conn->prepare('UPDATE manual_payment_batches SET status = "paid", flw_tx_id = ? WHERE id = ?');
  $bid = (int)$batch['id'];
  $up->bind_param('si', $flw_tx_id, $bid);
  if (!$up->execute()) { throw new Exception('batch-update'); }
  $up->close();

  // Fetch manual seller for manuals_bought entries
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
  log_audit_event($conn, (int)$batch['hoc_id'], 'update', 'manual_payment_batch', $bid, [
    'tx_ref' => $tx_ref,
    'flw_tx_id' => $flw_tx_id,
    'status' => 'paid'
  ]);
  // Send success email notification
  try {
    $subject = "Batch payment successful: {$tx_ref}";
    $body = "<p>Batch ID: {$bid}</p><p>tx_ref: {$tx_ref}</p><p>Flutterwave TX ID: {$flw_tx_id}</p><p>Amount: {$amount}</p><p>Status: paid</p>";
    sendMail($subject, $body, 'akinyemisamuel170@gmail.com');
  } catch (Exception $ee) {
    // swallow mail errors
  }
} catch (Exception $e) {
  mysqli_rollback($conn);
  // Send failure email notification with debug info
  try {
    $subject = "Batch payment processing FAILED: {$tx_ref}";
    $body = "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $body .= "<p>Batch ID: " . htmlspecialchars(($batch['id'] ?? '')) . "</p>";
    $body .= "<pre>Payload: " . htmlspecialchars($raw) . "</pre>";
    sendMail($subject, $body, 'akinyemisamuel170@gmail.com');
  } catch (Exception $ee) {
    // ignore
  }
  exit('error');
}

echo 'ok';
?>

