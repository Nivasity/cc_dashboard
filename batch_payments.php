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
    <title>Batch Payments | Nivasity Command Center</title>
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

  <!-- Payment Link Modal -->
  <div class="modal fade" id="paymentLinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Payment Link</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <p class="mb-3">Share this link with the HOC to complete payment.</p>
          <div class="d-flex justify-content-center align-items-center mb-3">
            <a href="#" id="paymentLinkHref" target="_blank" class="text-break fs-5 fw-bold" style="word-break:break-all;">&nbsp;</a>
          </div>
          <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <button type="button" id="copyPaymentLinkBtn" class="btn btn-primary">Copy link</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Payments /</span> Batch Payments</h4>

            <div class="card mb-4">
              <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Create Batch</h5>
              </div>
              <div class="card-body">
                <form id="batchCreateForm" class="row g-3">
                  <div class="col-md-3">
                    <label for="bp_school" class="form-label">School</label>
                    <select id="bp_school" class="form-select" <?php if($admin_role == 5) echo 'disabled'; ?>>
                      <?php if($admin_role != 5) { ?><option value="0">Select School</option><?php } ?>
                      <?php while($school = mysqli_fetch_array($schools_query)) { ?>
                        <option value="<?php echo $school['id']; ?>" <?php if($admin_role == 5) echo 'selected'; ?>><?php echo $school['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="bp_faculty" class="form-label">Faculty</label>
                    <select id="bp_faculty" class="form-select">
                      <?php if(!($admin_role == 5 && $admin_faculty != 0)) { ?><option value="0">All Faculties</option><?php } ?>
                      <?php while($fac = mysqli_fetch_array($faculties_query)) { ?>
                        <option value="<?php echo $fac['id']; ?>" <?php if($admin_role == 5 && $admin_faculty == $fac['id']) echo 'selected'; ?>><?php echo $fac['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="bp_dept" class="form-label">Department</label>
                    <select id="bp_dept" class="form-select">
                      <option value="0">Select Department</option>
                      <?php while($dept = mysqli_fetch_array($depts_query)) { ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="bp_manual" class="form-label">Course Material</label>
                    <select id="bp_manual" class="form-select">
                      <option value="0">Select Material</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <div id="bp_alert" class="alert d-none" role="alert"></div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Price/Student</label>
                    <input type="text" id="bp_price" class="form-control" readonly value="0" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Students</label>
                    <input type="text" id="bp_students" class="form-control" readonly value="0" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Total Amount (Editable)</label>
                    <input type="number" id="bp_total" class="form-control" value="0" min="0" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Batch tx_ref</label>
                    <input type="text" id="bp_txref" class="form-control" readonly />
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-primary" id="bp_submit" disabled>Create Batch</button>
                  </div>
                </form>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">Recent Batches</h5>
              </div>
              <div class="card-body">
                <div class="row g-3 mb-3">
                  <div class="col-md-4">
                    <select id="filter_school" class="form-select" <?php if($admin_role == 5) echo 'disabled'; ?>>
                      <?php if($admin_role != 5) { ?><option value="0">All Schools</option><?php } ?>
                      <?php
                        $school_rs = ($admin_role == 5) ? mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' AND id = $admin_school") : mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
                        while($sc = mysqli_fetch_array($school_rs)) { ?>
                        <option value="<?php echo $sc['id']; ?>" <?php if($admin_role == 5) echo 'selected'; ?>><?php echo $sc['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <select id="filter_faculty" class="form-select"></select>
                  </div>
                  <div class="col-md-4">
                    <select id="filter_dept" class="form-select">
                      <option value="0">All Departments</option>
                    </select>
                  </div>
                </div>
                <div class="table-responsive text-nowrap">
                  <table class="table" id="batchesTable">
                    <thead class="table-secondary">
                      <tr>
                        <th>tx_ref</th>
                        <th>Material</th>
                        <th>Dept</th>
                        <th>School</th>
                        <th>Students</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0"></tbody>
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

  <div class="modal fade" id="batchItemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Batch Items</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table" id="batchItemsTable">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Matric</th>
                  <th>Email</th>
                  <th>Price</th>
                  <th>Ref</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
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
    const adminRole = <?php echo (int)$admin_role; ?>;
    const adminSchool = <?php echo (int)$admin_school; ?>;
    const adminFaculty = <?php echo (int)$admin_faculty; ?>;
  </script>
  <script src="model/functions/batch_payments.js"></script>
</body>
</html>

