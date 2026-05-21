<?php
session_start();
include('model/config.php');
include('model/page_config.php');

function cc_fetch_result_rows($result) {
  $rows = [];
  if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $rows[] = $row;
    }
  }
  return $rows;
}

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

$school_options = cc_fetch_result_rows($schools_query);
$faculty_options = cc_fetch_result_rows($faculties_query);
$dept_options = cc_fetch_result_rows($depts_query);
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
          <p class="mb-3">Share this link with the HOC to complete payment. The checkout amount includes a 2% processing fee.</p>
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
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-3 mb-4">
              <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Payments /</span> Batch Payments</h4>
              <?php if (!in_array((int)$admin_role, [3, 5], true)) { ?>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBatchModal">Create Batch</button>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manualBatchModal">Record Manual Purchase</button>
              </div>
              <?php } ?>
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
                        <th>Source</th>
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
          <div id="batchItemsNotice" class="alert alert-warning d-none" role="alert"></div>
          <div id="batchItemsSummary" class="border rounded p-3 mb-3 d-none">
            <div class="row g-3">
              <div class="col-md-4">
                <small class="text-muted d-block">Reference</small>
                <div id="bis_txref" class="fw-semibold">-</div>
              </div>
              <div class="col-md-4">
                <small class="text-muted d-block">Source</small>
                <div id="bis_source" class="fw-semibold">-</div>
              </div>
              <div class="col-md-4">
                <small class="text-muted d-block">Status</small>
                <div id="bis_status" class="fw-semibold">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Material</small>
                <div id="bis_manual" class="fw-semibold">-</div>
              </div>
              <div class="col-md-3">
                <small class="text-muted d-block">Students</small>
                <div id="bis_students" class="fw-semibold">-</div>
              </div>
              <div class="col-md-3">
                <small class="text-muted d-block">Total</small>
                <div id="bis_total" class="fw-semibold">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Paid By</small>
                <div id="bis_paid_by" class="fw-semibold">-</div>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Created</small>
                <div id="bis_created" class="fw-semibold">-</div>
              </div>
              <div class="col-12">
                <small class="text-muted d-block">Reason</small>
                <div id="bis_reason" class="fw-semibold">-</div>
              </div>
              <div class="col-12 d-none" id="bis_receipt_wrap">
                <small class="text-muted d-block">Receipt</small>
                <a href="#" id="bis_receipt_link" target="_blank" rel="noopener">View uploaded receipt</a>
              </div>
            </div>
          </div>
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

  <?php if (!in_array((int)$admin_role, [3, 5], true)) { ?>
  <div class="modal fade" id="createBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create Batch</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="batchCreateForm" class="row g-3" enctype="multipart/form-data">
            <div class="col-md-3">
              <label for="bp_school" class="form-label">School</label>
              <select id="bp_school" class="form-select" <?php if($admin_role == 5) echo 'disabled'; ?>>
                <?php if($admin_role != 5) { ?><option value="0">Select School</option><?php } ?>
                <?php foreach($school_options as $school) { ?>
                  <option value="<?php echo $school['id']; ?>" <?php if($admin_role == 5) echo 'selected'; ?>><?php echo $school['name']; ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="bp_faculty" class="form-label">Faculty</label>
              <select id="bp_faculty" class="form-select">
                <?php if(!($admin_role == 5 && $admin_faculty != 0)) { ?><option value="0">All Faculties</option><?php } ?>
                <?php foreach($faculty_options as $fac) { ?>
                  <option value="<?php echo $fac['id']; ?>" <?php if($admin_role == 5 && $admin_faculty == $fac['id']) echo 'selected'; ?>><?php echo $fac['name']; ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="bp_dept" class="form-label">Department</label>
              <select id="bp_dept" class="form-select">
                <option value="0">Select Department</option>
                <?php foreach($dept_options as $dept) { ?>
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
              <div id="bp_alert" class="alert d-none mb-0" role="alert"></div>
            </div>
            <div class="col-md-6">
              <label for="bp_students_file" class="form-label">Students CSV</label>
              <input type="file" id="bp_students_file" name="students_csv" class="form-control" accept=".csv,text/csv" />
              <div class="form-text">Upload a CSV containing matric numbers. The first non-empty value on each row is used, duplicates are ignored, and unmatched matric numbers are stored without a linked student record.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">School Settlement Mode</label>
              <div class="form-control bg-light">Internal ledger settlement</div>
              <div class="form-text">The batch subtotal will be recorded into <code>school_payable_ledger</code> after payment verification. The 2% checkout fee remains outside the school subtotal.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Price/Student</label>
              <input type="text" id="bp_price" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Students in CSV</label>
              <input type="text" id="bp_students" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Matched</label>
              <input type="text" id="bp_matched" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Unmatched</label>
              <input type="text" id="bp_unmatched" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Total Amount (Editable)</label>
              <input type="number" id="bp_total" class="form-control" value="0" min="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Batch tx_ref</label>
              <input type="text" id="bp_txref" class="form-control" readonly />
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" id="bp_submit" disabled>Create Batch</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="manualBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Record Manual Material Purchase</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="manualBatchForm" class="row g-3" enctype="multipart/form-data">
            <div class="col-md-3">
              <label for="mb_school" class="form-label">School</label>
              <select id="mb_school" class="form-select" <?php if($admin_role == 5) echo 'disabled'; ?>>
                <?php if($admin_role != 5) { ?><option value="0">Select School</option><?php } ?>
                <?php foreach($school_options as $school) { ?>
                  <option value="<?php echo $school['id']; ?>" <?php if($admin_role == 5) echo 'selected'; ?>><?php echo $school['name']; ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="mb_faculty" class="form-label">Faculty</label>
              <select id="mb_faculty" class="form-select">
                <?php if(!($admin_role == 5 && $admin_faculty != 0)) { ?><option value="0">All Faculties</option><?php } ?>
                <?php foreach($faculty_options as $fac) { ?>
                  <option value="<?php echo $fac['id']; ?>" <?php if($admin_role == 5 && $admin_faculty == $fac['id']) echo 'selected'; ?>><?php echo $fac['name']; ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="mb_dept" class="form-label">Department</label>
              <select id="mb_dept" class="form-select">
                <option value="0">Select Department</option>
                <?php foreach($dept_options as $dept) { ?>
                  <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="mb_manual" class="form-label">Course Material</label>
              <select id="mb_manual" class="form-select">
                <option value="0">Select Material</option>
              </select>
            </div>
            <div class="col-12">
              <div id="mb_alert" class="alert d-none mb-0" role="alert"></div>
            </div>
            <div class="col-md-6">
              <label for="mb_students_file" class="form-label">Students CSV</label>
              <input type="file" id="mb_students_file" name="students_csv" class="form-control" accept=".csv,text/csv" />
              <div class="form-text">Upload a CSV with <code>first_name</code>, <code>last_name</code>, and <code>matric_no</code> columns. Duplicate matric numbers are ignored.</div>
            </div>
            <div class="col-md-6">
              <label for="mb_receipt_file" class="form-label">Payment Receipt</label>
              <input type="file" id="mb_receipt_file" name="payment_receipt" class="form-control" accept="image/*,.pdf,application/pdf" />
              <div class="form-text">Upload the external receipt as an image or PDF. Manual records do not create a Nivasity transaction and are not remitted into <code>school_payable_ledger</code>.</div>
            </div>
            <div class="col-md-6">
              <label for="mb_paid_by_name" class="form-label">Paid By Name</label>
              <input type="text" id="mb_paid_by_name" class="form-control" placeholder="Enter the payer's full name" />
            </div>
            <div class="col-md-6">
              <label for="mb_paid_by_phone" class="form-label">Paid By Phone Number</label>
              <input type="tel" id="mb_paid_by_phone" class="form-control" placeholder="e.g. 08012345678" />
            </div>
            <div class="col-12">
              <label for="mb_payment_reason" class="form-label">Reason</label>
              <textarea id="mb_payment_reason" class="form-control" rows="3" placeholder="Why was this payment recorded manually?"></textarea>
            </div>
            <div class="col-md-2">
              <label class="form-label">Price/Student</label>
              <input type="text" id="mb_price" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Students in CSV</label>
              <input type="text" id="mb_students" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Matched</label>
              <input type="text" id="mb_matched" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Unmatched</label>
              <input type="text" id="mb_unmatched" class="form-control" readonly value="0" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Recorded Subtotal</label>
              <input type="text" id="mb_total" class="form-control" readonly value="0" />
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" id="mb_submit" disabled>Record Manual Purchase</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>

  <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
  <script src="assets/vendor/js/bootstrap.min.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/vendor/libs/popper/popper.min.js"></script>
  <script src="assets/vendor/js/menu.min.js"></script>
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
