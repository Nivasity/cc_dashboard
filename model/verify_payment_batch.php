<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/../config/fw.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/mail.php');

$admin_id = $_SESSION['nivas_adminId'] ?? null;
if (!$admin_id) {
  respondJson('failed', 'Not authenticated');
}

$batch_id = intval($_POST['batch_id'] ?? 0);
$tx_ref = trim($_POST['tx_ref'] ?? '');

if ($batch_id <= 0 || $tx_ref === '') {
  respondJson('failed', 'Invalid parameters');
}

$stmt = $conn->prepare('SELECT id, manual_id, hoc_id, school_id, dept_id, total_amount, status, gateway FROM manual_payment_batches WHERE id = ? AND tx_ref = ? LIMIT 1');
$stmt->bind_param('is', $batch_id, $tx_ref);
$stmt->execute();
$res = $stmt->get_result();
$batch = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$batch) {
  respondJson('failed', 'Batch not found');
}

if (($batch['status'] ?? '') !== 'pending') {
  respondJson('failed', 'Batch is not pending');
}

$gateway = normalizeGateway((string)($batch['gateway'] ?? 'PAYSTACK'));

if ($gateway === 'PAYSTACK') {
  $verification = verifyPaystackPayment($tx_ref, (int)$batch['total_amount']);
} else {
  $verification = verifyFlutterwavePayment($tx_ref, (int)$batch['total_amount']);
}

try {
  $result = finalizeBatchPayment(
    $conn,
    $batch,
    $tx_ref,
    $verification['gateway_tx_id'],
    $verification['amount'],
    $gateway,
    (int)$admin_id
  );
  respondJson('success', 'Payment verified and processed successfully', $result);
} catch (Exception $e) {
  $bid = (int)($batch['id'] ?? 0);
  try {
    $subject = "Batch payment verification FAILED: {$tx_ref}";
    $body = '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    $body .= '<p>Batch ID: ' . $bid . '</p>';
    $body .= '<p>Gateway: ' . htmlspecialchars($gateway) . '</p>';
    $body .= '<p>Verified by admin: ' . (int)$admin_id . '</p>';
    sendMail($subject, $body, 'akinyemisamuel170@gmail.com');
  } catch (Exception $mailException) {
    // ignore mail errors
  }

  respondJson('failed', 'Failed to process payment: ' . $e->getMessage());
}

function verifyPaystackPayment(string $tx_ref, int $totalAmount): array
{
  $secret = defined('PAYSTACK_SECRET_KEY') ? trim((string)PAYSTACK_SECRET_KEY) : '';
  if ($secret === '') {
    respondJson('failed', 'Paystack secret is not configured');
  }

  $json = executeGetJsonRequest(
    'https://api.paystack.co/transaction/verify/' . rawurlencode($tx_ref),
    ['Authorization: Bearer ' . $secret]
  );

  if (($json['status'] ?? false) !== true) {
    respondJson('failed', 'Payment provider returned error', [], ['response' => $json]);
  }

  $tx_data = $json['data'] ?? null;
  if (!is_array($tx_data)) {
    respondJson('failed', 'No transaction found for this reference', [], ['response' => $json]);
  }

  if (($tx_data['status'] ?? '') !== 'success') {
    respondJson('failed', 'Payment not successful yet', [], ['response' => $json]);
  }

  $gateway_tx_id = (string)($tx_data['id'] ?? $tx_data['reference'] ?? $tx_ref);
  $amount_kobo = (int)($tx_data['amount'] ?? 0);
  $expected_kobo = ($totalAmount + calculateBatchProcessingFee($totalAmount)) * 100;
  if ($amount_kobo !== $expected_kobo) {
    respondJson('failed', 'Amount mismatch', [], [
      'expected' => $expected_kobo / 100,
      'received' => $amount_kobo / 100,
      'gateway' => 'PAYSTACK',
    ]);
  }

  return [
    'gateway_tx_id' => $gateway_tx_id,
    'amount' => (int)round($amount_kobo / 100),
  ];
}

function verifyFlutterwavePayment(string $tx_ref, int $totalAmount): array
{
  $secret = defined('FLW_SECRET_KEY') ? trim((string)FLW_SECRET_KEY) : (defined('FLW_SECRET_KEY_TEST') ? trim((string)FLW_SECRET_KEY_TEST) : '');
  if ($secret === '') {
    respondJson('failed', 'Flutterwave secret is not configured');
  }

  $json = executeGetJsonRequest(
    'https://api.flutterwave.com/v3/transactions?tx_ref=' . urlencode($tx_ref),
    ['Authorization: Bearer ' . $secret]
  );

  if (($json['status'] ?? '') !== 'success') {
    respondJson('failed', 'Payment provider returned error', [], ['response' => $json]);
  }

  $transactions = $json['data'] ?? [];
  if (!is_array($transactions) || count($transactions) === 0) {
    respondJson('failed', 'No transaction found for this reference', [], ['response' => $json]);
  }

  $tx_data = $transactions[0];
  if (($tx_data['status'] ?? '') !== 'successful') {
    respondJson('failed', 'Payment not successful yet', [], ['response' => $json]);
  }

  $gateway_tx_id = (string)($tx_data['id'] ?? $tx_ref);
  $amount = isset($tx_data['amount']) ? (int)$tx_data['amount'] : (isset($tx_data['charged_amount']) ? (int)$tx_data['charged_amount'] : 0);
  if (!isAllowedLegacyFlutterwaveAmount($totalAmount, $amount)) {
    respondJson('failed', 'Amount mismatch', [], [
      'expected' => $totalAmount + calculateBatchProcessingFee($totalAmount),
      'received' => $amount,
      'gateway' => 'FLUTTERWAVE',
    ]);
  }

  return [
    'gateway_tx_id' => $gateway_tx_id,
    'amount' => $amount,
  ];
}

function finalizeBatchPayment(mysqli $conn, array $batch, string $tx_ref, string $gatewayTxId, int $amount, string $gateway, int $admin_id): array
{
  mysqli_begin_transaction($conn);
  try {
    $bid = (int)$batch['id'];
    $up = $conn->prepare('UPDATE manual_payment_batches SET status = "paid", flw_tx_id = ? WHERE id = ?');
    $up->bind_param('si', $gatewayTxId, $bid);
    if (!$up->execute()) {
      throw new Exception('batch-update');
    }
    $up->close();

    $mid = (int)$batch['manual_id'];
    $mres = mysqli_query($conn, "SELECT user_id AS seller FROM manuals WHERE id = $mid");
    $mrow = $mres ? mysqli_fetch_assoc($mres) : null;
    $seller = (int)($mrow['seller'] ?? 0);
    $school_id = (int)$batch['school_id'];

    $items = [];
    $ires = mysqli_query($conn, "SELECT id, manual_id, student_id, student_matric, price, ref_id FROM manual_payment_batch_items WHERE batch_id = $bid");
    while ($ires && $row = mysqli_fetch_assoc($ires)) {
      $items[] = $row;
    }

    $up_item = $conn->prepare('UPDATE manual_payment_batch_items SET status = "paid" WHERE id = ?');
    $up_tx = $conn->prepare('UPDATE transactions SET status = "successful" WHERE ref_id = ?');
    $ins_mb = $conn->prepare('INSERT INTO manuals_bought (manual_id, price, seller, buyer, school_id, ref_id, status) VALUES (?, ?, ?, ?, ?, ?, "successful")');
    $unmatched_count = 0;

    foreach ($items as $it) {
      $iid = (int)$it['id'];
      $manual_id = (int)$it['manual_id'];
      $student_id = (int)$it['student_id'];
      $price = (int)$it['price'];
      $ref_id = (string)$it['ref_id'];

      $up_item->bind_param('i', $iid);
      if (!$up_item->execute()) {
        throw new Exception('item-update');
      }

      $up_tx->bind_param('s', $ref_id);
      if (!$up_tx->execute()) {
        throw new Exception('tx-update');
      }

      if ($student_id <= 0) {
        $unmatched_count++;
        continue;
      }

      $ins_mb->bind_param('iiiiis', $manual_id, $price, $seller, $student_id, $school_id, $ref_id);
      if (!$ins_mb->execute()) {
        throw new Exception('mb-insert');
      }
    }

    $up_item->close();
    $up_tx->close();
    $ins_mb->close();

    mysqli_commit($conn);
    log_audit_event($conn, (int)$batch['hoc_id'], 'verify', 'manual_payment_batch', $bid, [
      'tx_ref' => $tx_ref,
      'gateway' => $gateway,
      'gateway_tx_id' => $gatewayTxId,
      'status' => 'paid',
      'unmatched_count' => $unmatched_count,
      'verified_by_admin' => $admin_id,
    ]);

    try {
      $subject = "Batch payment verified: {$tx_ref}";
      $body = '<p>Batch ID: ' . $bid . '</p>';
      $body .= '<p>tx_ref: ' . htmlspecialchars($tx_ref) . '</p>';
      $body .= '<p>Gateway: ' . htmlspecialchars($gateway) . '</p>';
      $body .= '<p>Gateway TX ID: ' . htmlspecialchars($gatewayTxId) . '</p>';
      $body .= '<p>Amount: ' . $amount . '</p>';
      if ($unmatched_count > 0) {
        $body .= '<p>Unmatched matric-only items: ' . $unmatched_count . '</p>';
      }
      $body .= '<p>Status: paid (verified by admin)</p>';
      sendMail($subject, $body, 'akinyemisamuel170@gmail.com');
    } catch (Exception $mailException) {
      // ignore mail errors
    }

    return [
      'batch_id' => $bid,
      'tx_ref' => $tx_ref,
      'amount' => $amount,
      'gateway' => $gateway,
      'unmatched_count' => $unmatched_count,
      'status' => 'paid',
    ];
  } catch (Exception $e) {
    mysqli_rollback($conn);
    throw $e;
  }
}

function calculateBatchProcessingFee(int $baseAmount): int
{
  if ($baseAmount <= 0) {
    return 0;
  }

  return (int)ceil($baseAmount * 0.02);
}

function isAllowedLegacyFlutterwaveAmount(int $baseAmount, int $amount): bool
{
  if ($amount <= 0) {
    return false;
  }

  $acceptable = [
    $baseAmount,
    $baseAmount + calculateBatchProcessingFee($baseAmount),
    $baseAmount + (int)ceil($baseAmount * 0.03),
  ];

  return in_array($amount, array_values(array_unique($acceptable)), true);
}

function normalizeGateway(string $gateway): string
{
  $normalized = strtoupper(trim($gateway));
  if (in_array($normalized, ['PAYSTACK', 'FLUTTERWAVE'], true)) {
    return $normalized;
  }

  return 'PAYSTACK';
}

function executeGetJsonRequest(string $url, array $headers): array
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  $info = curl_getinfo($ch);
  $error = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    respondJson('failed', 'Payment provider request failed: ' . $error);
  }

  $decoded = json_decode((string)$response, true);
  if (!is_array($decoded)) {
    respondJson('failed', 'Invalid response from payment provider', [], [
      'http_code' => $info['http_code'] ?? null,
      'raw' => $response,
    ]);
  }

  return $decoded;
}

function respondJson(string $status, string $message, array $data = [], array $extra = []): void
{
  header('Content-Type: application/json');
  echo json_encode(array_merge([
    'status' => $status,
    'message' => $message,
    'data' => $data,
  ], $extra));
  exit;
}
?>
