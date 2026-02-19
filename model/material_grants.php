<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');

// Check if user is logged in and has role 6
$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : null;
$admin_id = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : null;

if ($admin_role === null || $admin_role !== 6 || !$admin_id) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
  exit();
}

// Get admin's school and faculty for filtering
$admin_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
$admin_school = isset($admin_info['school']) ? (int) $admin_info['school'] : 0;
$admin_faculty = isset($admin_info['faculty']) ? (int) $admin_info['faculty'] : 0;

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'list') {
  // List all material exports with optional status filter
  $status = $_GET['status'] ?? '';
  
  $sql = "SELECT 
    e.id,
    e.code,
    e.manual_id,
    e.hoc_user_id,
    e.students_count,
    e.total_amount,
    e.downloaded_at,
    e.grant_status,
    e.granted_by,
    e.granted_at,
    e.last_student_id,
    m.title AS manual_title,
    m.course_code AS manual_code,
    m.school_id,
    m.faculty AS manual_faculty,
    m.dept AS manual_dept,
    u.first_name AS hoc_first_name,
    u.last_name AS hoc_last_name,
    u.email AS hoc_email,
    a.first_name AS granted_by_first_name,
    a.last_name AS granted_by_last_name
  FROM manual_export_audits e
  LEFT JOIN manuals m ON e.manual_id = m.id
  LEFT JOIN users u ON e.hoc_user_id = u.id
  LEFT JOIN admins a ON e.granted_by = a.id
  LEFT JOIN depts d ON m.dept = d.id
  WHERE 1=1";
  
  // Filter by admin's school
  if ($admin_school > 0) {
    $sql .= " AND m.school_id = $admin_school";
  }
  
  // Filter by admin's faculty if assigned
  if ($admin_faculty != 0) {
    // Similar to role 5: check manual faculty OR department's faculty
    $sql .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
  
  if (!empty($status)) {
    $status = mysqli_real_escape_string($conn, $status);
    $sql .= " AND e.grant_status = '$status'";
  }
  
  $sql .= " ORDER BY e.downloaded_at DESC";
  
  $result = mysqli_query($conn, $sql);
  
  if ($result) {
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
      $data[] = $row;
    }
    echo json_encode(['data' => $data]);
  } else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
  }
} elseif ($action === 'grant') {
  // Grant a material export
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
  }
  
  $export_id = isset($_POST['export_id']) ? (int) $_POST['export_id'] : 0;
  
  if ($export_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid export ID']);
    exit();
  }
  
  // Check if export exists and is pending
  // Also verify it belongs to admin's school/faculty
  $check_sql = "SELECT e.id, e.code, e.grant_status, e.manual_id, m.school_id, m.faculty AS manual_faculty, m.dept AS manual_dept, d.faculty_id AS dept_faculty
    FROM manual_export_audits e
    LEFT JOIN manuals m ON e.manual_id = m.id
    LEFT JOIN depts d ON m.dept = d.id
    WHERE e.id = $export_id";
  
  $check_result = mysqli_query($conn, $check_sql);
  
  if (!$check_result || mysqli_num_rows($check_result) === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Export not found']);
    exit();
  }
  
  $export = mysqli_fetch_assoc($check_result);
  
  // Verify the export belongs to admin's school
  if ($admin_school > 0 && $export['school_id'] != $admin_school) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to grant this export']);
    exit();
  }
  
  // Verify the export belongs to admin's faculty (if admin has faculty assigned)
  if ($admin_faculty != 0) {
    $manual_faculty = (int)$export['manual_faculty'];
    $dept_faculty = (int)$export['dept_faculty'];
    
    // Check if manual's faculty matches OR department's faculty matches
    $faculty_match = ($manual_faculty == $admin_faculty) || 
                     (($manual_faculty == 0 || $manual_faculty === null) && $dept_faculty == $admin_faculty);
    
    if (!$faculty_match) {
      http_response_code(403);
      echo json_encode(['success' => false, 'message' => 'You do not have permission to grant this export']);
      exit();
    }
  }
  
  if ($export['grant_status'] === 'granted') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Export already granted']);
    exit();
  }
  
  // Update the export to granted
  $current_datetime = date('Y-m-d H:i:s');
  $update_sql = "UPDATE manual_export_audits 
    SET grant_status = 'granted', 
        granted_by = $admin_id, 
        granted_at = '$current_datetime'
    WHERE id = $export_id";
  
  if (mysqli_query($conn, $update_sql)) {
    // Log the grant action in audit logs
    log_audit_event(
      $conn,
      $admin_id,
      'grant',
      'manual_export',
      $export_id,
      'Granted material export: ' . $export['code']
    );
    
    echo json_encode([
      'success' => true, 
      'message' => 'Material export granted successfully'
    ]);
  } else {
    http_response_code(500);
    echo json_encode([
      'success' => false, 
      'message' => 'Failed to grant export: ' . mysqli_error($conn)
    ]);
  }
} else {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

mysqli_close($conn);
?>
