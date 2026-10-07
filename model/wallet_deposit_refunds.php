<?php
// Wallet deposit refunds (Student Wallets page).
//   POST action=deposits  wallet_id            -> the wallet's bank deposits, what is refundable, their refunds
//   POST action=create    funding_id, amount, reason -> takes the amount off the wallet and refunds it via Paystack
//   POST action=check     refund_id            -> asks Paystack for the latest status
// Starting/checking refunds: super admin, admin and finance (roles 1, 2, 4).
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/wallet_deposit_refund_core.php';
if (file_exists(__DIR__ . '/../config/fw.php')) {
  require_once __DIR__ . '/../config/fw.php';
}

header('Content-Type: application/json');

function wdrRespond(int $code, array $payload): void
{
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

$adminId = (int) ($_SESSION['nivas_adminId'] ?? 0);
$adminRole = (int) ($_SESSION['nivas_adminRole'] ?? 0);
if ($adminId <= 0 || !in_array($adminRole, [1, 2, 3, 4, 5], true)) {
  wdrRespond(401, ['success' => false, 'message' => 'Sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  wdrRespond(405, ['success' => false, 'message' => 'Method not allowed']);
}
if (!nvWalletRefundReady($conn)) {
  wdrRespond(200, ['success' => false, 'ready' => false, 'message' => 'Run nivasity/sql/add_wallet_deposit_refunds.sql first.']);
}

$canRefund = in_array($adminRole, [1, 2, 4], true);
$secret = defined('PAYSTACK_SECRET_KEY') ? trim((string) PAYSTACK_SECRET_KEY) : '';
$action = (string) ($_POST['action'] ?? '');

function wdrRefundPublic(array $r): array
{
  return [
    'id' => (int) $r['id'],
    'reference' => $r['reference'],
    'funding_id' => (int) $r['funding_id'],
    'amount' => (int) $r['amount'],
    'reason' => (string) $r['reason'],
    'status' => $r['status'],
    'provider_status' => $r['provider_status'],
    'failure_reason' => $r['failure_reason'],
    'created_by' => (int) ($r['created_by'] ?? 0) === 0 ? 'Student (self-service)' : trim((string) ($r['admin_name'] ?? '')),
    'created_at' => $r['created_at'],
    'completed_at' => $r['completed_at'],
  ];
}

if ($action === 'deposits') {
  $walletId = (int) ($_POST['wallet_id'] ?? 0);
  if ($walletId <= 0) {
    wdrRespond(400, ['success' => false, 'message' => 'Missing wallet']);
  }
  $deposits = [];
  $res = mysqli_query($conn, "SELECT id, provider, provider_reference, amount, status, created_at, posted_at
      FROM wallet_funding_transactions WHERE wallet_id = $walletId ORDER BY created_at DESC, id DESC LIMIT 50");
  while ($res && ($d = mysqli_fetch_assoc($res))) {
    $deposits[(int) $d['id']] = [
      'id' => (int) $d['id'],
      'provider' => $d['provider'],
      'reference' => $d['provider_reference'],
      'amount' => (int) $d['amount'],
      'status' => $d['status'],
      'date' => $d['posted_at'] ?: $d['created_at'],
      'refunded_or_held' => 0,
      'refundable' => 0,
      'refunds' => [],
    ];
  }
  if ($deposits) {
    $ids = implode(',', array_keys($deposits));
    $rr = mysqli_query($conn, "SELECT r.*, TRIM(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))) AS admin_name
        FROM wallet_deposit_refunds r LEFT JOIN admins a ON a.id = r.created_by
        WHERE r.funding_id IN ($ids) ORDER BY r.id DESC");
    while ($rr && ($r = mysqli_fetch_assoc($rr))) {
      $fid = (int) $r['funding_id'];
      $deposits[$fid]['refunds'][] = wdrRefundPublic($r);
      if ($r['status'] !== 'failed') {
        $deposits[$fid]['refunded_or_held'] += (int) $r['amount'];
      }
    }
  }
  $balance = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT balance FROM user_wallets WHERE id = $walletId"))[0] ?? 0);
  foreach ($deposits as &$d) {
    $refundable = ($d['status'] === 'posted' && $d['provider'] === 'paystack') ? max(0, $d['amount'] - $d['refunded_or_held']) : 0;
    $d['refundable'] = min($refundable, $balance);
  }
  unset($d);
  wdrRespond(200, ['success' => true, 'ready' => true, 'can_refund' => $canRefund, 'balance' => $balance, 'deposits' => array_values($deposits)]);
}

if (!$canRefund) {
  wdrRespond(403, ['success' => false, 'message' => 'Only super admins, admins and finance can refund deposits.']);
}

if ($action === 'create') {
  $fundingId = (int) ($_POST['funding_id'] ?? 0);
  $amount = (int) ($_POST['amount'] ?? 0);
  $reason = trim((string) ($_POST['reason'] ?? ''));
  if ($fundingId <= 0 || $amount <= 0) {
    wdrRespond(400, ['success' => false, 'message' => 'Enter an amount greater than zero.']);
  }
  if ($reason === '') {
    wdrRespond(400, ['success' => false, 'message' => 'Enter a reason for the refund.']);
  }
  $reason = substr($reason, 0, 250);

  // 1) Take the amount off the wallet and record the refund (one transaction)
  mysqli_begin_transaction($conn);
  try {
    $funding = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM wallet_funding_transactions WHERE id = $fundingId LIMIT 1 FOR UPDATE"));
    if (!$funding || $funding['status'] !== 'posted' || $funding['provider'] !== 'paystack') {
      throw new RuntimeException('Only completed Paystack bank deposits can be refunded.');
    }
    $walletId = (int) $funding['wallet_id'];
    $userId = (int) $funding['user_id'];
    $wallet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM user_wallets WHERE id = $walletId LIMIT 1 FOR UPDATE"));
    if (!$wallet) {
      throw new RuntimeException('Wallet not found.');
    }
    $already = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) FROM wallet_deposit_refunds WHERE funding_id = $fundingId AND status <> 'failed'"))[0] ?? 0);
    $refundable = (int) $funding['amount'] - $already;
    $balance = (int) $wallet['balance'];
    if ($amount > $refundable) {
      throw new RuntimeException('At most N' . number_format(max(0, $refundable)) . ' of this deposit can still be refunded.');
    }
    if ($amount > $balance) {
      throw new RuntimeException('The wallet only has N' . number_format($balance) . '; the student has spent part of this deposit.');
    }

    $reference = 'WDR-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $after = $balance - $amount;
    $refEsc = mysqli_real_escape_string($conn, $reference);
    $provRef = mysqli_real_escape_string($conn, (string) $funding['provider_reference']);
    $desc = mysqli_real_escape_string($conn, 'Refund to bank of deposit ' . $funding['provider_reference']);
    $meta = mysqli_real_escape_string($conn, json_encode(['funding_id' => $fundingId, 'admin_id' => $adminId, 'reason' => $reason]));
    if (!mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, provider_reference, description, metadata)
        VALUES ($walletId, 'debit', $amount, $balance, $after, 'pending', '$refEsc', '$provRef', '$desc', '$meta')")) {
      throw new RuntimeException('Could not record the wallet debit: ' . mysqli_error($conn));
    }
    $debitLedgerId = (int) mysqli_insert_id($conn);
    mysqli_query($conn, "UPDATE user_wallets SET balance = $after, updated_at = NOW() WHERE id = $walletId");
    $reasonEsc = mysqli_real_escape_string($conn, $reason);
    if (!mysqli_query($conn, "INSERT INTO wallet_deposit_refunds (reference, funding_id, wallet_id, user_id, amount, reason, status, provider_transaction_reference, debit_ledger_id, created_by)
        VALUES ('$refEsc', $fundingId, $walletId, $userId, $amount, '$reasonEsc', 'processing', '$provRef', $debitLedgerId, $adminId)")) {
      throw new RuntimeException('Could not record the refund: ' . mysqli_error($conn));
    }
    $refundId = (int) mysqli_insert_id($conn);
    mysqli_commit($conn);
  } catch (Throwable $e) {
    mysqli_rollback($conn);
    wdrRespond(400, ['success' => false, 'message' => $e->getMessage()]);
  }

  // 2) Ask Paystack to refund the deposit
  $call = nvWalletRefundPaystack('POST', '/refund', [
    'transaction' => (string) $funding['provider_reference'],
    'amount' => $amount * 100,
    'currency' => 'NGN',
    'merchant_note' => 'Nivasity wallet refund ' . $reference . ': ' . $reason,
    'customer_note' => 'Refund of your Nivasity wallet deposit',
  ], $secret);

  if (!$call['ok']) {
    // Paystack refused: put the money back and record why
    nvWalletRefundApplyProviderStatus($conn, $refundId, 'failed', ['message' => $call['error']], 'cc_create');
    log_audit_event($conn, $adminId, 'wallet_refund_failed', 'wallet_deposit_refund', $refundId, ['reference' => $reference, 'error' => $call['error']]);
    wdrRespond(400, ['success' => false, 'message' => 'Paystack did not accept the refund: ' . rtrim($call['error'], '.') . '. The money is back on the wallet.']);
  }

  $data = is_array($call['body']['data'] ?? null) ? $call['body']['data'] : [];
  if (!empty($data['id'])) {
    mysqli_query($conn, "UPDATE wallet_deposit_refunds SET provider_refund_id = '" . mysqli_real_escape_string($conn, (string) $data['id']) . "' WHERE id = $refundId");
  }
  $status = nvWalletRefundApplyProviderStatus($conn, $refundId, (string) ($data['status'] ?? 'pending'), $data, 'cc_create');
  log_audit_event($conn, $adminId, 'wallet_refund_started', 'wallet_deposit_refund', $refundId, [
    'reference' => $reference, 'funding_id' => $fundingId, 'amount' => $amount, 'reason' => $reason, 'status' => $status,
  ]);
  wdrRespond(200, ['success' => true, 'status' => $status,
    'message' => $status === 'refunded'
      ? 'Refund completed by Paystack.'
      : 'Refund sent to Paystack. N' . number_format($amount) . ' is off the wallet; the status updates automatically when Paystack finishes.']);
}

if ($action === 'check') {
  $refundId = (int) ($_POST['refund_id'] ?? 0);
  $refund = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM wallet_deposit_refunds WHERE id = $refundId LIMIT 1"));
  if (!$refund) {
    wdrRespond(404, ['success' => false, 'message' => 'Refund not found']);
  }
  if (empty($refund['provider_refund_id'])) {
    wdrRespond(200, ['success' => true, 'status' => $refund['status'], 'message' => 'No Paystack refund to check.']);
  }
  $call = nvWalletRefundPaystack('GET', '/refund/' . rawurlencode((string) $refund['provider_refund_id']), null, $secret);
  if (!$call['ok']) {
    wdrRespond(502, ['success' => false, 'message' => 'Could not check with Paystack: ' . $call['error']]);
  }
  $data = is_array($call['body']['data'] ?? null) ? $call['body']['data'] : [];
  $status = nvWalletRefundApplyProviderStatus($conn, $refundId, (string) ($data['status'] ?? ''), $data, 'cc_check');
  wdrRespond(200, ['success' => true, 'status' => $status, 'message' => 'Paystack says: ' . ($data['status'] ?? 'unknown')]);
}

wdrRespond(400, ['success' => false, 'message' => 'Unknown action']);
