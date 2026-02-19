<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');

header('Content-Type: application/json');

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$admin_id = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;

if ($admin_role !== 6 || $admin_id <= 0) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
  exit();
}

$admin_scope_query = mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id LIMIT 1");
$admin_info = $admin_scope_query ? mysqli_fetch_assoc($admin_scope_query) : null;
$admin_school = isset($admin_info['school']) ? (int) $admin_info['school'] : 0;
$admin_faculty = isset($admin_info['faculty']) ? (int) $admin_info['faculty'] : 0;

if ($admin_school <= 0 || $admin_faculty <= 0) {
  http_response_code(403);
  echo json_encode([
    'success' => false,
    'message' => 'Account scope is not configured. Assign both school and faculty for role 6.'
  ]);
  exit();
}

$action = $_GET['action'] ?? '';
$scope_clause = "m.school_id = $admin_school AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";

if ($action === 'list') {
  $status = trim((string)($_GET['status'] ?? ''));
  if ($status !== '' && !in_array($status, ['pending', 'granted'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status filter']);
    exit();
  }

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
  WHERE $scope_clause";

  if ($status !== '') {
    $sql .= " AND e.grant_status = '$status'";
  }

  $sql .= ' ORDER BY e.downloaded_at DESC';

  $result = mysqli_query($conn, $sql);
  if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
  }

  $data = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
  }

  echo json_encode(['data' => $data]);
  exit();
}

if ($action === 'grant') {
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

  $check_sql = "SELECT
    e.id,
    e.code,
    e.grant_status
  FROM manual_export_audits e
  LEFT JOIN manuals m ON e.manual_id = m.id
  LEFT JOIN depts d ON m.dept = d.id
  WHERE e.id = $export_id
    AND $scope_clause
  LIMIT 1";

  $check_result = mysqli_query($conn, $check_sql);
  if (!$check_result || mysqli_num_rows($check_result) === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to grant this export']);
    exit();
  }

  $export = mysqli_fetch_assoc($check_result);
  if (($export['grant_status'] ?? '') === 'granted') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Export already granted']);
    exit();
  }

  $current_datetime = date('Y-m-d H:i:s');
  $update_sql = "UPDATE manual_export_audits
    SET grant_status = 'granted',
        granted_by = $admin_id,
        granted_at = '$current_datetime'
    WHERE id = $export_id AND grant_status = 'pending'";

  if (!mysqli_query($conn, $update_sql)) {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'Failed to grant export: ' . mysqli_error($conn)
    ]);
    exit();
  }

  if (mysqli_affected_rows($conn) < 1) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Export is no longer pending']);
    exit();
  }

  log_audit_event(
    $conn,
    $admin_id,
    'grant',
    'manual_export',
    $export_id,
    'Granted material export: ' . ($export['code'] ?? '')
  );

  echo json_encode([
    'success' => true,
    'message' => 'Material export granted successfully'
  ]);
  exit();
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
