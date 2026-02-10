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

  $material_sql = "SELECT m.id, m.title, m.course_code, m.price, m.due_date, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, CASE WHEN m.due_date < NOW() THEN 'closed' ELSE m.status END AS status, m.status AS db_status, CASE WHEN m.due_date < NOW() THEN 1 ELSE 0 END AS due_passed, u.first_name, u.last_name, u.matric_no FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN users u ON m.user_id = u.id LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
  if ($school > 0) {
    $material_sql .= " AND m.school_id = $school";
  }
  if ($faculty != 0) {
    $material_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
  }
  if ($dept > 0) {
    $material_sql .= " AND m.dept = $dept";
  }
  $material_sql .= " GROUP BY m.id ORDER BY m.created_at DESC";
  $mat_query = mysqli_query($conn, $material_sql);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="materials_' . date('Ymd_His') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Title (Course Code)', 'Posted By', 'Matric No', 'Unit Price', 'Revenue', 'Qty Sold', 'Availability', 'Due Date']);
  while ($row = mysqli_fetch_assoc($mat_query)) {
    $posted_by = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $matric = $row['matric_no'] ?? '';
    fputcsv($out, [
      $row['title'] . ' (' . $row['course_code'] . ')',
      $posted_by,
      $matric,
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
    $material_sql = "SELECT m.id, m.code, m.title, m.course_code, m.price, m.due_date, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, CASE WHEN m.due_date < NOW() THEN 'closed' ELSE m.status END AS status, m.status AS db_status, CASE WHEN m.due_date < NOW() THEN 1 ELSE 0 END AS due_passed, u.first_name, u.last_name, u.matric_no FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN users u ON m.user_id = u.id LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
    if($admin_role == 5){
      $material_sql .= " AND m.school_id = $admin_school";
      if($admin_faculty != 0){
        $material_sql .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
      }
    } else {
      if($school > 0){
        $material_sql .= " AND m.school_id = $school";
      }
      if($faculty != 0){
        $material_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
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
        'code' => $row['code'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => $row['price'],
        'revenue' => $row['revenue'],
        'qty_sold' => $row['qty_sold'],
        'status' => $row['status'],
        'db_status' => $row['db_status'],
        'due_date' => date('M d, Y', strtotime($row['due_date'])),
        'due_passed' => $row['due_passed'] == 1,
        'posted_by' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'matric' => $row['matric_no'] ?? ''
      );
    }
    $statusRes = 'success';
  }
}

if(isset($_POST['toggle_id'])){
  $id = intval($_POST['toggle_id']);
  $manual_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT m.status, m.school_id, m.faculty, m.dept, m.title, m.course_code FROM manuals m WHERE m.id = $id"));
  if($manual_res){
    if($admin_role == 5 && ($manual_res['school_id'] != $admin_school || ($admin_faculty != 0 && $manual_res['faculty'] != $admin_faculty))){
      $statusRes = 'error';
      $messageRes = 'Unauthorized';
    } else {
      $new_status = ($manual_res['status'] == 'open') ? 'closed' : 'open';
      mysqli_query($conn, "UPDATE manuals SET status = '$new_status' WHERE id = $id");
      if(mysqli_affected_rows($conn) > 0){
        $statusRes = 'success';
        $messageRes = 'Material status updated';
        
        // Send notification when material is closed
        if ($new_status === 'closed' && $admin_id) {
          require_once __DIR__ . '/notification_helpers.php';
          notifyCourseMaterialClosed(
            $conn, 
            $admin_id, 
            $id, 
            $manual_res['title'], 
            $manual_res['course_code'], 
            $manual_res['dept'], 
            $manual_res['school_id']
          );
          
          // Log the action
          log_audit_event($conn, $admin_id, 'close', 'course_material', $id, [
            'title' => $manual_res['title'],
            'course_code' => $manual_res['course_code']
          ]);
        }
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

// Handle new material creation
if(isset($_POST['create_material'])){
  $school = intval($_POST['school'] ?? 0);
  $faculty = intval($_POST['faculty'] ?? 0);
  $dept = intval($_POST['dept'] ?? 0);
  $title = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
  $course_code = mysqli_real_escape_string($conn, trim($_POST['course_code'] ?? ''));
  $price = intval($_POST['price'] ?? 0);
  $due_date = mysqli_real_escape_string($conn, trim($_POST['due_date'] ?? ''));
  
  // Validate required fields
  if(empty($title) || empty($course_code) || $price < 0 || empty($due_date)){
    $statusRes = 'error';
    $messageRes = 'All required fields must be filled';
  } elseif($school == 0 || $faculty == 0){
    $statusRes = 'error';
    $messageRes = 'School and Faculty are required';
  } else {
    // Validate admin permissions
    if($admin_role == 5){
      if($school != $admin_school){
        $statusRes = 'error';
        $messageRes = 'Unauthorized: Invalid school';
      } elseif($admin_faculty != 0 && $faculty != $admin_faculty){
        $statusRes = 'error';
        $messageRes = 'Unauthorized: Invalid faculty';
      }
    }
    
    if(!isset($statusRes) || $statusRes !== 'error'){
      // Generate unique 8-character alphanumeric code
      $code = '';
      $isUnique = false;
      while(!$isUnique){
        $code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $check = mysqli_query($conn, "SELECT id FROM manuals WHERE code = '$code'");
        if(mysqli_num_rows($check) == 0){
          $isUnique = true;
        }
      }
      
      // Convert datetime-local format to MySQL datetime format
      $due_date_mysql = date('Y-m-d H:i:s', strtotime($due_date));
      
      // Insert new material
      $insert_sql = "INSERT INTO manuals (title, course_code, price, code, due_date, quantity, dept, faculty, user_id, admin_id, school_id, status, created_at) 
                     VALUES ('$title', '$course_code', $price, '$code', '$due_date_mysql', 0, $dept, $faculty, 0, $admin_id, $school, 'open', NOW())";
      
      if(mysqli_query($conn, $insert_sql)){
        $material_id = mysqli_insert_id($conn);
        $statusRes = 'success';
        $messageRes = 'Course material created successfully with code: ' . $code;
        
        // Log the action
        if(function_exists('log_audit_event')){
          log_audit_event($conn, $admin_id, 'create', 'course_material', $material_id, [
            'title' => $title,
            'course_code' => $course_code,
            'code' => $code
          ]);
        }
      } else {
        $statusRes = 'error';
        $messageRes = 'Failed to create material: ' . mysqli_error($conn);
      }
    }
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
