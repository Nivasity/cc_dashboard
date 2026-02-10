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
  <title>Course Materials | Nivasity Command Center</title>
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources Management /</span> Course Materials</h4>
            <div class="card mb-4" id="materialsCard">
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
                    <select name="faculty" id="faculty" class="form-select" <?php if($admin_role == 5 && $admin_faculty != 0) echo 'disabled'; ?>>
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
                    <button type="button" id="downloadMaterials" class="btn btn-success ms-2">Download CSV</button>
                  </div>
                </form>
                <div class="table-responsive text-nowrap">
                  <table class="table">
                    <thead class="table-secondary">
                      <tr>
                        <th>#Code</th>
                        <th>Title (Course Code)</th>
                        <th>Posted By</th>
                        <th>Unit Price</th>
                        <th>Revenue</th>
                        <th>Qty Sold</th>
                        <th>Availability</th>
                        <th>Due Date</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                      <!-- Table rows will be populated by JavaScript via fetchMaterials() -->
                    </tbody>
                  </table>
                </div>
                <div class="stats-loading-spinner">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-primary new_formBtn" data-bs-toggle="modal"
            data-bs-target="#newMaterialModal" aria-label="Add new material">
            <i class='bx bx-plus fs-3'></i>
          </button>
          <?php include('partials/_footer.php') ?>
          <div class="content-backdrop fade"></div>
        </div>
      </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- Core JS -->
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>
  <!-- Main JS -->
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>
  <script src="model/functions/materials.js"></script>
  <script>
    // Expose admin context to JS files
    window.adminRole = <?php echo (int)$admin_role; ?>;
    window.adminSchool = <?php echo (int)$admin_school; ?>;
    window.adminFaculty = <?php echo (int)$admin_faculty; ?>;
  </script>

  <!-- New Material Modal -->
  <div class="modal fade" id="newMaterialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="materialModalTitle">Add New Course Material</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="newMaterialForm" novalidate>
          <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
            <div id="newMaterialAlert" class="alert d-none" role="alert"></div>
            <input type="hidden" id="materialId" name="material_id" value="">
            
            <div class="mb-3">
              <label for="materialSchool" class="form-label">School <span class="text-danger">*</span></label>
              <select id="materialSchool" name="school" class="form-select" required <?php if($admin_role == 5) echo 'disabled'; ?>>
                <?php 
                mysqli_data_seek($schools_query, 0);
                if($admin_role != 5) { ?>
                  <option value="">Select School</option>
                <?php } 
                while($school = mysqli_fetch_array($schools_query)) { ?>
                  <option value="<?php echo $school['id']; ?>" <?php if($admin_role == 5 && $school['id'] == $admin_school) echo 'selected'; ?>><?php echo $school['name']; ?></option>
                <?php } ?>
              </select>
              <?php if($admin_role == 5) { ?>
                <input type="hidden" name="school" value="<?php echo $admin_school; ?>">
              <?php } ?>
            </div>

            <!-- Faculty Host and Faculty (2-column grid on desktop) -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="materialHostFaculty" class="form-label">Faculty Host <span class="text-danger">*</span></label>
                <select id="materialHostFaculty" name="host_faculty" class="form-select" required <?php if($admin_role == 5 && $admin_faculty != 0) echo 'disabled'; ?>>
                  <?php 
                  mysqli_data_seek($faculties_query, 0);
                  if(!($admin_role == 5 && $admin_faculty != 0)) { ?>
                    <option value="">Select Faculty Host</option>
                  <?php } 
                  while($fac = mysqli_fetch_array($faculties_query)) { ?>
                    <option value="<?php echo $fac['id']; ?>" <?php if($admin_role == 5 && $admin_faculty == $fac['id']) echo 'selected'; ?>><?php echo $fac['name']; ?></option>
                  <?php } ?>
                </select>
                <div class="form-text">Faculty hosting this material</div>
                <?php if($admin_role == 5 && $admin_faculty != 0) { ?>
                  <input type="hidden" name="host_faculty" value="<?php echo $admin_faculty; ?>">
                <?php } ?>
              </div>
              <div class="col-md-6">
                <label for="materialFaculty" class="form-label">Faculty (Who Can Buy) <span class="text-danger">*</span></label>
                <select id="materialFaculty" name="faculty" class="form-select" required <?php if($admin_role == 5 && $admin_faculty != 0) echo 'disabled'; ?>>
                  <?php 
                  mysqli_data_seek($faculties_query, 0);
                  if(!($admin_role == 5 && $admin_faculty != 0)) { ?>
                    <option value="">Select Faculty</option>
                  <?php } 
                  while($fac = mysqli_fetch_array($faculties_query)) { ?>
                    <option value="<?php echo $fac['id']; ?>" <?php if($admin_role == 5 && $admin_faculty == $fac['id']) echo 'selected'; ?>><?php echo $fac['name']; ?></option>
                  <?php } ?>
                </select>
                <div class="form-text">Students from this faculty can buy</div>
                <?php if($admin_role == 5 && $admin_faculty != 0) { ?>
                  <input type="hidden" name="faculty" value="<?php echo $admin_faculty; ?>">
                <?php } ?>
              </div>
            </div>

            <!-- Department and Level (2-column grid on desktop) -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="materialDept" class="form-label">Department</label>
                <select id="materialDept" name="dept" class="form-select">
                  <option value="0">All Departments</option>
                  <?php 
                  mysqli_data_seek($depts_query, 0);
                  while($dept = mysqli_fetch_array($depts_query)) { ?>
                    <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                  <?php } ?>
                </select>
                <div class="form-text">Select specific or "All Departments"</div>
              </div>
              <div class="col-md-6">
                <label for="materialLevel" class="form-label">Level</label>
                <select id="materialLevel" name="level" class="form-select">
                  <option value="">All Levels</option>
                  <option value="100">100 Level</option>
                  <option value="200">200 Level</option>
                  <option value="300">300 Level</option>
                  <option value="400">400 Level</option>
                  <option value="500">500 Level</option>
                  <option value="600">600 Level</option>
                  <option value="700">700 Level</option>
                </select>
                <div class="form-text">Select specific level or "All Levels"</div>
              </div>
            </div>

            <!-- Course Title and Course Code (2-column grid on desktop) -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="materialTitle" class="form-label">Course Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="materialTitle" name="title" required placeholder="e.g., Introduction to Computer Science">
              </div>
              <div class="col-md-6">
                <label for="materialCourseCode" class="form-label">Course Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="materialCourseCode" name="course_code" required placeholder="e.g., CSC101">
              </div>
            </div>

            <!-- Price and Due Date (2-column grid on desktop) -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="materialPrice" class="form-label">Price (₦) <span class="text-danger">*</span></label>
                <!-- Client-side validation: min='0' - Backend validates price is a non-negative integer -->
                <input type="number" class="form-control" id="materialPrice" name="price" required min="0" step="1" placeholder="0">
              </div>
              <div class="col-md-6">
                <label for="materialDueDate" class="form-label">Due Date <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" id="materialDueDate" name="due_date" required>
                <div class="form-text">Set the deadline for this material</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="newMaterialSubmit">Create Material</button>
          </div>
        </form>
      </div>
    </div>
  </div>

</body>
</html>
