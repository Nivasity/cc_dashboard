<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRefundRoles = [1, 2, 3, 4];

if (!$finance_mgt_menu || !in_array($admin_role, $allowedRefundRoles, true)) {
  header('Location: /');
  exit();
}

$schools = [];
$schoolQuery = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");

if ($schoolQuery) {
  while ($row = mysqli_fetch_assoc($schoolQuery)) {
    $schools[] = [
      'id' => (int) $row['id'],
      'name' => (string) $row['name'],
    ];
  }
}

$today = date('Y-m-d');
$thirtyDaysAgo = date('Y-m-d', strtotime('-29 days'));
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Refunds | Nivasity Command Center</title>
    <meta name="description" content="Refund queue, split ledger and liability monitoring" />
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
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Finances /</span> Refunds</h4>

              <div class="card mb-4">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-3">
                  <div>
                    <h5 class="mb-1">School-level Monitoring</h5>
                    <small class="text-muted">Outstanding liability and daily consumed refund amounts</small>
                  </div>
                  <form id="monitoringFilterForm" class="row g-2">
                    <div class="col-md-4">
                      <select id="monitoringSchoolId" class="form-select">
                        <option value="">All Schools</option>
                        <?php foreach ($schools as $school) { ?>
                          <option value="<?php echo (int) $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <input type="date" id="monitoringFromDate" class="form-control" value="<?php echo htmlspecialchars($thirtyDaysAgo); ?>" />
                    </div>
                    <div class="col-md-3">
                      <input type="date" id="monitoringToDate" class="form-control" value="<?php echo htmlspecialchars($today); ?>" />
                    </div>
                    <div class="col-md-2">
                      <button type="submit" class="btn btn-outline-primary w-100" aria-label="Refresh monitoring">
                        <i class='bx bx-refresh'></i>
                      </button>
                    </div>
                  </form>
                </div>
                <div class="card-body">
                  <div class="row mb-3">
                    <div class="col-md-4">
                      <div class="border rounded p-3 h-100">
                        <span class="text-muted d-block mb-1">Total Outstanding Liability</span>
                        <h4 class="mb-0" id="outstandingTotal">0.00</h4>
                      </div>
                    </div>
                  </div>
                  <div class="row g-4">
                    <div class="col-lg-6">
                      <h6>Outstanding Liability by School</h6>
                      <div class="table-responsive">
                        <table class="table table-sm" id="outstandingTable">
                          <thead class="table-light">
                            <tr>
                              <th>School</th>
                              <th>Outstanding</th>
                              <th>Refunds</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <h6>Daily Consumption</h6>
                      <div class="table-responsive">
                        <table class="table table-sm" id="dailyConsumptionTable">
                          <thead class="table-light">
                            <tr>
                              <th>Date</th>
                              <th>School</th>
                              <th>Consumed</th>
                              <th>Rows</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <h5 class="mb-2">Refund Queue</h5>
                  <form id="refundQueueFilterForm" class="row g-3">
                    <div class="col-md-3">
                      <select id="queueSchoolId" class="form-select">
                        <option value="">All Schools</option>
                        <?php foreach ($schools as $school) { ?>
                          <option value="<?php echo (int) $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <select id="queueStatus" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="partially_applied">Partially Applied</option>
                        <option value="applied">Applied</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <input type="date" id="queueCreatedFrom" class="form-control" placeholder="Created from" />
                    </div>
                    <div class="col-md-2">
                      <input type="date" id="queueCreatedTo" class="form-control" placeholder="Created to" />
                    </div>
                    <div class="col-md-3">
                      <div class="input-group">
                        <input type="text" id="queueSourceRef" class="form-control" placeholder="Source ref search" />
                        <button type="submit" class="btn btn-outline-primary">Search</button>
                      </div>
                    </div>
                  </form>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table" id="refundQueueTable">
                      <thead class="table-secondary">
                        <tr>
                          <th>ID</th>
                          <th>School</th>
                          <th>Source Ref</th>
                          <th>Student</th>
                          <th>Amount</th>
                          <th>Remaining</th>
                          <th>Consumed</th>
                          <th>Status</th>
                          <th>Reason</th>
                          <th>Created</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <button type="button" class="btn btn-primary new_formBtn" data-bs-toggle="modal"
              data-bs-target="#newRefundModal" aria-label="Create refund">
              <i class='bx bx-plus fs-3'></i>
            </button>

            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <div class="modal fade" id="newRefundModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Create Refund</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="refundCreateForm" novalidate>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="createSchoolId" class="form-label">School</label>
                  <select id="createSchoolId" name="school_id" class="form-select" required>
                    <option value="">Select School</option>
                    <?php foreach ($schools as $school) { ?>
                      <option value="<?php echo (int) $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="createSourceRefId" class="form-label">Source Ref ID</label>
                  <input type="text" class="form-control" id="createSourceRefId" name="source_ref_id" placeholder="e.g. nivas_123_001" required />
                </div>
                <div class="col-md-4">
                  <label for="createStudentId" class="form-label">Student ID (Optional)</label>
                  <input type="number" min="1" step="1" class="form-control" id="createStudentId" name="student_id" placeholder="e.g. 219" />
                </div>
                <div class="col-md-4">
                  <label for="createAmount" class="form-label">Amount</label>
                  <input type="number" min="0.01" step="0.01" class="form-control" id="createAmount" name="amount" placeholder="0.00" required />
                </div>
                <div class="col-md-8">
                  <label for="createReason" class="form-label">Reason</label>
                  <textarea class="form-control" id="createReason" name="reason" rows="2" maxlength="2000" placeholder="Why is this refund being approved?" required></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" id="createRefundBtn" class="btn btn-primary">Create Refund</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="cancelRefundModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Cancel Refund</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="cancelRefundForm" novalidate>
            <div class="modal-body">
              <p id="cancelRefundTarget" class="text-muted mb-2"></p>
              <label for="cancelRefundReason" class="form-label">Cancellation Reason</label>
              <textarea id="cancelRefundReason" class="form-control" rows="3" maxlength="2000" placeholder="Provide reason for cancellation" required></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" id="cancelRefundBtn" class="btn btn-danger">Cancel Refund</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/ui-toasts.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      window.adminRole = <?php echo $admin_role; ?>;
    </script>
    <script src="model/functions/refunds.js"></script>
  </body>
</html>
