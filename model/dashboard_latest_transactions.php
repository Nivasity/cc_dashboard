<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$transactions = [];

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;

if (!$admin_id) {
  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'error',
    'message' => 'Unauthorized',
    'transactions' => []
  ]);
  exit;
}

$admin_school = $admin_faculty = 0;
if ($admin_role == 5 && $admin_id) {
  $aid = (int)$admin_id;
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $aid"));
  if ($info) {
    $admin_school = (int)$info['school'];
    $admin_faculty = (int)$info['faculty'];
  }
}

$tran_sql = "SELECT t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no, " .
  "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code) SEPARATOR ', ') AS materials " .
  "FROM transactions t " .
  "JOIN users u ON t.user_id = u.id " .
  "LEFT JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
  "LEFT JOIN manuals m ON b.manual_id = m.id " .
  "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";

$tran_sql .= " AND (b.ref_id IS NOT NULL OR (t.status = 'refunded' AND t.medium = 'MANUAL'))";

if ($admin_role == 5 && $admin_school > 0) {
  $school_safe = (int)$admin_school;
  $tran_sql .= " AND (b.school_id = $school_safe OR (b.school_id IS NULL AND u.school = $school_safe))";
  if ($admin_faculty != 0) {
    $faculty_safe = (int)$admin_faculty;
    $tran_sql .= " AND (m.faculty = $faculty_safe OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty_safe))";
  }
}

$tran_sql .= " GROUP BY t.id, t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no ORDER BY t.created_at DESC LIMIT 10";
$tran_query = mysqli_query($conn, $tran_sql);

if ($tran_query) {
  while ($row = mysqli_fetch_assoc($tran_query)) {
    $transactions[] = [
      'ref_id' => $row['ref_id'],
      'student' => $row['first_name'] . ' ' . $row['last_name'],
      'matric' => $row['matric_no'],
      'materials' => $row['materials'] ?? 'N/A',
      'amount' => $row['amount'],
      'date' => date('M j, Y', strtotime($row['created_at'])),
      'time' => date('h:i a', strtotime($row['created_at'])),
      'status' => $row['status']
    ];
  }
  $status = 'success';
} else {
  $message = 'Failed to fetch transactions.';
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'transactions' => $transactions
]);
?>
