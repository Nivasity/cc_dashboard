<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = $admin_['school'];
$admin_faculty = $admin_['faculty'] ?? 0;

if ($admin_role == 5) {
  $schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' AND id = $admin_school");
  if ($admin_faculty != 0) {
    $faculties_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND id = $admin_faculty");
    $depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $admin_faculty ORDER BY name");
  } else {
    $faculties_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
    $depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
  }
} else {
  $schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
  $faculties_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
  $depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Transactions | Nivasity Command Center</title>
    <meta name="description" content="" />
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Payments /</span> Transactions</h4>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
              <div class="col-lg-4 col-md-6 mb-4">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-start">
                      <div class="avatar flex-shrink-0 me-3">
                        <i class='bx bx-list-ul bx-sm'></i>
                      </div>
                      <div class="flex-grow-1">
                        <span class="fw-semibold d-block mb-1">Total Transaction Count</span>
                        <h3 class="card-title mb-1" id="totalCount">0</h3>
                        <small class="text-success" id="countChange"></small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 col-md-6 mb-4">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-start">
                      <div class="avatar flex-shrink-0 me-3">
                        <i class='bx bx-wallet bx-sm'></i>
                      </div>
                      <div class="flex-grow-1">
                        <span class="fw-semibold d-block mb-1">Total Sum</span>
                        <h3 class="card-title mb-1" id="totalSum">₦ 0</h3>
                        <small class="text-success" id="sumChange"></small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 col-md-6 mb-4">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-start">
                      <div class="avatar flex-shrink-0 me-3">
                        <i class='bx bx-calculator bx-sm'></i>
                      </div>
                      <div class="flex-grow-1">
                        <span class="fw-semibold d-block mb-1">Commonly Paid</span>
                        <h3 class="card-title mb-1" id="averagePaid">₦ 0</h3>
                        <small class="text-primary" id="modeFrequency"></small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card mb-4">
              <div class="card-body">
                <form id="filterForm" class="row g-3 mb-4">
                  <div class="col-md-3">
                    <select name="school" id="school" class="form-select" <?php if($admin_role == 5) echo 'disabled'; ?>>
                      <?php if($admin_role != 5) { ?>
                        <option value="0">All Schools</option>
                      <?php } ?>
                      <?php while($school = mysqli_fetch_array($schools_query)) { ?>
                        <option value="<?php echo $school['id']; ?>" <?php if($admin_role == 5) echo 'selected'; ?>><?php echo $school['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select name="faculty" id="faculty" class="form-select">
                      <?php if(!($admin_role == 5 && $admin_faculty != 0)) { ?>
                        <option value="0">All Faculties</option>
                      <?php } ?>
                      <?php while($fac = mysqli_fetch_array($faculties_query)) { ?>
                        <option value="<?php echo $fac['id']; ?>" <?php if($admin_role == 5 && $admin_faculty == $fac['id']) echo 'selected'; ?>><?php echo $fac['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select name="dept" id="dept" class="form-select">
                      <option value="0">All Departments</option>
                      <?php while($dept = mysqli_fetch_array($depts_query)) { ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select name="date_range" id="dateRange" class="form-select">
                      <option value="7" selected>Last 7 Days</option>
                      <option value="30">Last 30 Days</option>
                      <option value="90">Last 90 Days</option>
                      <option value="all">All Time</option>
                      <option value="custom">Custom Range</option>
                    </select>
                  </div>
                  <div class="col-md-6 d-none" id="customDateRange">
                    <div class="row g-2">
                      <div class="col-md-6">
                        <input type="date" class="form-control" id="startDate" name="start_date" placeholder="Start Date">
                      </div>
                      <div class="col-md-6">
                        <input type="date" class="form-control" id="endDate" name="end_date" placeholder="End Date">
                      </div>
                    </div>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-secondary">Search</button>
                    <button type="button" id="downloadCsv" class="btn btn-success ms-2">Download CSV</button>
                  </div>
                </form>
                <div class="table-responsive text-nowrap">
                  <table class="table">
                    <thead class="table-secondary">
                      <tr>
                        <th>Ref. Id</th>
                        <th>Student Details</th>
                        <th>Course materials</th>
                        <th>Total Paid</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                        <?php if (in_array((int)$admin_role, [1, 2, 4], true)) { ?>
                          <th>Action</th>
                        <?php } ?>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php if ((int)$admin_role !== 5) { ?>
          <button type="button" class="btn btn-primary new_formBtn" data-bs-toggle="modal"
            data-bs-target="#manualTransactionModal" aria-label="Add manual transaction">
            <i class='bx bx-plus fs-3'></i>
          </button>
          <?php } ?>
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
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    // Expose admin context to JS files
    window.adminRole = <?php echo (int)$admin_role; ?>;
    window.adminSchool = <?php echo (int)$admin_school; ?>;
    window.adminFaculty = <?php echo (int)$admin_faculty; ?>;
  </script>
    <div class="modal fade" id="manualTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Manual Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="manualTransactionForm" novalidate>
          <div class="modal-body overflow-auto">
            <div id="manualTransactionAlert" class="alert d-none" role="alert"></div>
            <div class="mb-3">
              <label for="manualTransactionStatus" class="form-label">Transaction Status</label>
              <select id="manualTransactionStatus" name="status" class="form-select" required>
                <option value="successful" selected>Successful</option>
                <option value="refunded">Refunded</option>
              </select>
              <div class="form-text">Choose "Refunded" to record a refund without materials.</div>
            </div>
            <div class="mb-4">
              <label for="manualUserEmail" class="form-label">User Email</label>
              <input type="email" class="form-control" id="manualUserEmail" name="email" placeholder="student@example.com"
                required autocomplete="off" />
              <div class="form-text">Enter the student's email to load their profile.</div>
              <div id="manualUserFeedback" class="mt-3 d-none" aria-live="polite"></div>
              <div id="manualUserDetails" class="mt-3"></div>
            </div>
            <div class="mb-4 d-none" id="manualRefundAmountGroup">
              <label for="manualRefundAmount" class="form-label">Amount (₦)</label>
              <input type="number" class="form-control" id="manualRefundAmount" name="amount" min="0" step="1" placeholder="0" />
              <div class="form-text">For refunds, enter the total amount to record.</div>
            </div>
            <div class="mb-4">
              <label for="manualMaterialSelect" class="form-label">Course Materials</label>
              <select id="manualMaterialSelect" name="manuals[]" class="form-select" multiple required>
              </select>
              <div class="form-text">Select one or more materials. Each option shows the title, course code, manual code and ID.</div>
              <div id="manualMaterialSummary" class="mt-2 text-muted"></div>
            </div>
            <div class="mb-3">
              <label for="manualTransactionRef" class="form-label">Transaction Reference</label>
              <input type="text" class="form-control" id="manualTransactionRef" name="transaction_ref"
                placeholder="e.g. nivas_4_001" required autocomplete="off" />
              <div class="form-text">Reference must be unique.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="manualTransactionSubmit" disabled>Save Transaction</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="deleteTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Delete Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p id="deleteTransactionMessage">
            Are you sure you want to delete this transaction? This will also remove all related course material purchase records.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDeleteTransaction">Delete</button>
        </div>
      </div>
    </div>
  </div>

  <script src="model/functions/transactions.js"></script>
</body>
</html>
