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
  $material_sql = "SELECT m.id, m.code, m.title, m.course_code, m.price, m.due_date, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, CASE WHEN m.due_date < NOW() THEN 'closed' ELSE m.status END AS status, m.status AS db_status, CASE WHEN m.due_date < NOW() THEN 1 ELSE 0 END AS due_passed, u.first_name, u.last_name, u.matric_no FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN users u ON m.user_id = u.id LEFT JOIN depts d ON m.dept = d.id WHERE m.school_id = $admin_school";
  if ($admin_faculty != 0) {
    $material_sql .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
  $material_sql .= " GROUP BY m.id ORDER BY m.created_at DESC";
} else {
  $schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
  $faculties_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
  $depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
  $material_sql = "SELECT m.id, m.code, m.title, m.course_code, m.price, m.due_date, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, CASE WHEN m.due_date < NOW() THEN 'closed' ELSE m.status END AS status, m.status AS db_status, CASE WHEN m.due_date < NOW() THEN 1 ELSE 0 END AS due_passed, u.first_name, u.last_name, u.matric_no FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN users u ON m.user_id = u.id LEFT JOIN depts d ON m.dept = d.id GROUP BY m.id ORDER BY m.created_at DESC";
}
$materials_query = mysqli_query($conn, $material_sql);
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
            <div class="card mb-4">
              <div class="card-body">
                <form id="filterForm" class="row g-3 mb-4">
                  <div class="col-md-4">
                    <select name="school" id="school" class="form-select" <?php if($admin_role == 5) echo 'disabled'; ?>>
                      <?php if($admin_role != 5) { ?>
                        <option value="0">All Schools</option>
                      <?php } ?>
                      <?php while($school = mysqli_fetch_array($schools_query)) { ?>
                        <option value="<?php echo $school['id']; ?>" <?php if($admin_role == 5) echo 'selected'; ?>><?php echo $school['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <select name="faculty" id="faculty" class="form-select" <?php if($admin_role == 5 && $admin_faculty != 0) echo 'disabled'; ?>>
                      <?php if(!($admin_role == 5 && $admin_faculty != 0)) { ?>
                        <option value="0">All Faculties</option>
                      <?php } ?>
                      <?php while($fac = mysqli_fetch_array($faculties_query)) { ?>
                        <option value="<?php echo $fac['id']; ?>" <?php if($admin_role == 5 && $admin_faculty == $fac['id']) echo 'selected'; ?>><?php echo $fac['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <select name="dept" id="dept" class="form-select">
                      <option value="0">All Departments</option>
                      <?php while($dept = mysqli_fetch_array($depts_query)) { ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                      <?php } ?>
                    </select>
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
                      <?php while($mat = mysqli_fetch_array($materials_query)) { ?>
                      <tr>
                        <td class="text-uppercase">#<?php echo htmlspecialchars($mat['code']); ?></td>
                        <td class="text-uppercase"><strong><?php echo $mat['title'].' ('.$mat['course_code'].')'; ?></strong></td>
                        <td>
                          <span class="text-uppercase text-primary"><?php echo trim(($mat['first_name'] ?? '').' '.($mat['last_name'] ?? '')); ?></span>
                          <?php if(!empty($mat['matric_no'])) { ?>
                            <br>Matric no: <?php echo $mat['matric_no']; ?>
                          <?php } ?>
                        </td>
                        <td>₦ <?php echo number_format($mat['price']); ?></td>
                        <td>₦ <?php echo number_format($mat['revenue']); ?></td>
                        <td><?php echo $mat['qty_sold']; ?></td>
                        <td><span class="fw-bold badge bg-label-<?php echo $mat['status']=='open' ? 'success' : 'danger'; ?>"><?php echo ucfirst($mat['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($mat['due_date'])); ?></td>
                        <td>
                          <div class="dropstart">
                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">
                              <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu">
                              <?php if($mat['db_status']=='open' && !$mat['due_passed']) { ?>
                              <a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="<?php echo $mat['id']; ?>" data-status="<?php echo $mat['db_status']; ?>">
                                <i class="bx bx-lock me-1"></i> Close Material
                              </a>
                              <?php } ?>
                              <a href="javascript:void(0);" class="dropdown-item downloadMaterialTransactions" data-id="<?php echo (int)$mat['id']; ?>" data-code="<?php echo htmlspecialchars($mat['code']); ?>">
                                <i class="bx bx-download me-1"></i> Download transactions list
                              </a>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <?php } ?>
                    </tbody>
                  </table>
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
          <h5 class="modal-title">Add New Course Material</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="newMaterialForm" novalidate>
          <div class="modal-body overflow-auto">
            <div id="newMaterialAlert" class="alert d-none" role="alert"></div>
            
            <div class="mb-3">
              <label for="materialSchool" class="form-label">School <span class="text-danger">*</span></label>
              <select id="materialSchool" name="school" class="form-select" required <?php if($admin_role == 5) echo 'disabled'; ?>>
                <?php 
                mysqli_data_seek($schools_query, 0);
                if($admin_role != 5) { ?>
                  <option value="">Select School</option>
                <?php } 
                while($school = mysqli_fetch_array($schools_query)) { ?>
                  <option value="<?php echo $school['id']; ?>" <?php if($admin_role == 5) echo 'selected'; ?>><?php echo $school['name']; ?></option>
                <?php } ?>
              </select>
              <?php if($admin_role == 5) { ?>
                <input type="hidden" name="school" value="<?php echo $admin_school; ?>">
              <?php } ?>
            </div>

            <div class="mb-3">
              <label for="materialFaculty" class="form-label">Faculty <span class="text-danger">*</span></label>
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
              <?php if($admin_role == 5 && $admin_faculty != 0) { ?>
                <input type="hidden" name="faculty" value="<?php echo $admin_faculty; ?>">
              <?php } ?>
            </div>

            <div class="mb-3">
              <label for="materialDept" class="form-label">Department</label>
              <select id="materialDept" name="dept" class="form-select">
                <option value="0">All Departments</option>
                <?php 
                mysqli_data_seek($depts_query, 0);
                while($dept = mysqli_fetch_array($depts_query)) { ?>
                  <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                <?php } ?>
              </select>
              <div class="form-text">Select a specific department or leave as "All Departments"</div>
            </div>

            <div class="mb-3">
              <label for="materialTitle" class="form-label">Course Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="materialTitle" name="title" required placeholder="e.g., Introduction to Computer Science">
            </div>

            <div class="mb-3">
              <label for="materialCourseCode" class="form-label">Course Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="materialCourseCode" name="course_code" required placeholder="e.g., CSC101">
            </div>

            <div class="mb-3">
              <label for="materialPrice" class="form-label">Price (₦) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" id="materialPrice" name="price" required min="0" step="1" placeholder="0">
            </div>

            <div class="mb-3">
              <label for="materialDueDate" class="form-label">Due Date <span class="text-danger">*</span></label>
              <input type="datetime-local" class="form-control" id="materialDueDate" name="due_date" required>
              <div class="form-text">Set the deadline for this material</div>
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
