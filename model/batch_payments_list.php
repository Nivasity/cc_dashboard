<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$batches = [];

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = $admin_faculty = 0;
if ($admin_role == 5 && $admin_id) {
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
  if ($info) {
    $admin_school = (int)$info['school'];
    $admin_faculty = (int)$info['faculty'];
  }
}

$school = intval($_GET['school'] ?? 0);
$faculty = intval($_GET['faculty'] ?? 0);
$dept = intval($_GET['dept'] ?? 0);

if ($admin_role == 5) {
  $school = $admin_school;
  if ($admin_faculty != 0) { $faculty = $admin_faculty; }
}

$sql = "SELECT b.id, b.tx_ref, b.total_students, b.total_amount, b.status, b.created_at, 
               m.title AS manual_title, m.course_code, d.name AS dept_name, s.name AS school_name
        FROM manual_payment_batches b
        JOIN manuals m ON b.manual_id = m.id
        JOIN depts d ON b.dept_id = d.id
        JOIN schools s ON b.school_id = s.id
        WHERE 1=1";
if ($school > 0) { $sql .= " AND b.school_id = $school"; }
if ($dept > 0) { $sql .= " AND b.dept_id = $dept"; }
if ($faculty != 0) { $sql .= " AND d.faculty_id = $faculty"; }
$sql .= " ORDER BY b.created_at DESC";

$res = mysqli_query($conn, $sql);
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $batches[] = [
      'id' => (int)$row['id'],
      'tx_ref' => $row['tx_ref'],
      'manual' => $row['manual_title'] . ' - ' . $row['course_code'],
      'dept' => $row['dept_name'],
      'school' => $row['school_name'],
      'total_students' => (int)$row['total_students'],
      'total_amount' => (int)$row['total_amount'],
      'status' => $row['status'],
      'created_at' => $row['created_at']
    ];
  }
  $status = 'success';
} else {
  $message = 'Failed to fetch batches.';
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'batches' => $batches
]);
?>

