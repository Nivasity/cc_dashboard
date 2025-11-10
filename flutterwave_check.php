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

// Prepare rows and pagination meta
$rows = [];
$page_size = 10; // as specified
$total = 0;
$total_pages = 1;
if (($api_response['status'] ?? '') === 'success') {
  $items = $api_response['data'];
  // Extract total if available in meta
  if (isset($api_response['meta']['page_info']['total'])) {
    $total = (int)$api_response['meta']['page_info']['total'];
  } elseif (isset($api_response['meta']['total'])) {
    $total = (int)$api_response['meta']['total'];
  } else {
    // Fallback when meta not provided
    $total = count($items);
  }
  $total_pages = max(1, (int)ceil($total / $page_size));

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

// AJAX handlers
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  header('Content-Type: application/json');

  // Detail request: fetch user details by tx_ref
  if (isset($_GET['detail']) && $_GET['detail'] == '1') {
    $tx_ref = trim($_GET['tx_ref'] ?? '');
    $user = null;
    $user_id = null;
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
    'status' => ($api_response['status'] ?? 'error'),
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
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Payments /</span> Flutterwave Checker</h4>
              <div class="card mb-4">
                <div class="card-body">
                  <form method="get" id="fetchForm" class="row g-3 mb-4">
                    <div class="col-md-3">
                      <label class="form-label">From</label>
                      <input type="date" name="from" class="form-control" value="<?php echo h($from); ?>" required />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">To</label>
                      <input type="date" name="to" class="form-control" value="<?php echo h($to); ?>" required />
                    </div>
                    <div class="col-md-4">
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

                  <?php if (($api_response['status'] ?? '') !== 'success') { ?>
                    <div class="alert alert-warning">
                      <strong>Notice:</strong> Unable to fetch from Flutterwave. <?php echo h($api_response['message'] ?? ''); ?>
                    </div>
                  <?php } ?>

                  <div class="table-responsive text-nowrap">
                    <table class="table table-striped" id="resultsTable">
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
                    Tip: When Status is "Refunded", results come from Flutterwave refunds endpoint and are matched against local transactions by <code>tx_ref</code>.
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
                        <div><strong>FLW Ref/ID:</strong> <span id="mFlwRef"></span></div>
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
                      <td>'+ (r.local_status||'') +'</td>\
                    </tr>';
          }).join('');
          $tbody.html(html);
        }

        function renderPagination(totalPages, currentPage) {
          currentPage = parseInt(currentPage || 1, 10);
          totalPages = parseInt(totalPages || 1, 10);
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
          $.get('flutterwave_check.php', params).done(function(res){
            if (res && res.status === 'success') {
              currentRows = res.rows || [];
              renderRows(currentRows);
              renderPagination(res.total_pages || 1, res.page || 1);
            } else {
              $tbody.html('<tr><td colspan="9" class="text-center">Failed to fetch from Flutterwave</td></tr>');
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
          $.get('flutterwave_check.php', { ajax: '1', detail: '1', tx_ref: tx.tx_ref }).done(function(res){
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
