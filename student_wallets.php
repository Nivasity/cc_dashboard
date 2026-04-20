<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRoles = [1, 2, 3, 4, 5];

if (!$finance_mgt_menu || !in_array($admin_role, $allowedRoles, true)) {
  header('Location: /');
  exit();
}

function ccStudentWalletTableExists($conn, $tableName) {
  static $cache = [];

  if (array_key_exists($tableName, $cache)) {
    return $cache[$tableName];
  }

  $tableName = trim((string) $tableName);
  if ($tableName === '') {
    $cache[$tableName] = false;
    return false;
  }

  $safeTableName = mysqli_real_escape_string($conn, $tableName);
  $result = mysqli_query($conn, "SHOW TABLES LIKE '$safeTableName'");
  $cache[$tableName] = $result && mysqli_num_rows($result) > 0;

  return $cache[$tableName];
}

$requiredTables = ['users', 'schools', 'depts', 'faculties', 'user_wallets', 'wallet_virtual_accounts', 'wallet_ledger_entries'];
$missingTables = [];

foreach ($requiredTables as $requiredTable) {
  if (!ccStudentWalletTableExists($conn, $requiredTable)) {
    $missingTables[] = $requiredTable;
  }
}

$walletTablesReady = empty($missingTables);
$initialLookup = trim((string) ($_GET['lookup'] ?? ''));
$allowedEntryTypes = ['all', 'credit', 'debit', 'refund', 'fee', 'adjustment'];
$initialEntryType = strtolower(trim((string) ($_GET['entry_type'] ?? 'all')));
if (!in_array($initialEntryType, $allowedEntryTypes, true)) {
  $initialEntryType = 'all';
}
$initialDateFrom = trim((string) ($_GET['date_from'] ?? ''));
$initialDateTo = trim((string) ($_GET['date_to'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Student Wallets | Nivasity Command Center</title>
    <meta name="description" content="Find a student wallet by matric number or email and review balance and ledger history." />
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
                  <h4 class="fw-bold py-3"><span class="text-muted fw-light">Finances /</span> Student Wallets</h4>
                </div>
              </div>

              <?php if (!$walletTablesReady) { ?>
              <div class="alert alert-warning" role="alert">
                Wallet tables are not available in this environment yet. Missing table(s): <strong><?php echo htmlspecialchars(implode(', ', $missingTables)); ?></strong>.
              </div>
              <?php } ?>

              <div class="card mb-4">
                <div class="card-body">
                  <form id="walletLookupForm" class="row g-3 align-items-end">
                    <div class="col-lg-8">
                      <label for="walletLookup" class="form-label">Matric Number or Email Address</label>
                      <input
                        type="text"
                        class="form-control"
                        id="walletLookup"
                        name="lookup"
                        value="<?php echo htmlspecialchars($initialLookup); ?>"
                        placeholder="e.g. 19/1234 or student@example.com"
                        <?php echo !$walletTablesReady ? 'disabled' : ''; ?>
                      />
                    </div>
                    <div class="col-lg-4 d-flex gap-2">
                      <button type="submit" class="btn btn-primary" id="lookupSubmitBtn" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>Find Wallet</button>
                      <button type="button" class="btn btn-outline-secondary" id="lookupClearBtn" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>Clear</button>
                    </div>
                  </form>
                </div>
              </div>

              <div id="walletAjaxAlert"></div>

              <div class="row mb-4 d-none" id="walletSummaryRow">
                <div class="col-xl-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Current Balance</span>
                      <h3 class="mb-0" id="walletCurrentBalance">&#8358;0</h3>
                      <small class="text-muted">Stored in <strong>user_wallets.balance</strong></small>
                    </div>
                  </div>
                </div>
                <div class="col-xl-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Total Credits</span>
                      <h3 class="mb-0 text-success" id="walletTotalCredits">&#8358;0</h3>
                      <small class="text-muted">Credit + refund entries</small>
                    </div>
                  </div>
                </div>
                <div class="col-xl-4 col-md-12 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Total Debits</span>
                      <h3 class="mb-0 text-danger" id="walletTotalDebits">&#8358;0</h3>
                      <small class="text-muted" id="walletEntriesCount">0 ledger entries</small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row g-4 d-none" id="walletContentRow">
                <div class="col-xl-4">
                  <div class="card mb-4">
                    <div class="card-header">
                      <h5 class="mb-1">Student Details</h5>
                    </div>
                    <div class="card-body" id="studentDetailsBody">
                      <div class="text-muted">Search for a student to see details.</div>
                    </div>
                  </div>

                  <div class="card">
                    <div class="card-header">
                      <h5 class="mb-1">Wallet Details</h5>
                    </div>
                    <div class="card-body" id="walletDetailsBody">
                      <div class="text-muted">Run a lookup to load wallet details.</div>
                    </div>
                  </div>
                </div>

                <div class="col-xl-8">
                  <div class="card">
                    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                      <div>
                        <h5 class="mb-1">Wallet Ledger</h5>
                        <small class="text-muted">Most recent 50 entries from <strong>wallet_ledger_entries</strong>, with optional date and type filters.</small>
                      </div>
                      <div class="text-muted small d-none" id="walletLedgerMeta"></div>
                    </div>
                    <div class="card-body">
                      <form id="walletFilterForm" class="row g-3 align-items-end mb-4">
                        <div class="col-md-4">
                          <label for="walletFilterEntryType" class="form-label">Entry Type</label>
                          <select class="form-select" id="walletFilterEntryType" name="entry_type" disabled>
                            <option value="all" <?php echo $initialEntryType === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="credit" <?php echo $initialEntryType === 'credit' ? 'selected' : ''; ?>>Credit</option>
                            <option value="debit" <?php echo $initialEntryType === 'debit' ? 'selected' : ''; ?>>Debit</option>
                            <option value="refund" <?php echo $initialEntryType === 'refund' ? 'selected' : ''; ?>>Refund</option>
                            <option value="fee" <?php echo $initialEntryType === 'fee' ? 'selected' : ''; ?>>Fee</option>
                            <option value="adjustment" <?php echo $initialEntryType === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                          </select>
                        </div>
                        <div class="col-md-3">
                          <label for="walletFilterDateFrom" class="form-label">From</label>
                          <input type="date" class="form-control" id="walletFilterDateFrom" name="date_from" value="<?php echo htmlspecialchars($initialDateFrom); ?>" disabled />
                        </div>
                        <div class="col-md-3">
                          <label for="walletFilterDateTo" class="form-label">To</label>
                          <input type="date" class="form-control" id="walletFilterDateTo" name="date_to" value="<?php echo htmlspecialchars($initialDateTo); ?>" disabled />
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                          <button type="submit" class="btn btn-primary w-100" id="walletFilterApplyBtn" disabled>Apply</button>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                          <small class="text-muted" id="walletFilterSummary">Filters become available after a wallet is loaded.</small>
                          <button type="button" class="btn btn-sm btn-outline-secondary" id="walletFilterResetBtn" disabled>Reset Filters</button>
                        </div>
                      </form>
                      <div class="table-responsive text-nowrap">
                        <table class="table table-striped align-middle">
                          <thead class="table-secondary">
                            <tr>
                              <th>Date</th>
                              <th>Type</th>
                              <th>Amount</th>
                              <th>Balance Before</th>
                              <th>Balance After</th>
                              <th>Status</th>
                              <th>Reference</th>
                              <th>Description</th>
                            </tr>
                          </thead>
                          <tbody id="walletLedgerBody">
                            <tr>
                              <td colspan="8" class="text-center text-muted py-4">Search for a student to see wallet ledger entries.</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
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
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      $(function() {
        var walletTablesReady = <?php echo $walletTablesReady ? 'true' : 'false'; ?>;
        var endpointUrl = 'model/student_wallet_lookup.php';
        var currentLookup = <?php echo json_encode($initialLookup); ?>;
        var currentHasWallet = false;
        var pendingRequest = null;
        var requestSequence = 0;

        var $lookupForm = $('#walletLookupForm');
        var $lookupInput = $('#walletLookup');
        var $lookupSubmitBtn = $('#lookupSubmitBtn');
        var $lookupClearBtn = $('#lookupClearBtn');
        var $alertWrap = $('#walletAjaxAlert');
        var $summaryRow = $('#walletSummaryRow');
        var $contentRow = $('#walletContentRow');
        var $currentBalance = $('#walletCurrentBalance');
        var $totalCredits = $('#walletTotalCredits');
        var $totalDebits = $('#walletTotalDebits');
        var $entriesCount = $('#walletEntriesCount');
        var $studentDetailsBody = $('#studentDetailsBody');
        var $walletDetailsBody = $('#walletDetailsBody');
        var $ledgerMeta = $('#walletLedgerMeta');
        var $filterForm = $('#walletFilterForm');
        var $filterEntryType = $('#walletFilterEntryType');
        var $filterDateFrom = $('#walletFilterDateFrom');
        var $filterDateTo = $('#walletFilterDateTo');
        var $filterApplyBtn = $('#walletFilterApplyBtn');
        var $filterResetBtn = $('#walletFilterResetBtn');
        var $filterSummary = $('#walletFilterSummary');
        var $ledgerBody = $('#walletLedgerBody');

        function escapeHtml(value) {
          return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        function formatCurrency(value) {
          return '₦' + Number(value || 0).toLocaleString();
        }

        function clearAlert() {
          $alertWrap.html('');
        }

        function showAlert(type, message) {
          $alertWrap.html('<div class="alert alert-' + type + '" role="alert">' + escapeHtml(message) + '</div>');
        }

        function setFilterEnabled(enabled) {
          currentHasWallet = !!enabled;
          $filterEntryType.prop('disabled', !enabled);
          $filterDateFrom.prop('disabled', !enabled);
          $filterDateTo.prop('disabled', !enabled);
          $filterApplyBtn.prop('disabled', !enabled);
          $filterResetBtn.prop('disabled', !enabled);
        }

        function setBusy(isBusy, mode) {
          mode = mode || 'lookup';
          $lookupSubmitBtn.prop('disabled', isBusy || !walletTablesReady).text(isBusy && mode === 'lookup' ? 'Loading...' : 'Find Wallet');
          $lookupClearBtn.prop('disabled', isBusy || !walletTablesReady);
          $filterEntryType.prop('disabled', isBusy || !currentHasWallet);
          $filterDateFrom.prop('disabled', isBusy || !currentHasWallet);
          $filterDateTo.prop('disabled', isBusy || !currentHasWallet);
          $filterApplyBtn.prop('disabled', isBusy || !currentHasWallet).text(isBusy && mode === 'filter' ? 'Applying...' : 'Apply');
          $filterResetBtn.prop('disabled', isBusy || !currentHasWallet);
        }

        function resetResults(message) {
          $summaryRow.addClass('d-none');
          $contentRow.addClass('d-none');
          setFilterEnabled(false);
          $ledgerMeta.addClass('d-none').text('');
          $studentDetailsBody.html('<div class="text-muted">Search for a student to see details.</div>');
          $walletDetailsBody.html('<div class="text-muted">Run a lookup to load wallet details.</div>');
          $ledgerBody.html('<tr><td colspan="8" class="text-center text-muted py-4">' + escapeHtml(message || 'Search for a student to see wallet ledger entries.') + '</td></tr>');
          $filterSummary.text('Filters become available after a wallet is loaded.');
        }

        function renderStudent(student) {
          var html = '' +
            '<dl class="row mb-0">' +
              '<dt class="col-sm-4">Name</dt>' +
              '<dd class="col-sm-8">' + escapeHtml(student.full_name || '-') + '</dd>' +
              '<dt class="col-sm-4">Matric</dt>' +
              '<dd class="col-sm-8">' + escapeHtml(student.matric_no || '-') + '</dd>' +
              '<dt class="col-sm-4">Email</dt>' +
              '<dd class="col-sm-8">' + escapeHtml(student.email || '-') + '</dd>' +
              '<dt class="col-sm-4">Phone</dt>' +
              '<dd class="col-sm-8">' + escapeHtml(student.phone || '-') + '</dd>' +
              '<dt class="col-sm-4">School</dt>' +
              '<dd class="col-sm-8">' + escapeHtml(student.school_name || '-') + '</dd>' +
              '<dt class="col-sm-4">Faculty</dt>' +
              '<dd class="col-sm-8">' + escapeHtml(student.faculty_name || '-') + '</dd>' +
              '<dt class="col-sm-4">Department</dt>' +
              '<dd class="col-sm-8">' + escapeHtml(student.dept_name || '-') + '</dd>' +
              '<dt class="col-sm-4">Status</dt>' +
              '<dd class="col-sm-8"><span class="badge bg-label-' + escapeHtml(student.status_badge || 'secondary') + '">' + escapeHtml(student.status_label || 'Unknown') + '</span></dd>' +
            '</dl>';
          $studentDetailsBody.html(html);
        }

        function renderWallet(wallet) {
          if (!wallet) {
            $walletDetailsBody.html('<div class="alert alert-info mb-0" role="alert">This student exists, but no wallet has been created for the account yet.</div>');
            return;
          }

          var html = '' +
            '<div class="d-flex justify-content-between align-items-center mb-3">' +
              '<h6 class="mb-0">Wallet Profile</h6>' +
              '<span class="badge bg-label-' + escapeHtml(wallet.status_badge || 'secondary') + '">' + escapeHtml(wallet.status_label || 'Unknown') + '</span>' +
            '</div>' +
            '<dl class="row mb-0">' +
              '<dt class="col-sm-5">Wallet ID</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.id || 0) + '</dd>' +
              '<dt class="col-sm-5">Requested Via</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.requested_via || '-') + '</dd>' +
              '<dt class="col-sm-5">Currency</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.currency || 'NGN') + '</dd>' +
              '<dt class="col-sm-5">Account Name</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.account_name || '-') + '</dd>' +
              '<dt class="col-sm-5">Account Number</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.account_number || '-') + '</dd>' +
              '<dt class="col-sm-5">Bank</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.bank_name || '-') + '</dd>' +
              '<dt class="col-sm-5">Provider</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.provider || '-') + '</dd>' +
              '<dt class="col-sm-5">VA Status</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.virtual_account_status_label || '-') + '</dd>' +
              '<dt class="col-sm-5">Created</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.created_at_display || '-') + '</dd>' +
              '<dt class="col-sm-5">Updated</dt>' +
              '<dd class="col-sm-7">' + escapeHtml(wallet.updated_at_display || '-') + '</dd>' +
            '</dl>';
          $walletDetailsBody.html(html);
        }

        function renderOverview(wallet, overview) {
          $currentBalance.text(formatCurrency(wallet.balance || 0));
          $totalCredits.text(formatCurrency(overview.credits_total || 0));
          $totalDebits.text(formatCurrency(overview.debits_total || 0));
          var entriesCount = Number(overview.entries_count || 0);
          $entriesCount.text(entriesCount.toLocaleString() + ' ledger ' + (entriesCount === 1 ? 'entry' : 'entries'));
          $summaryRow.removeClass('d-none');
          $ledgerMeta.text('Refunds: ' + formatCurrency(overview.refunds_total || 0) + ' | Fees: ' + formatCurrency(overview.fees_total || 0)).removeClass('d-none');
        }

        function renderEntries(entries, hasWallet, filters) {
          var hasActiveFilters = !!(filters && filters.has_active_filters);
          if (!hasWallet) {
            $ledgerBody.html('<tr><td colspan="8" class="text-center text-muted py-4">No wallet ledger is available because the student does not have a wallet yet.</td></tr>');
            $filterSummary.text('This student does not have a wallet yet, so there are no wallet transactions to filter.');
            return;
          }

          if (!Array.isArray(entries) || entries.length === 0) {
            $ledgerBody.html('<tr><td colspan="8" class="text-center text-muted py-4">No wallet ledger entries matched the current selection.</td></tr>');
            $filterSummary.text('No wallet ledger entries matched the current selection.');
            return;
          }

          var rows = entries.map(function(entry) {
            var amountClass = entry.direction === 'credit' ? 'text-success' : (entry.direction === 'debit' ? 'text-danger' : 'text-muted');
            return '' +
              '<tr>' +
                '<td>' + escapeHtml(entry.created_at_display || '-') + '</td>' +
                '<td><span class="badge bg-label-' + escapeHtml(entry.entry_type_badge || 'secondary') + '">' + escapeHtml(entry.entry_type_label || 'Unknown') + '</span></td>' +
                '<td class="' + amountClass + ' fw-semibold">' + escapeHtml(entry.amount_sign || '') + formatCurrency(entry.amount || 0) + '</td>' +
                '<td>' + formatCurrency(entry.balance_before || 0) + '</td>' +
                '<td>' + formatCurrency(entry.balance_after || 0) + '</td>' +
                '<td><span class="badge bg-label-' + escapeHtml(entry.status_badge || 'secondary') + '">' + escapeHtml(entry.status_label || 'Unknown') + '</span></td>' +
                '<td class="text-wrap" style="min-width: 180px;">' + escapeHtml(entry.reference_display || '-') + '</td>' +
                '<td class="text-wrap" style="min-width: 220px;">' + escapeHtml(entry.description || '-') + '</td>' +
              '</tr>';
          }).join('');

          $ledgerBody.html(rows);
          $filterSummary.text('Showing ' + entries.length + ' result' + (entries.length === 1 ? '' : 's') + (hasActiveFilters ? ' for the active filters.' : ' without additional filters.'));
        }

        function renderResponse(response) {
          $contentRow.removeClass('d-none');
          renderStudent(response.student || {});

          if (!response.has_wallet) {
            $summaryRow.addClass('d-none');
            $ledgerMeta.addClass('d-none').text('');
            renderWallet(null);
            renderEntries([], false, response.filters || {});
            setFilterEnabled(false);
            return;
          }

          renderWallet(response.wallet || {});
          renderOverview(response.wallet || {}, response.overview || {});
          renderEntries(response.entries || [], true, response.filters || {});
          setFilterEnabled(true);
        }

        function runLookup(mode) {
          if (!walletTablesReady) {
            return;
          }

          var lookupValue = $.trim($lookupInput.val());
          if (lookupValue === '') {
            showAlert('warning', 'Enter a matric number or email address.');
            return;
          }

          clearAlert();
          currentLookup = lookupValue;

          if (pendingRequest) {
            pendingRequest.abort();
          }

          requestSequence += 1;
          var currentRequestId = requestSequence;
          setBusy(true, mode);
          pendingRequest = $.ajax({
            url: endpointUrl,
            method: 'POST',
            dataType: 'json',
            data: {
              lookup: lookupValue,
              entry_type: $filterEntryType.val(),
              date_from: $filterDateFrom.val(),
              date_to: $filterDateTo.val()
            }
          });

          pendingRequest.done(function(response) {
            if (currentRequestId !== requestSequence) {
              return;
            }
            renderResponse(response);
          }).fail(function(xhr, textStatus) {
            if (textStatus === 'abort') {
              return;
            }

            if (currentRequestId !== requestSequence) {
              return;
            }

            var message = 'Unable to load wallet details.';
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

            if (xhr.status === 404) {
              resetResults(message);
            }

            showAlert('danger', message);
          }).always(function() {
            if (currentRequestId !== requestSequence) {
              return;
            }
            pendingRequest = null;
            setBusy(false, mode);
          });
        }

        $lookupForm.on('submit', function(event) {
          event.preventDefault();
          runLookup('lookup');
        });

        $filterForm.on('submit', function(event) {
          event.preventDefault();
          if (!currentHasWallet) {
            return;
          }
          runLookup('filter');
        });

        $filterResetBtn.on('click', function() {
          $filterEntryType.val('all');
          $filterDateFrom.val('');
          $filterDateTo.val('');
          if ($.trim($lookupInput.val()) !== '') {
            runLookup('filter');
          }
        });

        $lookupClearBtn.on('click', function() {
          if (pendingRequest) {
            pendingRequest.abort();
            pendingRequest = null;
          }
          requestSequence += 1;
          $lookupInput.val('');
          $filterEntryType.val('all');
          $filterDateFrom.val('');
          $filterDateTo.val('');
          clearAlert();
          resetResults('Search for a student to see wallet ledger entries.');
        });

        resetResults('Search for a student to see wallet ledger entries.');

        if (walletTablesReady && $.trim($lookupInput.val()) !== '') {
          runLookup('lookup');
        }
      });
    </script>
  </body>
</html>