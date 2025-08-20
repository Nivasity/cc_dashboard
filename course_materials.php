<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$selected_school = isset($_GET['school']) ? intval($_GET['school']) : 0;
$selected_faculty = isset($_GET['faculty']) ? intval($_GET['faculty']) : 0;
$selected_dept = isset($_GET['dept']) ? intval($_GET['dept']) : 0;

if(isset($_GET['toggle_id'])){
  $toggle_id = intval($_GET['toggle_id']);
  $status_res = mysqli_fetch_array(mysqli_query($conn, "SELECT status FROM manuals WHERE id = $toggle_id"));
  if($status_res){
    $new_status = ($status_res[0] == 'open') ? 'close' : 'open';
    mysqli_query($conn, "UPDATE manuals SET status = '$new_status' WHERE id = $toggle_id");
  }
}

$schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
$faculties_query = ($selected_school > 0) ? mysqli_query($conn, "SELECT id, name FROM faculties WHERE school_id = $selected_school AND status = 'active' ORDER BY name") : mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
$depts_query = 0;
if($selected_faculty > 0){
  $depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE faculty_id = $selected_faculty AND status = 'active' ORDER BY name");
} elseif($selected_school > 0) {
  $depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE school_id = $selected_school AND status = 'active' ORDER BY name");
} else {
  $depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
}

$material_sql = "SELECT m.*, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
if($selected_school > 0){
  $material_sql .= " AND m.school_id = $selected_school";
}
if($selected_faculty > 0){
  $material_sql .= " AND d.faculty_id = $selected_faculty";
}
if($selected_dept > 0){
  $material_sql .= " AND m.dept = $selected_dept";
}
$material_sql .= " GROUP BY m.id ORDER BY m.created_at DESC";
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
                <form method="GET" class="row g-3 mb-4">
                  <div class="col-md-4">
                    <select name="school" id="school" class="form-select">
                      <option value="0">All Schools</option>
                      <?php while($school = mysqli_fetch_array($schools_query)) { ?>
                        <option value="<?php echo $school['id']; ?>" <?php echo $school['id']==$selected_school ? 'selected' : ''; ?>><?php echo $school['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <select name="faculty" id="faculty" class="form-select">
                      <option value="0">All Faculties</option>
                      <?php while($fac = mysqli_fetch_array($faculties_query)) { ?>
                        <option value="<?php echo $fac['id']; ?>" <?php echo $fac['id']==$selected_faculty ? 'selected' : ''; ?>><?php echo $fac['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <select name="dept" id="dept" class="form-select">
                      <option value="0">All Departments</option>
                      <?php while($dept = mysqli_fetch_array($depts_query)) { ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $dept['id']==$selected_dept ? 'selected' : ''; ?>><?php echo $dept['name']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-secondary">Search</button>
                  </div>
                </form>
                <div class="table-responsive text-nowrap">
                  <table class="table">
                    <thead class="table-secondary">
                      <tr>
                        <th>Title (Course Code)</th>
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
                        <td class="text-uppercase"><?php echo $mat['title'].' ('.$mat['course_code'].')'; ?></td>
                        <td><?php echo $mat['currency'].' '.number_format($mat['price']); ?></td>
                        <td><?php echo $mat['currency'].' '.number_format($mat['revenue']); ?></td>
                        <td><?php echo $mat['qty_sold']; ?></td>
                        <td><span class="fw-bold badge bg-label-<?php echo $mat['status']=='open' ? 'success' : 'danger'; ?>"><?php echo ucfirst($mat['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($mat['due_date'])); ?></td>
                        <td>
                          <a href="course_materials.php?toggle_id=<?php echo $mat['id']; ?>&school=<?php echo $selected_school; ?>&faculty=<?php echo $selected_faculty; ?>&dept=<?php echo $selected_dept; ?>" class="dropdown-item">
                            <?php echo $mat['status']=='open' ? 'Close Material' : 'Open Material'; ?>
                          </a>
                        </td>
                      </tr>
                      <?php } ?>
                    </tbody>
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
</body>
</html>
