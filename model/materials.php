<?php
session_start();
include('config.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = '';
$faculties = $departments = $materials = null;

if(isset($_GET['fetch'])){
  $fetch = $_GET['fetch'];
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);

  if($fetch == 'faculties'){
    $fac_query = ($school > 0) ?
      mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $school ORDER BY name") :
      mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
    $faculties = array();
    while($row = mysqli_fetch_assoc($fac_query)){
      $faculties[] = $row;
    }
    $statusRes = 'success';
  }

  if($fetch == 'departments'){
    if($faculty > 0){
      $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $faculty ORDER BY name");
    } elseif($school > 0){
      $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $school ORDER BY name");
    } else {
      $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
    }
    $departments = array();
    while($row = mysqli_fetch_assoc($dept_query)){
      $departments[] = $row;
    }
    $statusRes = 'success';
  }

  if($fetch == 'materials'){
    $material_sql = "SELECT m.id, m.title, m.course_code, m.price, m.status, m.due_date, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
    if($school > 0){
      $material_sql .= " AND m.school_id = $school";
    }
    if($faculty > 0){
      $material_sql .= " AND d.faculty_id = $faculty";
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
        'due_date' => date('M d, Y', strtotime($row['due_date']))
      );
    }
    $statusRes = 'success';
  }
}

if(isset($_POST['toggle_id'])){
  $id = intval($_POST['toggle_id']);
  $status_res = mysqli_fetch_array(mysqli_query($conn, "SELECT status FROM manuals WHERE id = $id"));
  if($status_res){
    $new_status = ($status_res[0] == 'open') ? 'close' : 'open';
    mysqli_query($conn, "UPDATE manuals SET status = '$new_status' WHERE id = $id");
    if(mysqli_affected_rows($conn) > 0){
      $statusRes = 'success';
      $messageRes = 'Material status updated';
    } else {
      $statusRes = 'error';
      $messageRes = 'Update failed';
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
  'materials' => $materials
);

header('Content-Type: application/json');
echo json_encode($responseData);
?>
