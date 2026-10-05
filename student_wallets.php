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

              <!-- Bank deposits and refunds to the student's bank (model/wallet_deposit_refunds.php) -->
              <div class="row mt-4 d-none" id="walletDepositsRow">
                <div class="col-12">
                  <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                      <div>
                        <h5 class="mb-0">Deposits &amp; refunds</h5>
                        <small class="text-muted">Refund a bank deposit back to the student's bank through Paystack. The amount leaves the wallet immediately and returns if the refund fails.</small>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm mb-0">
                        <thead>
                          <tr><th>Date</th><th>Amount</th><th>Reference</th><th>Refunds</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody id="walletDepositsBody"></tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Refund deposit -->
            <div class="modal fade" id="refundDepositModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" id="refundDepositForm">
                  <div class="modal-header">
                    <h5 class="modal-title">Refund deposit to bank</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div id="refundDepositAlert" class="alert d-none" role="alert"></div>
                    <p class="mb-3 small" id="refundDepositInfo"></p>
                    <input type="hidden" id="refundFundingId" />
                    <div class="mb-3">
                      <label for="refundAmount" class="form-label">Amount (&#8358;)</label>
                      <input type="number" class="form-control" id="refundAmount" min="1" step="1" required />
                      <div class="form-text" id="refundAmountHint"></div>
                    </div>
                    <div class="mb-0">
                      <label for="refundReason" class="form-label">Reason</label>
                      <input type="text" class="form-control" id="refundReason" maxlength="250" required placeholder="e.g. School fees sent to wallet by mistake" />
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="refundDepositSubmit">Refund to bank</button>
                  </div>
                </form>
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
          $('#walletDepositsRow').addClass('d-none');
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
            $('#walletDepositsRow').addClass('d-none');
            $summaryRow.addClass('d-none');
            $ledgerMeta.addClass('d-none').text('');
            renderWallet(null);
            renderEntries([], false, response.filters || {});
            setFilterEnabled(false);
            return;
          }

          renderWallet(response.wallet || {});
          loadDeposits((response.wallet || {}).id);
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

        // ── Deposits & refunds ──
        var depositsWalletId = 0;
        var refundBadge = { processing: 'warning', refunded: 'success', failed: 'danger', needs_attention: 'info' };
        var refundLabel = { processing: 'Processing', refunded: 'Refunded', failed: 'Failed', needs_attention: 'Needs attention in Paystack' };

        function loadDeposits(walletId) {
          depositsWalletId = Number(walletId) || 0;
          if (!depositsWalletId) { $('#walletDepositsRow').addClass('d-none'); return; }
          $.post('model/wallet_deposit_refunds.php', { action: 'deposits', wallet_id: depositsWalletId }, null, 'json').done(function (res) {
            if (!res.success) {
              $('#walletDepositsRow').removeClass('d-none');
              $('#walletDepositsBody').html('<tr><td colspan="5" class="text-muted py-3">' + escapeHtml(res.message || 'Deposits unavailable') + '</td></tr>');
              return;
            }
            var rows = (res.deposits || []).map(function (d) {
              var refunds = (d.refunds || []).map(function (r) {
                var extra = r.status === 'failed' && r.failure_reason ? ' (' + escapeHtml(r.failure_reason) + ')' : '';
                var check = (r.status === 'processing' || r.status === 'needs_attention') && res.can_refund
                  ? ' <a href="javascript:void(0)" class="refund-check small" data-id="' + r.id + '">Check status</a>' : '';
                return '<div class="small"><span class="badge bg-label-' + (refundBadge[r.status] || 'secondary') + '">' + escapeHtml(refundLabel[r.status] || r.status) + '</span> '
                  + formatCurrency(r.amount) + extra + ' <span class="text-muted">· ' + escapeHtml(r.created_at) + (r.created_by ? ' by ' + escapeHtml(r.created_by) : '') + '</span>' + check + '</div>';
              }).join('');
              var action = res.can_refund && d.refundable > 0
                ? '<button type="button" class="btn btn-sm btn-outline-danger refund-open" data-id="' + d.id + '" data-amount="' + d.amount + '" data-refundable="' + d.refundable + '" data-ref="' + escapeHtml(d.reference) + '">Refund</button>'
                : '<span class="text-muted small">' + (d.status !== 'posted' ? escapeHtml(d.status) : (d.refundable > 0 ? '' : 'Nothing refundable')) + '</span>';
              return '<tr><td>' + escapeHtml(d.date) + '</td><td>' + formatCurrency(d.amount) + '</td><td class="small">' + escapeHtml(d.reference) + '</td><td>' + (refunds || '<span class="text-muted small">None</span>') + '</td><td class="text-end">' + action + '</td></tr>';
            });
            $('#walletDepositsBody').html(rows.length ? rows.join('') : '<tr><td colspan="5" class="text-muted py-3">No bank deposits on this wallet.</td></tr>');
            $('#walletDepositsRow').removeClass('d-none');
          });
        }

        $(document).on('click', '.refund-open', function () {
          var $b = $(this);
          var refundable = Number($b.data('refundable'));
          $('#refundFundingId').val($b.data('id'));
          $('#refundAmount').val(refundable).attr('max', refundable);
          $('#refundAmountHint').text('Up to ' + formatCurrency(refundable) + ' (deposit ' + formatCurrency($b.data('amount')) + ').');
          $('#refundReason').val('');
          $('#refundDepositInfo').text('Deposit ' + $b.data('ref') + '. Paystack sends the money back to the account it came from.');
          $('#refundDepositAlert').addClass('d-none');
          $('#refundDepositModal').modal('show');
        });

        $('#refundDepositForm').on('submit', function (e) {
          e.preventDefault();
          var amount = Number($('#refundAmount').val());
          if (!window.confirm('Refund ' + formatCurrency(amount) + ' to the student\'s bank? It leaves the wallet now.')) return;
          var $btn = $('#refundDepositSubmit').prop('disabled', true).text('Refunding...');
          $.post('model/wallet_deposit_refunds.php', {
            action: 'create', funding_id: $('#refundFundingId').val(), amount: amount, reason: $('#refundReason').val()
          }, null, 'json').done(function (res) {
            $('#refundDepositModal').modal('hide');
            showAlert('success', res.message || 'Refund started.');
            runLookup('filter');
          }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Refund failed.';
            $('#refundDepositAlert').removeClass('d-none alert-success').addClass('alert-danger').text(msg);
            if (depositsWalletId) loadDeposits(depositsWalletId);
          }).always(function () { $btn.prop('disabled', false).text('Refund to bank'); });
        });

        $(document).on('click', '.refund-check', function () {
          $.post('model/wallet_deposit_refunds.php', { action: 'check', refund_id: $(this).data('id') }, null, 'json')
            .done(function (res) { showAlert('info', res.message || 'Status updated.'); runLookup('filter'); })
            .fail(function (xhr) { showAlert('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'Could not check the refund.'); });
        });

        resetResults('Search for a student to see wallet ledger entries.');

        if (walletTablesReady && $.trim($lookupInput.val()) !== '') {
          runLookup('lookup');
        }
      });
    </script>
  </body>
</html>