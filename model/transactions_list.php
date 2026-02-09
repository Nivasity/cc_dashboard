<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$transactions = [];

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
$date_range = $_GET['date_range'] ?? '7';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($admin_role == 5) {
  $school = $admin_school;
  if ($admin_faculty != 0) {
    $faculty = $admin_faculty;
  }
}

$tran_sql = "SELECT t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no, " .
  "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code, ' (', b.price, ')') SEPARATOR '<br>') AS materials " .
  "FROM transactions t " .
  "JOIN users u ON t.user_id = u.id " .
  // Include manuals purchases and manual refunds (which have no manuals_bought rows)
  "LEFT JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
  "LEFT JOIN manuals m ON b.manual_id = m.id " .
  "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
// Restrict to either manuals purchases (b exists) or manual refunds
$tran_sql .= " AND (b.ref_id IS NOT NULL OR (t.status = 'refunded' AND t.medium = 'MANUAL'))";
if ($school > 0) {
  // When there is no manuals_bought row (refunds), fall back to user's school
  $tran_sql .= " AND (b.school_id = $school OR (b.school_id IS NULL AND u.school = $school))";
}
if ($faculty != 0) {
  $tran_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
}
if ($dept > 0) {
  $tran_sql .= " AND m.dept = $dept";
}

// Build the date filter
$date_filter = "";
if ($date_range === 'custom' && $start_date && $end_date) {
  $start_date = mysqli_real_escape_string($conn, $start_date);
  $end_date = mysqli_real_escape_string($conn, $end_date);
  $date_filter = " AND t.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
} elseif ($date_range !== 'all') {
  $days = intval($date_range);
  if ($days > 0) {
    $date_filter = " AND t.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
  }
}
$tran_sql .= $date_filter;

$tran_sql .= " GROUP BY t.id, t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no ORDER BY t.created_at DESC";
$tran_query = mysqli_query($conn, $tran_sql);

if ($tran_query) {
  while ($row = mysqli_fetch_assoc($tran_query)) {
    $transactions[] = [
      'ref_id' => $row['ref_id'],
      'student' => $row['first_name'] . ' ' . $row['last_name'],
      'matric' => $row['matric_no'],
      'materials' => $row['materials'] ?? '',
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
