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
  respondJson('failed', 'Not authenticated.');
}

$batch_id = intval($_POST['batch_id'] ?? 0);
$tx_ref = trim($_POST['tx_ref'] ?? '');
$amount = intval($_POST['amount'] ?? 0);

if ($batch_id <= 0 || $tx_ref === '' || $amount <= 0) {
  respondJson('failed', 'Invalid request parameters.');
}

$stmt = $conn->prepare('SELECT id, tx_ref, total_amount, status, hoc_id, school_id, gateway, paystack_subaccount_code FROM manual_payment_batches WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $batch_id);
$stmt->execute();
$res = $stmt->get_result();
$batch = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$batch) {
  respondJson('failed', 'Batch not found.');
}

if (($batch['status'] ?? '') !== 'pending') {
  respondJson('failed', 'Batch is not pending.');
}

if ((string)($batch['tx_ref'] ?? '') !== $tx_ref) {
  respondJson('failed', 'Batch reference mismatch.');
}

$gateway = normalizeGateway((string)($batch['gateway'] ?? 'PAYSTACK'));
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$redirectUrl = $scheme . '://' . $host . '/';
$webhookUrl = $scheme . '://' . $host . '/model/flw_webhook_batch.php';

$hoc_email = '';
$hoc_name = '';
$hoc_phone = '';
if (!empty($batch['hoc_id'])) {
  $hid = (int)$batch['hoc_id'];
  $ares = mysqli_query($conn, "SELECT first_name, last_name, email, phone FROM users WHERE id = $hid LIMIT 1");
  if ($ares) {
    $arow = mysqli_fetch_assoc($ares);
    if ($arow) {
      $hoc_email = trim((string)($arow['email'] ?? ''));
      $hoc_name = trim(($arow['first_name'] ?? '') . ' ' . ($arow['last_name'] ?? ''));
      $hoc_phone = trim((string)($arow['phone'] ?? ''));
    }
  }
}

$base_amount = (int)$batch['total_amount'];
$fee = calculateBatchProcessingFee($base_amount);
$final_amount = $base_amount + $fee;

if ($gateway === 'PAYSTACK') {
  if ($hoc_email === '') {
    respondJson('failed', 'The HOC must have a valid email before initializing a Paystack payment.');
  }

  $paystack_subaccount_code = strtoupper(trim((string)($batch['paystack_subaccount_code'] ?? '')));
  if ($paystack_subaccount_code === '') {
    respondJson('failed', 'This batch does not have a school Paystack subaccount code. Create a new batch with the school subaccount code.');
  }

  $secret = defined('PAYSTACK_SECRET_KEY') ? trim((string)PAYSTACK_SECRET_KEY) : '';
  if ($secret === '') {
    respondJson('failed', 'Paystack secret is not configured.');
  }

  $payload = [
    'email' => $hoc_email,
    'amount' => $final_amount * 100,
    'reference' => $tx_ref,
    'currency' => 'NGN',
    'callback_url' => $redirectUrl,
    'metadata' => [
      'batch_id' => $batch_id,
      'gateway' => $gateway,
      'amount_before_fee' => $base_amount,
      'fee' => $fee,
      'settlement_subtotal' => $base_amount,
      'customer_name' => $hoc_name,
      'customer_phone' => $hoc_phone,
      'assigned_subaccount' => $paystack_subaccount_code,
    ],
    'subaccount' => $paystack_subaccount_code,
    'transaction_charge' => $fee * 100,
    'bearer' => 'account',
  ];

  $json = executeGatewayJsonRequest(
    'https://api.paystack.co/transaction/initialize',
    [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $secret,
    ],
    $payload
  );

  if (($json['status'] ?? false) !== true) {
    respondJson('failed', 'Failed to create Paystack payment link: ' . (string)($json['message'] ?? 'unknown'), [], ['response' => $json]);
  }

  $link = $json['data']['authorization_url'] ?? null;
  if (!$link) {
    respondJson('failed', 'Paystack did not return an authorization URL.', [], ['response' => $json]);
  }
} else {
  $secret = defined('FLW_SECRET_KEY') ? trim((string)FLW_SECRET_KEY) : (defined('FLW_SECRET_KEY_TEST') ? trim((string)FLW_SECRET_KEY_TEST) : '');
  if ($secret === '') {
    respondJson('failed', 'Flutterwave secret is not configured.');
  }

  $payload = [
    'tx_ref' => $tx_ref,
    'amount' => (string)$final_amount,
    'currency' => 'NGN',
    'redirect_url' => $redirectUrl,
    'customer' => [
      'email' => $hoc_email,
      'name' => $hoc_name,
      'phonenumber' => $hoc_phone,
    ],
    'customizations' => [
      'title' => 'Bulk Payment for Manual',
      'description' => 'Payment for batch ' . $tx_ref . ' (' . number_format($final_amount) . ' NGN) - includes 2% fee',
      'logo' => '',
    ],
    'meta' => [
      'batch_id' => $batch_id,
      'gateway' => $gateway,
      'amount_before_fee' => $base_amount,
      'fee' => $fee,
    ],
    'webhook_url' => $webhookUrl,
  ];

  if (!empty($batch['school_id']) && (int)$batch['school_id'] === 1) {
    $sub_id = null;
    $school_id = (int)$batch['school_id'];
    $sa_res = mysqli_query($conn, "SELECT subaccount_code FROM settlement_accounts WHERE school_id = $school_id LIMIT 1");
    if ($sa_res && ($sa_row = mysqli_fetch_assoc($sa_res))) {
      $sub_id = $sa_row['subaccount_code'] ?? null;
    }

    if (!$sub_id && !empty($batch['hoc_id'])) {
      $hid = (int)$batch['hoc_id'];
      $sa_res2 = mysqli_query($conn, "SELECT subaccount_code FROM settlement_accounts WHERE user_id = $hid LIMIT 1");
      if ($sa_res2 && ($sa_row2 = mysqli_fetch_assoc($sa_res2))) {
        $sub_id = $sa_row2['subaccount_code'] ?? null;
      }
    }

    if (!$sub_id) {
      $sub_id = 'RS_5E799E345A0720AEB353B331709081E6';
    }

    $payload['subaccounts'] = [
      [
        'id' => $sub_id,
        'transaction_split_ratio' => 1,
        'transaction_charge_type' => 'flat_subaccount',
        'transaction_charge' => (string)$base_amount,
      ],
    ];
    $payload['meta']['assigned_subaccount'] = $sub_id;
    $payload['meta']['assigned_subaccount_charge'] = $base_amount;
  }

  $json = executeGatewayJsonRequest(
    'https://api.flutterwave.com/v3/payments',
    [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $secret,
    ],
    $payload
  );

  if (($json['status'] ?? '') !== 'success') {
    respondJson('failed', 'Failed to create Flutterwave payment link: ' . (string)($json['message'] ?? 'unknown'), [], ['response' => $json]);
  }

  $link = $json['data']['link'] ?? $json['data']['url'] ?? $json['data']['checkout_url'] ?? null;
  if (!$link) {
    respondJson('failed', 'Flutterwave did not return a payment link.', [], ['response' => $json]);
  }
}

log_audit_event($conn, $admin_id, 'create', 'manual_payment_link', $batch_id, [
  'tx_ref' => $tx_ref,
  'gateway' => $gateway,
  'link' => $link,
  'amount' => $final_amount,
  'fee' => $fee,
  'settlement_subtotal' => $base_amount,
  'assigned_subaccount' => strtoupper(trim((string)($batch['paystack_subaccount_code'] ?? ''))),
]);

respondJson('success', 'Payment link created.', [
  'link' => $link,
  'gateway' => $gateway,
  'amount' => $final_amount,
  'fee' => $fee,
]);

function calculateBatchProcessingFee(int $baseAmount): int
{
  if ($baseAmount <= 0) {
    return 0;
  }

  return (int)ceil($baseAmount * 0.02);
}

function normalizeGateway(string $gateway): string
{
  $normalized = strtoupper(trim($gateway));
  if (in_array($normalized, ['PAYSTACK', 'FLUTTERWAVE'], true)) {
    return $normalized;
  }

  return 'PAYSTACK';
}

function executeGatewayJsonRequest(string $url, array $headers, array $payload): array
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

  $response = curl_exec($ch);
  $error = curl_error($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);

  if ($response === false) {
    respondJson('failed', 'Payment provider request failed: ' . $error, [], ['curl_error' => $error]);
  }

  $decoded = json_decode((string)$response, true);
  if (!is_array($decoded)) {
    respondJson('failed', 'Invalid response from payment provider.', [], [
      'http_code' => $info['http_code'] ?? null,
      'content_type' => $info['content_type'] ?? null,
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
