<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once(__DIR__ . '/model/wallet_transactions_data.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRoles = [1, 2, 3, 4];

if (!$finance_mgt_menu || !in_array($admin_role, $allowedRoles, true)) {
  header('Location: /');
  exit();
}

$filters = ccWalletTransactionsGetFilters($_GET);
$directionFilter = $filters['direction'];
$dateRange = $filters['date_range'];
$startDate = $filters['start_date'];
$endDate = $filters['end_date'];

$walletData = ccWalletTransactionsFetchData($conn, $filters);
$walletTablesReady = (bool) ($walletData['wallet_tables_ready'] ?? false);
$missingTables = $walletData['missing_tables'] ?? [];
$summary = $walletData['summary'] ?? ['entries_count' => 0, 'credit_total' => 0, 'debit_total' => 0];
$transactions = $walletData['transactions'] ?? [];
$tableNotice = (string) ($walletData['table_notice'] ?? '');
$hasTransactionRows = (bool) ($walletData['has_transaction_rows'] ?? false);
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Wallet Transactions | Nivasity Command Center</title>
    <meta name="description" content="Review wallet ledger entries with credit or debit direction filters and transaction date ranges." />
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
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                  <h4 class="fw-bold py-3 mb-1"><span class="text-muted fw-light">Finances /</span> Wallet Transactions</h4>
                  <p class="mb-0 text-muted">Monitor wallet credits and debits across student wallets.</p>
                </div>
              </div>

              <?php if (!$walletTablesReady) { ?>
              <div class="alert alert-warning" role="alert">
                Wallet transaction tables are not available in this environment yet. Missing table(s): <strong><?php echo htmlspecialchars(implode(', ', $missingTables)); ?></strong>.
              </div>
              <?php } ?>

              <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Filtered Entries</span>
                      <h3 class="card-title mb-1" id="summaryEntriesCount"><?php echo number_format((int) $summary['entries_count']); ?></h3>
                      <small class="text-muted">Total wallet ledger rows matching the current filters.</small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Credits</span>
                      <h3 class="card-title mb-1 text-success" id="summaryCreditTotal"><?php echo ccWalletTransactionsFormatAmount($summary['credit_total']); ?></h3>
                      <small class="text-muted">Credit and refund entries in the selected range.</small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-12 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Debits</span>
                      <h3 class="card-title mb-1 text-danger" id="summaryDebitTotal"><?php echo ccWalletTransactionsFormatAmount($summary['debit_total']); ?></h3>
                      <small class="text-muted">Debit and fee entries in the selected range.</small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-body">
                  <div id="walletTransactionsAlert" class="alert d-none" role="alert"></div>
                  <form method="get" id="walletTransactionsFilters" class="row g-3 align-items-end">
                    <div class="col-xl-3 col-lg-4 col-md-6">
                      <label for="direction" class="form-label">Direction</label>
                      <select name="direction" id="direction" class="form-select" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        <option value="all" <?php echo $directionFilter === 'all' ? 'selected' : ''; ?>>All Entries</option>
                        <option value="credit" <?php echo $directionFilter === 'credit' ? 'selected' : ''; ?>>Credits</option>
                        <option value="debit" <?php echo $directionFilter === 'debit' ? 'selected' : ''; ?>>Debits</option>
                      </select>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                      <label for="dateRange" class="form-label">Date Range</label>
                      <select name="date_range" id="dateRange" class="form-select" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                      </select>
                    </div>
                    <div class="col-xl-6 col-lg-12 <?php echo $dateRange === 'custom' ? '' : 'd-none'; ?>" id="customDateRange">
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label for="startDate" class="form-label">Start Date</label>
                          <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                          <label for="endDate" class="form-label">End Date</label>
                          <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                      <small class="text-muted">Filters auto-apply on desktop as soon as you change them.</small>
                      <div class="d-flex flex-wrap gap-2">
                      <button type="submit" id="walletTransactionsApplyBtn" class="btn btn-primary d-lg-none" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>Apply Filters</button>
                      <button type="button" id="walletTransactionsResetBtn" class="btn btn-outline-secondary" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>Reset</button>
                      </div>
                    </div>
                  </form>
                  <?php if ($tableNotice !== '') { ?>
                  <div class="pt-3 small text-muted" id="walletTransactionsTableNotice"><?php echo htmlspecialchars($tableNotice); ?></div>
                  <?php } else { ?>
                  <div class="pt-3 small text-muted d-none" id="walletTransactionsTableNotice"></div>
                  <?php } ?>
                  <div class="pt-4">
                  <div class="table-responsive text-nowrap">
                    <table id="walletTransactionsTable" class="table table-striped align-middle w-100">
                      <thead class="table-secondary">
                        <tr>
                          <th>Date &amp; Time</th>
                          <th>Student</th>
                          <th>Direction</th>
                          <th>Amount</th>
                          <th>Balance After</th>
                          <th>Reference</th>
                        </tr>
                      </thead>
                      <tbody id="walletTransactionsTableBody">
                        <?php if (!$walletTablesReady) { ?>
                        <tr>
                          <td colspan="6" class="text-center text-muted py-4">Wallet ledger tables are unavailable in this database.</td>
                        </tr>
                        <?php } elseif ($transactions === []) { ?>
                        <tr>
                          <td colspan="6" class="text-center text-muted py-4">No wallet transactions matched the current filters.</td>
                        </tr>
                        <?php } else { ?>
                          <?php foreach ($transactions as $transaction) { ?>
                          <tr>
                            <td data-order="<?php echo htmlspecialchars($transaction['created_at']); ?>"><?php echo htmlspecialchars($transaction['created_at_display']); ?></td>
                            <td>
                              <div class="fw-semibold"><?php echo htmlspecialchars($transaction['student_name'] !== '' ? $transaction['student_name'] : 'Unknown User'); ?></div>
                              <small class="text-muted d-block"><?php echo htmlspecialchars($transaction['matric_no'] !== '' ? $transaction['matric_no'] : $transaction['email']); ?></small>
                              <small class="text-muted d-block">
                                <?php
                                  $locationParts = array_filter([
                                    $transaction['school_code'],
                                    $transaction['faculty_name'],
                                    $transaction['dept_name'],
                                  ]);
                                  echo htmlspecialchars($locationParts !== [] ? implode(' / ', $locationParts) : 'No academic scope');
                                ?>
                              </small>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['direction_label']); ?></td>
                            <td data-order="<?php echo htmlspecialchars((string) $transaction['amount']); ?>" class="fw-semibold <?php echo $transaction['direction'] === 'credit' ? 'text-success' : ($transaction['direction'] === 'debit' ? 'text-danger' : 'text-body'); ?>">
                              <?php echo htmlspecialchars($transaction['amount_sign']); ?><?php echo ccWalletTransactionsFormatAmount($transaction['amount']); ?>
                            </td>
                            <td data-order="<?php echo htmlspecialchars((string) $transaction['balance_after']); ?>"><?php echo ccWalletTransactionsFormatAmount($transaction['balance_after']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['reference_display']); ?></td>
                          </tr>
                          <?php } ?>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                  </div>
                </div>
              </div>
            </div>

            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      $(function() {
        var endpointUrl = 'model/wallet_transactions_list.php';
        var walletTablesReady = <?php echo $walletTablesReady ? 'true' : 'false'; ?>;
        var hasTransactionRows = <?php echo $hasTransactionRows ? 'true' : 'false'; ?>;
        var walletTransactionsTable = null;
        var pendingRequest = null;
        var requestSequence = 0;
        var $form = $('#walletTransactionsFilters');
        var $alert = $('#walletTransactionsAlert');
        var $summaryEntries = $('#summaryEntriesCount');
        var $summaryCredit = $('#summaryCreditTotal');
        var $summaryDebit = $('#summaryDebitTotal');
        var $dateRange = $('#dateRange');
        var $customDateRange = $('#customDateRange');
        var $startDate = $('#startDate');
        var $endDate = $('#endDate');
        var $tableNotice = $('#walletTransactionsTableNotice');
        var $tableBody = $('#walletTransactionsTableBody');
        var $applyButton = $('#walletTransactionsApplyBtn');
        var $resetButton = $('#walletTransactionsResetBtn');
        var autoSubmitTimer = null;

        function escapeHtml(value) {
          return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        function formatCurrency(amount) {
          var numericAmount = Number(amount || 0);
          return '&#8358;' + numericAmount.toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        }

        function formatInteger(value) {
          return Number(value || 0).toLocaleString('en-NG');
        }

        function showAlert(type, message) {
          if (!message) {
            $alert.addClass('d-none').removeClass('alert-success alert-danger alert-warning alert-info').text('');
            return;
          }

          $alert
            .removeClass('d-none alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + type)
            .text(message);
        }

        function toggleCustomDateRange() {
          var showCustomRange = $dateRange.val() === 'custom';
          $customDateRange.toggleClass('d-none', !showCustomRange);
        }

        function isDesktopViewport() {
          return window.matchMedia('(min-width: 992px)').matches;
        }

        function queueAutoSubmit() {
          if (!walletTablesReady || !isDesktopViewport()) {
            return;
          }

          clearTimeout(autoSubmitTimer);
          autoSubmitTimer = window.setTimeout(function() {
            $form.trigger('submit');
          }, 250);
        }

        function maybeSubmitCustomRange() {
          if ($dateRange.val() !== 'custom') {
            queueAutoSubmit();
            return;
          }

          var startValue = $startDate.val();
          var endValue = $endDate.val();
          if (startValue !== '' && endValue !== '') {
            queueAutoSubmit();
          }
        }

        function destroyDataTable() {
          if (walletTransactionsTable) {
            walletTransactionsTable.destroy();
            walletTransactionsTable = null;
          }
        }

        function initializeDataTable(shouldInitialize) {
          destroyDataTable();

          if (!walletTablesReady || !shouldInitialize) {
            return;
          }

          walletTransactionsTable = new DataTable('#walletTransactionsTable', {
            order: [[0, 'desc']],
            pageLength: 25,
            scrollX: true,
            language: {
              emptyTable: 'No wallet transactions matched the current filters.'
            }
          });
        }

        function setBusy(isBusy) {
          if (!walletTablesReady) {
            return;
          }

          $applyButton.prop('disabled', isBusy);
          $resetButton.prop('disabled', isBusy);
          $form.find('select, input').prop('disabled', isBusy);
        }

        function buildLocation(transaction) {
          var parts = [];

          if (transaction.school_code) {
            parts.push(transaction.school_code);
          }
          if (transaction.faculty_name) {
            parts.push(transaction.faculty_name);
          }
          if (transaction.dept_name) {
            parts.push(transaction.dept_name);
          }

          return parts.length ? parts.join(' / ') : 'No academic scope';
        }

        function buildRowsHtml(response) {
          if (!response.wallet_tables_ready) {
            return '<tr><td colspan="6" class="text-center text-muted py-4">Wallet ledger tables are unavailable in this database.</td></tr>';
          }

          var transactions = Array.isArray(response.transactions) ? response.transactions : [];
          if (!transactions.length) {
            return '<tr><td colspan="6" class="text-center text-muted py-4">No wallet transactions matched the current filters.</td></tr>';
          }

          return transactions.map(function(transaction) {
            var amountClass = transaction.direction === 'credit' ? 'text-success' : (transaction.direction === 'debit' ? 'text-danger' : 'text-body');
            var studentLabel = transaction.student_name ? transaction.student_name : 'Unknown User';
            var subLabel = transaction.matric_no ? transaction.matric_no : transaction.email;

            return '<tr>' +
              '<td data-order="' + escapeHtml(transaction.created_at || '') + '">' + escapeHtml(transaction.created_at_display || '-') + '</td>' +
              '<td>' +
                '<div class="fw-semibold">' + escapeHtml(studentLabel) + '</div>' +
                '<small class="text-muted d-block">' + escapeHtml(subLabel || '') + '</small>' +
                '<small class="text-muted d-block">' + escapeHtml(buildLocation(transaction)) + '</small>' +
              '</td>' +
              '<td>' + escapeHtml(transaction.direction_label || '') + '</td>' +
              '<td data-order="' + escapeHtml(String(transaction.amount || 0)) + '" class="fw-semibold ' + amountClass + '">' +
                escapeHtml(transaction.amount_sign || '') + formatCurrency(transaction.amount || 0) +
              '</td>' +
              '<td data-order="' + escapeHtml(String(transaction.balance_after || 0)) + '">' + formatCurrency(transaction.balance_after || 0) + '</td>' +
              '<td>' + escapeHtml(transaction.reference_display || '-') + '</td>' +
            '</tr>';
          }).join('');
        }

        function renderResponse(response) {
          walletTablesReady = !!response.wallet_tables_ready;
          hasTransactionRows = !!response.has_transaction_rows;

          $summaryEntries.text(formatInteger(response.summary && response.summary.entries_count ? response.summary.entries_count : 0));
          $summaryCredit.html(formatCurrency(response.summary && response.summary.credit_total ? response.summary.credit_total : 0));
          $summaryDebit.html(formatCurrency(response.summary && response.summary.debit_total ? response.summary.debit_total : 0));

          if (response.table_notice) {
            $tableNotice.removeClass('d-none').text(response.table_notice);
          } else {
            $tableNotice.addClass('d-none').text('');
          }

          destroyDataTable();
          $tableBody.html(buildRowsHtml(response));
          initializeDataTable(hasTransactionRows);
        }

        function updateUrl(filters) {
          var activeFilters = filters || {};
          var params = new URLSearchParams();

          if ((activeFilters.direction || 'all') !== 'all') {
            params.set('direction', activeFilters.direction);
          }

          if ((activeFilters.date_range || 'today') !== 'today') {
            params.set('date_range', activeFilters.date_range);
          }

          if ((activeFilters.date_range || 'today') === 'custom') {
            if (activeFilters.start_date) {
              params.set('start_date', activeFilters.start_date);
            }
            if (activeFilters.end_date) {
              params.set('end_date', activeFilters.end_date);
            }
          }

          var nextUrl = params.toString() === '' ? 'wallet_transactions.php' : 'wallet_transactions.php?' + params.toString();
          window.history.replaceState({}, '', nextUrl);
        }

        function fetchTransactions() {
          if (!walletTablesReady) {
            return;
          }

          showAlert('', '');

          if (pendingRequest) {
            pendingRequest.abort();
          }

          requestSequence += 1;
          var currentRequestId = requestSequence;
          var requestData = $form.serialize();

          setBusy(true);
          pendingRequest = $.ajax({
            url: endpointUrl,
            method: 'GET',
            dataType: 'json',
            data: requestData
          });

          pendingRequest.done(function(response) {
            if (currentRequestId !== requestSequence) {
              return;
            }

            renderResponse(response);
            updateUrl(response.filters || null);
          }).fail(function(xhr, textStatus) {
            if (textStatus === 'abort') {
              return;
            }

            if (currentRequestId !== requestSequence) {
              return;
            }

            var message = 'Unable to load wallet transactions.';
            var response = xhr.responseJSON;

            if (!response && xhr.responseText) {
              try {
                response = JSON.parse(xhr.responseText);
              } catch (error) {
                response = null;
              }
            }

            if (response && response.message) {
              message = response.message;
            }

            showAlert('danger', message);
          }).always(function() {
            if (currentRequestId !== requestSequence) {
              return;
            }

            pendingRequest = null;
            setBusy(false);
            toggleCustomDateRange();
          });
        }

        $('#direction').on('change', queueAutoSubmit);
        $dateRange.on('change', function() {
          toggleCustomDateRange();

          if ($(this).val() !== 'custom') {
            $startDate.val('');
            $endDate.val('');
          }

          maybeSubmitCustomRange();
        });

        $startDate.on('change', maybeSubmitCustomRange);
        $endDate.on('change', maybeSubmitCustomRange);

        $form.on('submit', function(event) {
          event.preventDefault();
          fetchTransactions();
        });

        $resetButton.on('click', function() {
          if (!walletTablesReady) {
            return;
          }

          $form[0].reset();
          $dateRange.val('today');
          $startDate.val('');
          $endDate.val('');
          toggleCustomDateRange();
          fetchTransactions();
        });

        initializeDataTable(hasTransactionRows);
        toggleCustomDateRange();
      });
    </script>
  </body>
</html>