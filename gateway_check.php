<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once __DIR__ . '/config/fw.php';

// Defaults
$today = new DateTime('now', new DateTimeZone('Africa/Lagos'));
$default_to = $today->format('Y-m-d');
$default_from = (clone $today)->modify('-7 days')->format('Y-m-d');

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : $default_from;
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : $default_to;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$mode = isset($_GET['status']) && in_array($_GET['status'], ['successful', 'refunded']) ? $_GET['status'] : 'successful';
$customer_email = isset($_GET['customer_email']) ? trim((string)$_GET['customer_email']) : '';
$tx_ref_filter = isset($_GET['tx_ref']) ? trim((string)$_GET['tx_ref']) : '';
$gateway = isset($_GET['gateway']) && in_array(strtolower($_GET['gateway']), ['flutterwave', 'paystack']) ? strtolower($_GET['gateway']) : 'flutterwave';

function flw_api_request(string $endpoint, array $query = []): array
{
  $url = $endpoint;
  if (!empty($query)) {
    $url .= '?' . http_build_query($query);
  }

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'Authorization: Bearer ' . FLW_SECRET_KEY,
    ],
  ]);
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err) {
    return ['status' => 'error', 'message' => 'cURL error: ' . $err, 'code' => $code, 'data' => []];
  }

  $json = json_decode($resp, true);
  if (!is_array($json)) {
    return ['status' => 'error', 'message' => 'Invalid JSON from Flutterwave', 'code' => $code, 'data' => []];
  }
  // Normalize
  $json['code'] = $code;
  if (!isset($json['data'])) {
    $json['data'] = [];
  }
  return $json;
}

function get_local_tx_status(mysqli $conn, string $refId): array
{
  $status = null;
  $stmt = $conn->prepare('SELECT status, medium, amount, created_at FROM transactions WHERE ref_id = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('s', $refId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
      $status = $row;
    }
    $stmt->close();
  }
  return $status ? ['found' => true, 'row' => $status] : ['found' => false, 'row' => null];
}

function fetch_transactions(string $from, string $to, int $page, string $customer_email = '', string $tx_ref = ''): array
{
  $params = [
    'from' => $from,
    'to' => $to,
    'status' => 'successful',
    'page' => $page,
  ];
  if ($customer_email !== '') { $params['customer_email'] = $customer_email; }
  if ($tx_ref !== '') { $params['tx_ref'] = $tx_ref; }
  return flw_api_request('https://api.flutterwave.com/v3/transactions', $params);
}

function fetch_refunds(string $from, string $to, int $page, string $customer_email = '', string $tx_ref = ''): array
{
  // Flutterwave refunds listing does not strictly require status param; we fetch and handle in-app
  $params = [
    'from' => $from,
    'to' => $to,
    'page' => $page,
  ];
  // Pass optional filters only when provided
  if ($customer_email !== '') { $params['customer_email'] = $customer_email; }
  if ($tx_ref !== '') { $params['tx_ref'] = $tx_ref; }
  return flw_api_request('https://api.flutterwave.com/v3/refunds', $params);
}

function fetch_tx_ref_from_tx_id(int $txId): ?string
{
  $resp = flw_api_request('https://api.flutterwave.com/v3/transactions/' . $txId . '/verify');
  if (($resp['status'] ?? '') === 'success' && isset($resp['data']['tx_ref'])) {
    return (string)$resp['data']['tx_ref'];
  }
  // Some responses may nest data differently
  if (isset($resp['data']['data']['tx_ref'])) {
    return (string)$resp['data']['data']['tx_ref'];
  }
  return null;
}

function paystack_api_request(string $endpoint, array $query = []): array
{
  $url = $endpoint;
  if (!empty($query)) {
    $url .= '?' . http_build_query($query);
  }

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
    ],
  ]);
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err) {
    return ['status' => false, 'message' => 'cURL error: ' . $err, 'code' => $code, 'data' => []];
  }

  $json = json_decode($resp, true);
  if (!is_array($json)) {
    return ['status' => false, 'message' => 'Invalid JSON from Paystack', 'code' => $code, 'data' => []];
  }
  // Normalize
  $json['code'] = $code;
  if (!isset($json['data'])) {
    $json['data'] = [];
  }
  return $json;
}

function fetch_paystack_transactions(string $from, string $to, int $page, string $customer_email = '', string $tx_ref = ''): array
{
  $params = [
    'from' => $from . 'T00:00:00.000Z',
    'to' => $to . 'T23:59:59.999Z',
    'status' => 'success',
    'page' => $page,
    'perPage' => 50,
  ];
  if ($customer_email !== '') { $params['customer'] = $customer_email; }
  // Paystack doesn't have tx_ref filter in list endpoint, we'll filter locally
  return paystack_api_request('https://api.paystack.co/transaction', $params);
}

function verify_bulk_payment_ref(string $refId): array
{
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.nivasity.com/payment/verify-bulk.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['ref_id' => $refId]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'Content-Type: application/x-www-form-urlencoded',
    ],
  ]);
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err) {
    return ['ok' => false, 'message' => 'cURL error: ' . $err, 'http_code' => $code, 'raw' => null, 'json' => null];
  }

  $json = json_decode((string)$resp, true);
  $apiOk = $code >= 200 && $code < 300;
  if (is_array($json)) {
    if (isset($json['status'])) {
      $statusText = strtolower((string)$json['status']);
      if (in_array($statusText, ['false', 'error', 'failed', 'fail', '0'], true)) {
        $apiOk = false;
      } elseif (in_array($statusText, ['true', 'success', 'ok', '1'], true)) {
        $apiOk = true;
      }
    }
    if (isset($json['success']) && $json['success'] === false) {
      $apiOk = false;
    }

    return [
      'ok' => $apiOk,
      'message' => (string)($json['message'] ?? ''),
      'http_code' => $code,
      'raw' => $resp,
      'json' => $json,
    ];
  }

  return [
    'ok' => $code >= 200 && $code < 300,
    'message' => $code >= 200 && $code < 300 ? 'Verification request sent.' : 'Verification request failed.',
    'http_code' => $code,
    'raw' => $resp,
    'json' => null,
  ];
}

// AJAX verify handler (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1' && ($_POST['verify_local'] ?? '') === '1') {
  header('Content-Type: application/json');
  $ref_id = trim((string)($_POST['ref_id'] ?? ''));
  if ($ref_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing ref_id']);
    exit;
  }

  $verify = verify_bulk_payment_ref($ref_id);
  $local = get_local_tx_status($conn, $ref_id);
  $ok = $verify['ok'] || $local['found'];

  echo json_encode([
    'status' => $ok ? 'success' : 'error',
    'message' => $verify['message'] !== '' ? $verify['message'] : ($ok ? 'Verification completed.' : 'Verification failed.'),
    'ref_id' => $ref_id,
    'local_found' => $local['found'],
    'local_status' => $local['row']['status'] ?? '',
    'verify' => [
      'ok' => $verify['ok'],
      'http_code' => $verify['http_code'],
      'json' => $verify['json'],
      'raw' => $verify['raw'],
    ],
  ]);
  exit;
}

// Execute query to gateway based on mode and gateway selection
$api_response = ['status' => null, 'message' => '', 'data' => [], 'meta' => []];
if ($gateway === 'paystack') {
  // Paystack doesn't have refunds endpoint in the same way, we'll use transactions only
  if ($mode === 'refunded') {
    $api_response = ['status' => false, 'message' => 'Paystack refund listing not supported via API. Please check transactions with refund status.', 'data' => []];
  } else {
    $api_response = fetch_paystack_transactions($from, $to, $page, $customer_email, $tx_ref_filter);
  }
} else {
  // Flutterwave
  if ($mode === 'refunded') {
    $api_response = fetch_refunds($from, $to, $page, $customer_email, $tx_ref_filter);
  } else {
    $api_response = fetch_transactions($from, $to, $page, $customer_email, $tx_ref_filter);
  }
}

// Prepare rows and pagination meta
$rows = [];
$page_size = 10; // as specified
$total = 0;
$total_pages = 1;
$response_status = $gateway === 'paystack' ? ($api_response['status'] ?? false) : (($api_response['status'] ?? '') === 'success');

if ($response_status) {
  $items = $api_response['data'];
  
  // Extract total based on gateway
  if ($gateway === 'paystack') {
    if (isset($api_response['meta']['total'])) {
      $total = (int)$api_response['meta']['total'];
    } elseif (isset($api_response['meta']['pageCount'])) {
      $total = (int)$api_response['meta']['pageCount'] * $page_size;
    } else {
      $total = count($items);
    }
  } else {
    // Flutterwave
    if (isset($api_response['meta']['page_info']['total'])) {
      $total = (int)$api_response['meta']['page_info']['total'];
    } elseif (isset($api_response['meta']['total'])) {
      $total = (int)$api_response['meta']['total'];
    } else {
      // Fallback when meta not provided
      $total = count($items);
    }
  }
  $total_pages = max(1, (int)ceil($total / $page_size));

  foreach ($items as $item) {
    if ($gateway === 'paystack') {
      // Paystack transaction structure
      $tx_ref = (string)($item['reference'] ?? '');
      // Filter by tx_ref if provided (Paystack doesn't support this in API)
      if ($tx_ref_filter !== '' && strpos($tx_ref, $tx_ref_filter) === false) {
        continue;
      }
      
      $paystack_id = $item['id'] ?? '';
      $amount = isset($item['amount']) ? $item['amount'] / 100 : null; // Paystack returns in kobo
      $currency = $item['currency'] ?? 'NGN';
      $status_remote = $item['status'] ?? '';
      // Normalize Paystack status: "success" -> "successful" to match Flutterwave
      if ($status_remote === 'success') {
        $status_remote = 'successful';
      }
      $created = $item['created_at'] ?? '';

      $local = $tx_ref ? get_local_tx_status($conn, $tx_ref) : ['found' => false, 'row' => null];

      $rows[] = [
        'source' => 'transaction',
        'tx_ref' => $tx_ref,
        'flw_ref' => $paystack_id,
        'amount' => $amount,
        'currency' => $currency,
        'status_remote' => $status_remote,
        'created_at' => $created,
        'local_found' => $local['found'],
        'local_status' => $local['row']['status'] ?? '',
      ];
    } elseif ($mode === 'refunded') {
      // Refunds payload may vary; attempt to resolve tx_ref from common locations
      $tx_ref = null;
      if (isset($item['tx_ref']) && $item['tx_ref'] !== '') {
        $tx_ref = (string)$item['tx_ref'];
      } elseif (isset($item['reference']) && $item['reference'] !== '') {
        $tx_ref = (string)$item['reference'];
      } elseif (isset($item['meta']['tx_ref'])) {
        $tx_ref = (string)$item['meta']['tx_ref'];
      } elseif (isset($item['meta']['reference'])) {
        $tx_ref = (string)$item['meta']['reference'];
      } elseif (isset($item['transaction_id']) && is_numeric($item['transaction_id'])) {
        $tx_ref = fetch_tx_ref_from_tx_id((int)$item['transaction_id']);
      }

      $amount = $item['amount_refunded'] ?? ($item['amount'] ?? null);
      $currency = $item['currency'] ?? '';
      $flw_ref = $item['flw_ref'] ?? '';
      $status_remote = $item['status'] ?? 'refunded';
      $created = $item['created_at'] ?? ($item['refund_date'] ?? '');

      $local = $tx_ref ? get_local_tx_status($conn, $tx_ref) : ['found' => false, 'row' => null];

      $rows[] = [
        'source' => 'refund',
        'tx_ref' => $tx_ref ?? '',
        'flw_ref' => $flw_ref,
        'amount' => $amount,
        'currency' => $currency,
        'status_remote' => $status_remote,
        'created_at' => $created,
        'local_found' => $local['found'],
        'local_status' => $local['row']['status'] ?? '',
      ];
    } else {
      // Transactions list
      $tx_ref = (string)($item['tx_ref'] ?? ($item['reference'] ?? ''));
      $flw_tx_id = $item['id'] ?? '';
      $amount = $item['amount'] ?? ($item['charged_amount'] ?? null);
      $currency = $item['currency'] ?? '';
      $status_remote = $item['status'] ?? '';
      $created = $item['created_at'] ?? '';

      $local = $tx_ref ? get_local_tx_status($conn, $tx_ref) : ['found' => false, 'row' => null];

      $rows[] = [
        'source' => 'transaction',
        'tx_ref' => $tx_ref,
        'flw_ref' => $flw_tx_id,
        'amount' => $amount,
        'currency' => $currency,
        'status_remote' => $status_remote,
        'created_at' => $created,
        'local_found' => $local['found'],
        'local_status' => $local['row']['status'] ?? '',
      ];
    }
  }
}

// AJAX handlers
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  header('Content-Type: application/json');

  // Detail request: fetch user details by tx_ref
  if (isset($_GET['detail']) && $_GET['detail'] == '1') {
    $tx_ref = trim($_GET['tx_ref'] ?? '');
    $user = null;
    $user_id = null;
    // Transaction reference pattern: nivas_{user_id}_{timestamp}_{random}
    if (preg_match('/^nivas_(\d+)_/i', $tx_ref, $m)) {
      $user_id = (int)$m[1];
      $stmt = $conn->prepare('SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender, u.status, u.matric_no, u.school, u.dept, s.name AS school_name, d.name AS dept_name, f.name AS faculty_name FROM users u LEFT JOIN schools s ON s.id = u.school LEFT JOIN depts d ON d.id = u.dept LEFT JOIN faculties f ON f.id = d.faculty_id WHERE u.id = ? LIMIT 1');
      if ($stmt) {
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
          $res = $stmt->get_result();
          if ($res) { $user = $res->fetch_assoc(); }
        }
        $stmt->close();
      }
    }
    echo json_encode([
      'status' => 'success',
      'tx_ref' => $tx_ref,
      'user' => $user,
    ]);
    exit;
  }

  echo json_encode([
    'status' => $response_status ? 'success' : 'error',
    'message' => ($api_response['message'] ?? ''),
    'mode' => $mode,
    'page' => $page,
    'page_size' => $page_size,
    'total' => $total,
    'total_pages' => $total_pages,
    'rows' => $rows,
  ]);
  exit;
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Gateway Checker | Nivasity Command Center</title>
    <meta name="description" content="Check Flutterwave & Paystack transactions/refunds against local records" />
    <?php include('partials/_head.php') ?>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  </head>
  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <?php include('partials/_sidebar.php') ?>
        <div class="layout-page">
          <?php include('partials/_navbar.php') ?>
          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Payments /</span> Gateway Checker</h4>
              <div class="card mb-4">
                <div class="card-body">
                  <form method="get" id="fetchForm" class="row g-3 mb-4">
                    <div class="col-md-3">
                      <label class="form-label">Gateway</label>
                      <select name="gateway" class="form-select">
                        <option value="flutterwave" <?php echo $gateway==='flutterwave'?'selected':''; ?>>Flutterwave</option>
                        <option value="paystack" <?php echo $gateway==='paystack'?'selected':''; ?>>Paystack</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">From</label>
                      <input type="date" name="from" class="form-control" value="<?php echo h($from); ?>" required />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">To</label>
                      <input type="date" name="to" class="form-control" value="<?php echo h($to); ?>" required />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Customer Email</label>
                      <input type="email" name="customer_email" class="form-control" value="<?php echo h($customer_email); ?>" placeholder="e.g. user@example.com" />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">TX Ref</label>
                      <input type="text" name="tx_ref" class="form-control" value="<?php echo h($tx_ref_filter); ?>" placeholder="e.g. nivas_123_..." />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Status</label>
                      <select name="status" class="form-select">
                        <option value="successful" <?php echo $mode==='successful'?'selected':''; ?>>Successful (Transactions)</option>
                        <option value="refunded" <?php echo $mode==='refunded'?'selected':''; ?>>Refunded (Refunds)</option>
                      </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                      <button type="submit" class="btn btn-secondary w-100">Fetch</button>
                    </div>
                  </form>

                  <?php if (!$response_status) { ?>
                    <div class="alert alert-warning">
                      <strong>Notice:</strong> Unable to fetch from <?php echo ucfirst($gateway); ?>. <?php echo h($api_response['message'] ?? ''); ?>
                    </div>
                  <?php } ?>

                  <div class="table-responsive text-nowrap">
                    <table class="table table-striped" id="resultsTable">
                      <thead class="table-secondary">
                        <tr>
                          <th>Type</th>
                          <th>TX Ref</th>
                          <th>Gateway Ref/ID</th>
                          <th>Amount</th>
                          <th>Currency</th>
                          <th>Remote Status</th>
                          <th>Created</th>
                          <th>Local Match</th>
                          <th>Local Status</th>
                        </tr>
                      </thead>
                      <tbody id="resultsTableBody">
                        <tr><td colspan="9" class="text-center">Use the filters above and click Fetch</td></tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="d-flex align-items-center justify-content-between mt-3">
                    <div id="pageInfo" class="text-muted">Page 1 of 1</div>
                    <nav>
                      <ul class="pagination mb-0" id="pagination"></ul>
                    </nav>
                  </div>

                  <p class="text-muted mt-2 mb-0">
                    Tip: When Status is "Refunded", results come from the gateway's refunds endpoint and are matched against local transactions by <code>tx_ref</code>. Paystack does not support refund listing via API.
                  </p>
                </div>
              </div>
            </div>
            <!-- Details Modal -->
            <div class="modal fade" id="txDetailModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <h6 class="mb-2">Remote Info</h6>
                        <div><strong>Type:</strong> <span id="mType"></span></div>
                        <div><strong>TX Ref:</strong> <span id="mTxRef"></span></div>
                        <div><strong>Gateway Ref/ID:</strong> <span id="mFlwRef"></span></div>
                        <div><strong>Amount:</strong> <span id="mAmount"></span> <span id="mCurrency"></span></div>
                        <div><strong>Status:</strong> <span id="mStatus" class="badge bg-label-secondary"></span></div>
                        <div><strong>Created:</strong> <span id="mCreated"></span></div>
                      </div>
                      <div class="col-md-6">
                        <h6 class="mb-2">User</h6>
                        <div><strong>Name:</strong> <span id="mUserName">-</span></div>
                        <div><strong>Email:</strong> <span id="mUserEmail">-</span></div>
                        <div><strong>Phone:</strong> <span id="mUserPhone">-</span></div>
                        <div><strong>School:</strong> <span id="mUserSchool">-</span></div>
                        <div><strong>Faculty:</strong> <span id="mUserFaculty">-</span></div>
                        <div><strong>Department:</strong> <span id="mUserDept">-</span></div>
                        <div><strong>Status:</strong> <span id="mUserStatus">-</span></div>
                        <div><strong>Matric No:</strong> <span id="mUserMatric">-</span></div>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
            <!-- /Details Modal -->
            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
    </div>
    <script>
      (function() {
        const $form = $('#fetchForm');
        const $tbody = $('#resultsTableBody');
        const $pagination = $('#pagination');
        const $pageInfo = $('#pageInfo');
        let currentRows = [];
        let activePage = 1;

        function badgeClass(source, status) {
          if (status === 'successful') return 'bg-label-success';
          if (source === 'refund') return 'bg-label-warning';
          return 'bg-label-secondary';
        }

        function renderRows(rows) {
          if (!rows || rows.length === 0) {
            $tbody.html('<tr><td colspan="9" class="text-center">No records</td></tr>');
            return;
          }
          const html = rows.map(function(r, idx) {
            const localBadge = r.local_found ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-danger">No</span>';
            const remoteBadge = '<span class="badge '+badgeClass(r.source, r.status_remote)+'">'+ (r.status_remote || '') +'</span>';
            const showVerifyButton = !r.local_found && !!(r.tx_ref || '');
            const localStatusHtml = (r.local_status || '') !== ''
              ? (r.local_status || '')
              : (showVerifyButton
                ? '<button type="button" class="btn btn-sm btn-outline-primary js-verify-local" data-ref_id="'+ (r.tx_ref || '') +'">Verify</button>'
                : '-');
            const trData = [
              'data-tx_ref="'+ (r.tx_ref||'') +'"',
              'data-source="'+ (r.source||'') +'"',
              'data-flw_ref="'+ (r.flw_ref||'') +'"',
              'data-amount="'+ (r.amount||'') +'"',
              'data-currency="'+ (r.currency||'') +'"',
              'data-status_remote="'+ (r.status_remote||'') +'"',
              'data-created_at="'+ (r.created_at||'') +'"'
            ].join(' ');
            return '<tr class="tx-row" style="cursor:pointer;" '+trData+'>\
                      <td>'+ (r.source||'') +'</td>\
                      <td>'+ (r.tx_ref||'') +'</td>\
                      <td>'+ (r.flw_ref||'') +'</td>\
                      <td>'+ (r.amount||'') +'</td>\
                      <td>'+ (r.currency||'') +'</td>\
                      <td>'+ remoteBadge +'</td>\
                      <td>'+ (r.created_at||'') +'</td>\
                      <td>'+ localBadge +'</td>\
                      <td>'+ localStatusHtml +'</td>\
                    </tr>';
          }).join('');
          $tbody.html(html);
        }

        function renderPagination(totalPages, currentPage) {
          currentPage = parseInt(currentPage || 1, 10);
          totalPages = parseInt(totalPages || 1, 10);
          activePage = currentPage;
          $pageInfo.text('Page ' + currentPage + ' of ' + totalPages);

          function pageItem(label, page, disabled, active) {
            return '<li class="page-item '+ (disabled?'disabled':'') +' '+ (active?'active':'') +'">\
                      <a class="page-link" href="#" data-page="'+ page +'">'+ label +'</a>\
                    </li>';
          }

          const items = [];
          items.push(pageItem('Prev', Math.max(1, currentPage-1), currentPage<=1, false));
          // Show a window of pages around current
          const windowSize = 3;
          const start = Math.max(1, currentPage - windowSize);
          const end = Math.min(totalPages, currentPage + windowSize);
          for (let p = start; p <= end; p++) {
            items.push(pageItem(p, p, false, p === currentPage));
          }
          items.push(pageItem('Next', Math.min(totalPages, currentPage+1), currentPage>=totalPages, false));

          $pagination.html(items.join(''));
        }

        function fetchPage(pageOverride) {
          const params = Object.fromEntries(new FormData($form[0]).entries());
          params.ajax = '1';
          if (pageOverride) { params.page = pageOverride; }
          $.get('gateway_check.php', params).done(function(res){
            if (res && res.status === 'success') {
              currentRows = res.rows || [];
              renderRows(currentRows);
              renderPagination(res.total_pages || 1, res.page || 1);
            } else {
              $tbody.html('<tr><td colspan="9" class="text-center">Failed to fetch from payment gateway</td></tr>');
              renderPagination(1, 1);
            }
          }).fail(function(){
            $tbody.html('<tr><td colspan="9" class="text-center">Network error</td></tr>');
            renderPagination(1, 1);
          });
        }

        $form.on('submit', function(e){ e.preventDefault(); fetchPage(1); });

        $pagination.on('click', 'a.page-link', function(e){
          e.preventDefault();
          const page = parseInt($(this).data('page'), 10);
          if (!isNaN(page)) { fetchPage(page); }
        });

        // Verify unmatched row using backend proxy to verify-bulk API
        $(document).on('click', '.js-verify-local', function(e){
          e.preventDefault();
          e.stopPropagation();
          const $btn = $(this);
          const refId = String($btn.data('ref_id') || '').trim();
          if (!refId) { return; }

          const oldText = $btn.text();
          $btn.prop('disabled', true).text('Verifying...');

          $.post('gateway_check.php', { ajax: '1', verify_local: '1', ref_id: refId }).done(function(res){
            if (res && res.status === 'success') {
              fetchPage(activePage);
              return;
            }
            const msg = (res && res.message) ? res.message : 'Verification failed';
            alert(msg);
            $btn.prop('disabled', false).text(oldText);
          }).fail(function(){
            alert('Network error while verifying payment');
            $btn.prop('disabled', false).text(oldText);
          });
        });

        // Row click -> details modal
        $(document).on('click', 'tr.tx-row', function(){
          const $tr = $(this);
          const tx = {
            type: $tr.data('source') || '',
            tx_ref: $tr.data('tx_ref') || '',
            flw_ref: $tr.data('flw_ref') || '',
            amount: $tr.data('amount') || '',
            currency: $tr.data('currency') || '',
            status: $tr.data('status_remote') || '',
            created: $tr.data('created_at') || ''
          };

          // Fill remote fields immediately
          $('#mType').text(tx.type);
          $('#mTxRef').text(tx.tx_ref);
          $('#mFlwRef').text(tx.flw_ref);
          $('#mAmount').text(tx.amount);
          $('#mCurrency').text(tx.currency);
          $('#mStatus').text(tx.status).removeClass('bg-label-success bg-label-warning bg-label-secondary').addClass(badgeClass(tx.type, tx.status));
          $('#mCreated').text(tx.created);

          // Clear user section first
          $('#mUserName,#mUserEmail,#mUserPhone,#mUserSchool,#mUserFaculty,#mUserDept,#mUserStatus,#mUserMatric').text('-');

          // Fetch user via tx_ref (nivas_{user_id}_...)
          $.get('gateway_check.php', { ajax: '1', detail: '1', tx_ref: tx.tx_ref }).done(function(res){
            if (res && res.status === 'success' && res.user) {
              const u = res.user;
              $('#mUserName').text((u.first_name||'') + ' ' + (u.last_name||''));
              $('#mUserEmail').text(u.email||'-');
              $('#mUserPhone').text(u.phone||'-');
              $('#mUserSchool').text(u.school_name||'-');
              $('#mUserFaculty').text(u.faculty_name||'-');
              $('#mUserDept').text(u.dept_name||'-');
              $('#mUserStatus').text(u.status||'-');
              $('#mUserMatric').text(u.matric_no||'-');
            }
          });

          // Show modal (Bootstrap 5)
          const modalEl = document.getElementById('txDetailModal');
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
        });

        // Optionally auto-fetch on initial load
        // fetchPage(1);
      })();
    </script>
  </body>
</html>
