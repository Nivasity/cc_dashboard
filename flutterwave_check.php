<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once __DIR__ . '/config/fw.php';

// Defaults
$today = new DateTime('now', new DateTimeZone('Africa/Lagos'));
$default_to = $today->format('Y-m-d');
$default_from = $today->modify('-7 days')->format('Y-m-d');

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : $default_from;
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : $default_to;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$mode = isset($_GET['status']) && in_array($_GET['status'], ['successful', 'refunded']) ? $_GET['status'] : 'successful';

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

function fetch_transactions(string $from, string $to, int $page): array
{
  $params = [
    'from' => $from,
    'to' => $to,
    'status' => 'successful',
    'page' => $page,
  ];
  return flw_api_request('https://api.flutterwave.com/v3/transactions', $params);
}

function fetch_refunds(string $from, string $to, int $page): array
{
  // Flutterwave refunds listing does not strictly require status param; we fetch and handle in-app
  $params = [
    'from' => $from,
    'to' => $to,
    'page' => $page,
  ];
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

// Execute query to Flutterwave based on mode
$api_response = ['status' => null, 'message' => '', 'data' => [], 'meta' => []];
if ($mode === 'refunded') {
  $api_response = fetch_refunds($from, $to, $page);
} else {
  $api_response = fetch_transactions($from, $to, $page);
}

// Prepare rows
$rows = [];
if (($api_response['status'] ?? '') === 'success') {
  $items = $api_response['data'];
  foreach ($items as $item) {
    if ($mode === 'refunded') {
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

function h($v) { return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Flutterwave Checker | Nivasity Command Center</title>
    <meta name="description" content="Check Flutterwave transactions/refunds against local records" />
    <?php include('partials/_head.php') ?>
  </head>
  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <?php include('partials/_sidebar.php') ?>
        <div class="layout-page">
          <?php include('partials/_navbar.php') ?>
          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Payments /</span> Flutterwave Checker</h4>
              <div class="card mb-4">
                <div class="card-body">
                  <form method="get" class="row g-3 mb-4">
                    <div class="col-md-3">
                      <label class="form-label">From</label>
                      <input type="date" name="from" class="form-control" value="<?php echo h($from); ?>" required />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">To</label>
                      <input type="date" name="to" class="form-control" value="<?php echo h($to); ?>" required />
                    </div>
                    <div class="col-md-2">
                      <label class="form-label">Page</label>
                      <input type="number" name="page" class="form-control" min="1" value="<?php echo (int)$page; ?>" />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Status</label>
                      <select name="status" class="form-select">
                        <option value="successful" <?php echo $mode==='successful'?'selected':''; ?>>Successful (Transactions)</option>
                        <option value="refunded" <?php echo $mode==='refunded'?'selected':''; ?>>Refunded (Refunds)</option>
                      </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                      <button type="submit" class="btn btn-secondary w-100">Fetch</button>
                    </div>
                  </form>

                  <?php if (($api_response['status'] ?? '') !== 'success') { ?>
                    <div class="alert alert-warning">
                      <strong>Notice:</strong> Unable to fetch from Flutterwave. <?php echo h($api_response['message'] ?? ''); ?>
                    </div>
                  <?php } ?>

                  <div class="table-responsive text-nowrap">
                    <table class="table table-striped">
                      <thead class="table-secondary">
                        <tr>
                          <th>Type</th>
                          <th>TX Ref</th>
                          <th>FLW Ref/ID</th>
                          <th>Amount</th>
                          <th>Currency</th>
                          <th>Remote Status</th>
                          <th>Created</th>
                          <th>Local Match</th>
                          <th>Local Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($rows)) { ?>
                          <tr><td colspan="9" class="text-center">No records</td></tr>
                        <?php } else { foreach ($rows as $r) { ?>
                          <tr>
                            <td><?php echo h($r['source']); ?></td>
                            <td><?php echo h($r['tx_ref']); ?></td>
                            <td><?php echo h($r['flw_ref']); ?></td>
                            <td><?php echo h($r['amount']); ?></td>
                            <td><?php echo h($r['currency']); ?></td>
                            <td><span class="badge bg-label-<?php echo $r['status_remote']==='successful'?'success':($r['source']==='refund'?'warning':'secondary'); ?>"><?php echo h($r['status_remote']); ?></span></td>
                            <td><?php echo h($r['created_at']); ?></td>
                            <td><?php echo $r['local_found'] ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-danger">No</span>'; ?></td>
                            <td><?php echo h($r['local_status']); ?></td>
                          </tr>
                        <?php }} ?>
                      </tbody>
                    </table>
                  </div>

                  <p class="text-muted mt-2 mb-0">
                    Tip: When Status is "Refunded", results come from Flutterwave refunds endpoint and are matched against local transactions by <code>tx_ref</code>.
                  </p>
                </div>
              </div>
            </div>
            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>

