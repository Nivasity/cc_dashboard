<?php
session_start();
include('config.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = '';
$faculties = $departments = $materials = null;
$restrict_faculty = false;

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = $admin_faculty = 0;
if ($admin_role == 5 && $admin_id) {
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
  $admin_school = $info['school'];
  $admin_faculty = $info['faculty'];
}

// Handle CSV download for filtered materials
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  if ($admin_role == 5) {
    $school = $admin_school;
    if ($admin_faculty != 0) { $faculty = $admin_faculty; }
  }

  $material_sql = "SELECT m.id, m.title, m.course_code, m.price, m.due_date, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, CASE WHEN m.due_date < NOW() THEN 'closed' ELSE m.status END AS status, m.status AS db_status, CASE WHEN m.due_date < NOW() THEN 1 ELSE 0 END AS due_passed FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
  if ($school > 0) {
    $material_sql .= " AND m.school_id = $school";
  }
  if ($faculty != 0) {
    $material_sql .= " AND d.faculty_id = $faculty";
  }
  if ($dept > 0) {
    $material_sql .= " AND m.dept = $dept";
  }
  $material_sql .= " GROUP BY m.id ORDER BY m.created_at DESC";
  $mat_query = mysqli_query($conn, $material_sql);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="materials_' . date('Ymd_His') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Title (Course Code)', 'Unit Price', 'Revenue', 'Qty Sold', 'Availability', 'Due Date']);
  while ($row = mysqli_fetch_assoc($mat_query)) {
    fputcsv($out, [
      $row['title'] . ' (' . $row['course_code'] . ')',
      $row['price'],
      $row['revenue'],
      $row['qty_sold'],
      $row['status'],
      date('M d, Y', strtotime($row['due_date']))
    ]);
  }
  fclose($out);
  exit;
}

if(isset($_GET['fetch'])){
  $fetch = $_GET['fetch'];
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  if ($admin_role == 5) {
    $school = $admin_school;
    if ($admin_faculty != 0) {
      $faculty = $admin_faculty;
    }
  }

  if($fetch == 'faculties'){
    if($admin_role == 5){
      if($admin_faculty != 0){
        $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND id = $admin_faculty");
        $restrict_faculty = true;
      } else {
        $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
      }
    } else {
      $fac_query = ($school > 0) ?
        mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $school ORDER BY name") :
        mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
    }
    $faculties = array();
    while($row = mysqli_fetch_assoc($fac_query)){
      $faculties[] = $row;
    }
    $statusRes = 'success';
  }

  if($fetch == 'departments'){
    if($admin_role == 5){
      if($admin_faculty != 0){
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $admin_faculty ORDER BY name");
      } elseif($faculty != 0){
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $faculty AND school_id = $admin_school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
      }
    } else {
      if($faculty != 0){
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $faculty ORDER BY name");
      } elseif($school > 0){
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
      }
    }
    $departments = array();
    while($row = mysqli_fetch_assoc($dept_query)){
      $departments[] = $row;
    }
    $statusRes = 'success';
  }

  if($fetch == 'materials'){
    $material_sql = "SELECT m.id, m.title, m.course_code, m.price, m.due_date, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, CASE WHEN m.due_date < NOW() THEN 'closed' ELSE m.status END AS status, m.status AS db_status, CASE WHEN m.due_date < NOW() THEN 1 ELSE 0 END AS due_passed FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
    if($admin_role == 5){
      $material_sql .= " AND m.school_id = $admin_school";
      if($admin_faculty != 0){
        $material_sql .= " AND d.faculty_id = $admin_faculty";
      }
    } else {
      if($school > 0){
        $material_sql .= " AND m.school_id = $school";
      }
      if($faculty != 0){
        $material_sql .= " AND d.faculty_id = $faculty";
      }
    }
    if($dept > 0){
      $material_sql .= " AND m.dept = $dept";
    }
    $material_sql .= " GROUP BY m.id ORDER BY m.created_at DESC";
    $mat_query = mysqli_query($conn, $material_sql);
    $materials = array();
    while($row = mysqli_fetch_assoc($mat_query)){
      $materials[] = array(
        'id' => $row['id'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => $row['price'],
        'revenue' => $row['revenue'],
        'qty_sold' => $row['qty_sold'],
        'status' => $row['status'],
        'db_status' => $row['db_status'],
        'due_date' => date('M d, Y', strtotime($row['due_date'])),
        'due_passed' => $row['due_passed'] == 1
      );
    }
    $statusRes = 'success';
  }
}

if(isset($_POST['toggle_id'])){
  $id = intval($_POST['toggle_id']);
  $manual_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT m.status, m.school_id, d.faculty_id FROM manuals m LEFT JOIN depts d ON m.dept = d.id WHERE m.id = $id"));
  if($manual_res){
    if($admin_role == 5 && ($manual_res['school_id'] != $admin_school || ($admin_faculty != 0 && $manual_res['faculty_id'] != $admin_faculty))){
      $statusRes = 'error';
      $messageRes = 'Unauthorized';
    } else {
      $new_status = ($manual_res['status'] == 'open') ? 'closed' : 'open';
      mysqli_query($conn, "UPDATE manuals SET status = '$new_status' WHERE id = $id");
      if(mysqli_affected_rows($conn) > 0){
        $statusRes = 'success';
        $messageRes = 'Material status updated';
      } else {
        $statusRes = 'error';
        $messageRes = 'Update failed';
      }
    }
  } else {
    $statusRes = 'error';
    $messageRes = 'Material not found';
  }
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'faculties' => $faculties,
  'departments' => $departments,
  'materials' => $materials,
  'restrict_faculty' => $restrict_faculty
);

header('Content-Type: application/json');
echo json_encode($responseData);
?>
