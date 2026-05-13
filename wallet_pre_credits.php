<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once(__DIR__ . '/model/wallet_pre_credit_service.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRoles = [1, 2, 3, 4];

if (!$finance_mgt_menu || !in_array($admin_role, $allowedRoles, true)) {
  header('Location: /');
  exit();
}

$missingTables = ccWalletPreCreditGetMissingTables($conn);
$tablesReady = $missingTables === [];
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Wallet Pre-Credit | Nivasity Command Center</title>
    <meta name="description" content="Manually pre-credit student wallets while waiting for delayed Paystack confirmations." />
    <?php include('partials/_head.php') ?>
    <style>
      .wallet-pre-credit-preview {
        border: 1px dashed rgba(105, 108, 255, 0.35);
        border-radius: 0.75rem;
        padding: 1rem;
        background: rgba(105, 108, 255, 0.04);
      }

      .wallet-pre-credit-flag {
        font-size: 0.75rem;
      }
    </style>
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
                  <h4 class="fw-bold py-3 mb-1"><span class="text-muted fw-light">Finances / Wallets /</span> Wallet Pre-Credit</h4>
                  <p class="mb-0 text-muted">Manually credit student wallets when bank downtime delays Paystack delivery. Each credit stays open until Paystack later confirms it or the amount is disputed.</p>
                </div>
              </div>

              <?php if (!$tablesReady) { ?>
              <div class="alert alert-warning" role="alert">
                Wallet pre-credit tables are not available in this environment yet. Missing table(s): <strong><?php echo htmlspecialchars(implode(', ', $missingTables)); ?></strong>.
              </div>
              <?php } ?>

              <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Total Records</span>
                      <h3 class="card-title mb-1" id="walletPreCreditTotalCount">0</h3>
                      <small class="text-muted">All pre-credit rows matching the current filters.</small>
                    </div>
                  </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Pending</span>
                      <h3 class="card-title mb-1 text-warning" id="walletPreCreditPendingCount">0</h3>
                      <small class="text-muted">Wallet credits still waiting for Paystack confirmation.</small>
                    </div>
                  </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Confirmed</span>
                      <h3 class="card-title mb-1 text-success" id="walletPreCreditConfirmedCount">0</h3>
                      <small class="text-muted">Rows that have already been matched to Paystack confirmation.</small>
                    </div>
                  </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Overdue 48h+</span>
                      <h3 class="card-title mb-1 text-danger" id="walletPreCreditOverdueCount">0</h3>
                      <small class="text-muted">Pending rows older than 48 hours and still unresolved.</small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-body">
                  <div id="walletPreCreditPageAlert" class="alert d-none" role="alert"></div>
                  <form id="walletPreCreditFilters" class="row g-3 align-items-end">
                    <div class="col-xl-3 col-lg-4 col-md-6">
                      <label for="walletPreCreditStatusFilter" class="form-label">Status</label>
                      <select class="form-select" id="walletPreCreditStatusFilter" name="status" <?php echo !$tablesReady ? 'disabled' : ''; ?>>
                        <option value="all">All statuses</option>
                        <option value="pending_confirmation">Pending confirmation</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="amount_disputed">Amount disputed</option>
                      </select>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                      <label for="walletPreCreditDateRange" class="form-label">Date range</label>
                      <select class="form-select" id="walletPreCreditDateRange" name="date_range" <?php echo !$tablesReady ? 'disabled' : ''; ?>>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="7">Last 7 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="all">All Time</option>
                        <option value="custom">Custom Range</option>
                      </select>
                    </div>
                    <div class="col-xl-6 col-lg-12 d-none" id="walletPreCreditCustomDateRange">
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label for="walletPreCreditStartDate" class="form-label">Start Date</label>
                          <input type="date" class="form-control" id="walletPreCreditStartDate" name="start_date" <?php echo !$tablesReady ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                          <label for="walletPreCreditEndDate" class="form-label">End Date</label>
                          <input type="date" class="form-control" id="walletPreCreditEndDate" name="end_date" <?php echo !$tablesReady ? 'disabled' : ''; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                      <small class="text-muted">Use this workflow only for genuine downtime-driven bank transfers that still need later Paystack confirmation.</small>
                      <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary d-lg-none" <?php echo !$tablesReady ? 'disabled' : ''; ?>>Apply Filters</button>
                        <button type="button" id="walletPreCreditResetBtn" class="btn btn-outline-secondary" <?php echo !$tablesReady ? 'disabled' : ''; ?>>Reset</button>
                      </div>
                    </div>
                  </form>
                  <div class="pt-3 small text-muted d-none" id="walletPreCreditTableNotice"></div>
                  <div class="pt-4">
                    <div class="table-responsive text-nowrap">
                      <table id="walletPreCreditTable" class="table table-striped align-middle w-100">
                        <thead class="table-secondary">
                          <tr>
                            <th>Date &amp; Time</th>
                            <th>Student</th>
                            <th>Account Number</th>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Receipt</th>
                            <th>Flag</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody id="walletPreCreditTableBody">
                          <tr>
                            <td colspan="9" class="text-center text-muted py-4"><?php echo $tablesReady ? 'Loading wallet pre-credit records...' : 'Wallet pre-credit tables are unavailable in this database.'; ?></td>
                          </tr>
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

    <button type="button" class="btn btn-primary new_formBtn" data-bs-toggle="modal" data-bs-target="#walletPreCreditModal" aria-label="Create wallet pre-credit" <?php echo !$tablesReady ? 'disabled' : ''; ?>>
      <i class="bx bx-plus fs-3"></i>
    </button>

    <div class="modal fade" id="walletPreCreditModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <form id="walletPreCreditCreateForm" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title">New Wallet Pre-Credit</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div id="walletPreCreditModalAlert" class="alert d-none" role="alert"></div>
              <div class="row g-3 align-items-end">
                <div class="col-md-8">
                  <label for="walletPreCreditAccountNumber" class="form-label">Wallet Account Number</label>
                  <input type="text" class="form-control" id="walletPreCreditAccountNumber" name="account_number" inputmode="numeric" autocomplete="off" placeholder="Enter the wallet virtual account number" required>
                </div>
                <div class="col-md-4 d-grid">
                  <button type="button" class="btn btn-outline-primary" id="walletPreCreditLookupBtn">Look Up Wallet</button>
                </div>
              </div>
              <div class="wallet-pre-credit-preview mt-3 d-none" id="walletPreCreditWalletPreview"></div>
              <div class="row g-3 mt-1 d-none" id="walletPreCreditDetailFields">
                <div class="col-md-6">
                  <label for="walletPreCreditReference" class="form-label">Reference ID</label>
                  <input type="text" class="form-control" id="walletPreCreditReference" name="provider_reference" placeholder="Reference not yet in DB" maxlength="50" required>
                </div>
                <div class="col-md-6">
                  <label for="walletPreCreditAmount" class="form-label">Amount</label>
                  <input type="number" class="form-control" id="walletPreCreditAmount" name="amount" min="1" step="1" placeholder="0" required>
                </div>
                <div class="col-md-12">
                  <label for="walletPreCreditReceipt" class="form-label">Receipt (Image or PDF)</label>
                  <input type="file" class="form-control" id="walletPreCreditReceipt" name="receipt" accept="image/*,.pdf,application/pdf" required>
                </div>
                <div class="col-md-12">
                  <label for="walletPreCreditAdminNote" class="form-label">Admin Note (optional)</label>
                  <textarea class="form-control" id="walletPreCreditAdminNote" name="admin_note" rows="3" placeholder="Add any operator context about the delayed transfer"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" id="walletPreCreditSubmitBtn" disabled>Credit Wallet</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="walletPreCreditDetailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Wallet Pre-Credit Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="walletPreCreditDetailAlert" class="alert d-none" role="alert"></div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <small class="text-muted">Student</small>
                <div class="fw-semibold" id="walletPreCreditDetailStudent">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted">Status</small>
                <div class="fw-semibold" id="walletPreCreditDetailStatusLabel">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted">Reference ID</small>
                <div class="fw-semibold" id="walletPreCreditDetailReference">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted">Amount</small>
                <div class="fw-semibold" id="walletPreCreditDetailAmount">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted">Account Number</small>
                <div class="fw-semibold" id="walletPreCreditDetailAccountNumber">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted">Confirmed Amount</small>
                <div class="fw-semibold" id="walletPreCreditDetailConfirmedAmount">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted">Created</small>
                <div class="fw-semibold" id="walletPreCreditDetailCreatedAt">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted">Confirmed At</small>
                <div class="fw-semibold" id="walletPreCreditDetailConfirmedAt">-</div>
              </div>
              <div class="col-md-12">
                <small class="text-muted">Receipt</small>
                <div class="fw-semibold" id="walletPreCreditDetailReceipt">-</div>
              </div>
            </div>

            <form id="walletPreCreditUpdateForm">
              <input type="hidden" name="pre_credit_id" id="walletPreCreditDetailId">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="walletPreCreditDetailStatus" class="form-label">Reconciliation Status</label>
                  <select class="form-select" id="walletPreCreditDetailStatus" name="status">
                    <option value="pending_confirmation">Pending confirmation</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="amount_disputed">Amount disputed</option>
                  </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                  <div class="w-100 small text-muted" id="walletPreCreditDetailFlagText">No pending flag.</div>
                </div>
                <div class="col-md-12">
                  <label for="walletPreCreditDetailAdminNote" class="form-label">Admin Note</label>
                  <textarea class="form-control" id="walletPreCreditDetailAdminNote" name="admin_note" rows="3" placeholder="Internal admin note"></textarea>
                </div>
                <div class="col-md-12">
                  <label for="walletPreCreditDetailReconciliationNote" class="form-label">Reconciliation Note</label>
                  <textarea class="form-control" id="walletPreCreditDetailReconciliationNote" name="reconciliation_note" rows="3" placeholder="Confirmation or dispute context"></textarea>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" form="walletPreCreditUpdateForm" id="walletPreCreditUpdateBtn">Save Update</button>
          </div>
        </div>
      </div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/ui-toasts.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      window.ccWalletPreCredits = {
        tablesReady: <?php echo $tablesReady ? 'true' : 'false'; ?>,
        missingTables: <?php echo json_encode($missingTables, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
      };
    </script>
    <script src="model/functions/wallet_pre_credits.js"></script>
  </body>
</html>