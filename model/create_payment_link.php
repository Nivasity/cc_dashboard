<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/../config/fw.php');
require_once(__DIR__ . '/functions.php');

$status = 'failed';
$message = '';
$data = [];

$admin_id = $_SESSION['nivas_adminId'] ?? null;
if (!$admin_id) {
  $message = 'Not authenticated.';
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message]);
  exit;
}

$batch_id = intval($_POST['batch_id'] ?? 0);
$tx_ref = trim($_POST['tx_ref'] ?? '');
$amount = intval($_POST['amount'] ?? 0);

if ($batch_id <= 0 || $tx_ref === '' || $amount <= 0) {
  $message = 'Invalid request parameters.';
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message]);
  exit;
}

$stmt = $conn->prepare('SELECT id, tx_ref, total_amount, status, hoc_id, school_id FROM manual_payment_batches WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $batch_id);
$stmt->execute();
$res = $stmt->get_result();
$batch = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$batch) {
  $message = 'Batch not found.';
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message]);
  exit;
}

if ($batch['status'] !== 'pending') {
  $message = 'Batch is not pending.';
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message]);
  exit;
}

// Build webhook URL for this server
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$webhook_url = $scheme . '://' . $host . '/model/flw_webhook_batch.php';

// Prepare payload for Flutterwave Create Payment Link
// Try to get HOC/admin contact details to prefill customer info
$hoc_email = '';
$hoc_name = '';
$hoc_phone = '';
if (!empty($batch['hoc_id'])) {
  $hid = (int)$batch['hoc_id'];
  $ares = mysqli_query($conn, "SELECT first_name, last_name, email, phone FROM users WHERE id = $hid LIMIT 1");
  if ($ares) {
    $arow = mysqli_fetch_assoc($ares);
    if ($arow) {
      $hoc_email = $arow['email'] ?? '';
      $hoc_name = trim(($arow['first_name'] ?? '') . ' ' . ($arow['last_name'] ?? ''));
      $hoc_phone = $arow['phone'] ?? '';
    }
  }
}

// Prepare payload for Flutterwave standard payments endpoint (v3/payments)
// Use batch total as authoritative amount and add 3% charge
$base_amount = (int)$batch['total_amount'];
$fee = (int)ceil($base_amount * 0.03);
$final_amount = $base_amount + $fee;

$payload = [
  'tx_ref' => $tx_ref,
  'amount' => (string)$final_amount,
  'currency' => 'NGN',
  'redirect_url' => $scheme . '://' . $host . '/',
  'customer' => [
    'email' => $hoc_email,
    'name' => $hoc_name,
    'phonenumber' => $hoc_phone
  ],
  'customizations' => [
    'title' => 'Bulk Payment for Manual',
    'description' => 'Payment for batch ' . $tx_ref . ' (' . number_format($final_amount) . ' NGN) - includes 3% fee',
    'logo' => ''
  ],
  'meta' => [
    'batch_id' => $batch_id,
    'amount_before_fee' => $base_amount,
    'fee' => $fee
  ],
  'webhook_url' => $webhook_url
];

// If the HOC's school is 1, try to assign a subaccount. Prefer settlement_accounts.school_id, then settlement_accounts.admin_id, else fallback to default.
if (!empty($batch['school_id']) && (int)$batch['school_id'] === 1) {
  $sub_id = null;
  $bid = (int)$batch['school_id'];
  // 1) Check settlement_accounts for school_id
  $sa_res = mysqli_query($conn, "SELECT subaccount_code FROM settlement_accounts WHERE school_id = $bid LIMIT 1");
  if ($sa_res && ($sa_row = mysqli_fetch_assoc($sa_res))) {
    $sub_id = $sa_row['subaccount_code'] ?? null;
  }

  // 2) If not found, check settlement_accounts for hoc/admin id
  if (!$sub_id && !empty($batch['hoc_id'])) {
    $hid = (int)$batch['hoc_id'];
    $sa_res2 = mysqli_query($conn, "SELECT subaccount_code FROM settlement_accounts WHERE user_id = $hid LIMIT 1");
    if ($sa_res2 && ($sa_row2 = mysqli_fetch_assoc($sa_res2))) {
      $sub_id = $sa_row2['subaccount_code'] ?? null;
    }
  }

  // 3) fallback to provided hard-coded id if still not found
  if (!$sub_id) {
    $sub_id = 'RS_5E799E345A0720AEB353B331709081E6';
  }

  // Subaccount gets ratio=1 and transaction_charge equals the base amount
  $payload['subaccounts'] = [
    [
      'id' => $sub_id,
      'transaction_split_ratio' => 1,
      'transaction_charge_type' => 'flat_subaccount',
      'transaction_charge' => (string)$base_amount
    ]
  ];
  // include assigned subaccount info in meta for webhook handling
  $payload['meta']['assigned_subaccount'] = $sub_id;
  $payload['meta']['assigned_subaccount_charge'] = $base_amount;
}

$secret = defined('FLW_SECRET_KEY') ? FLW_SECRET_KEY : (defined('FLW_SECRET_KEY_TEST') ? FLW_SECRET_KEY_TEST : '');
if (!$secret) {
  $message = 'Payment secret not configured.';
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message]);
  exit;
}

$ch = curl_init();
// Use standard payments endpoint to generate hosted payment link
curl_setopt($ch, CURLOPT_URL, 'https://api.flutterwave.com/v3/payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Authorization: Bearer ' . $secret
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

// Execute request and capture info for debugging
$resp = curl_exec($ch);
$info = curl_getinfo($ch);
if ($resp === false) {
  $message = 'Payment provider request failed: ' . curl_error($ch);
  curl_close($ch);
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message, 'curl_error' => curl_error($ch)]);
  exit;
}

curl_close($ch);

$json = json_decode($resp, true);
if (!is_array($json)) {
  // Return richer debug info to help pinpoint the problem
  $message = 'Invalid response from payment provider.';
  header('Content-Type: application/json');
  echo json_encode([
    'status' => $status,
    'message' => $message,
    'http_code' => $info['http_code'] ?? null,
    'content_type' => $info['content_type'] ?? null,
    'raw' => $resp
  ]);
  exit;
}

// Try to get the link from response
$link = $json['data']['link'] ?? $json['data']['url'] ?? $json['data']['checkout_url'] ?? null;
if (!$link) {
  $message = 'Failed to create payment link: ' . ($json['message'] ?? 'unknown');
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message, 'response' => $json]);
  exit;
}

// Log audit
log_audit_event($conn, $admin_id, 'create', 'manual_payment_link', $batch_id, ['tx_ref' => $tx_ref, 'link' => $link, 'amount' => $final_amount, 'fee' => $fee]);

$status = 'success';
$message = 'Payment link created.';
$data = ['link' => $link];

header('Content-Type: application/json');
echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
exit;
?>
