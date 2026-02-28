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

$refund_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($refund_id <= 0) {
  header('Location: refunds.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Refund Detail | Nivasity Command Center</title>
    <meta name="description" content="Refund split ledger detail" />
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
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Finances / Refunds /</span> Detail</h4>
                <a href="refunds.php" class="btn btn-outline-primary">Back to Queue</a>
              </div>

              <div class="card mb-4">
                <div class="card-header">
                  <h5 class="mb-0">Refund Summary</h5>
                </div>
                <div class="card-body" id="refundSummary">
                  <div class="row g-3">
                    <div class="col-md-3"><span class="text-muted d-block">Refund ID</span><span id="summaryId">-</span></div>
                    <div class="col-md-3"><span class="text-muted d-block">School</span><span id="summarySchool">-</span></div>
                    <div class="col-md-3"><span class="text-muted d-block">Student</span><span id="summaryStudent">-</span></div>
                    <div class="col-md-3"><span class="text-muted d-block">Source Ref ID</span><span id="summaryRefId">-</span></div>
                    <div class="col-md-3"><span class="text-muted d-block">Status</span><span id="summaryStatus">-</span></div>
                    <div class="col-md-3"><span class="text-muted d-block">Amount</span><span id="summaryAmount">-</span></div>
                    <div class="col-md-3"><span class="text-muted d-block">Remaining</span><span id="summaryRemaining">-</span></div>
                    <div class="col-md-3"><span class="text-muted d-block">Consumed</span><span id="summaryConsumed">-</span></div>
                    <div class="col-md-12"><span class="text-muted d-block">Reason</span><span id="summaryReason">-</span></div>
                  </div>
                </div>
              </div>

              <div class="row mb-4" id="totalsCards">
                <div class="col-md-3 mb-3">
                  <div class="border rounded p-3 h-100">
                    <span class="text-muted d-block">Total Reserved</span>
                    <h5 class="mb-0" id="totalReserved">0.00</h5>
                  </div>
                </div>
                <div class="col-md-3 mb-3">
                  <div class="border rounded p-3 h-100">
                    <span class="text-muted d-block">Total Consumed</span>
                    <h5 class="mb-0" id="totalConsumed">0.00</h5>
                  </div>
                </div>
                <div class="col-md-3 mb-3">
                  <div class="border rounded p-3 h-100">
                    <span class="text-muted d-block">Total Released</span>
                    <h5 class="mb-0" id="totalReleased">0.00</h5>
                  </div>
                </div>
                <div class="col-md-3 mb-3">
                  <div class="border rounded p-3 h-100">
                    <span class="text-muted d-block">Outstanding</span>
                    <h5 class="mb-0" id="totalOutstanding">0.00</h5>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <h5 class="mb-0">Split Ledger</h5>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table" id="refundLedgerTable">
                      <thead class="table-secondary">
                        <tr>
                          <th>Split #</th>
                          <th>Reservation Ref ID</th>
                          <th>Amount</th>
                          <th>Status</th>
                          <th>Reserved At</th>
                          <th>Consumed At</th>
                          <th>Released At</th>
                          <th>Release Reason</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
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

    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/ui-toasts.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      window.refundId = <?php echo $refund_id; ?>;
    </script>
    <script src="model/functions/refund_detail.js"></script>
  </body>
</html>
