<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$stats = [
  'count' => 0,
  'sum' => 0,
  'average' => 0
];

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

$stats_sql = "SELECT COUNT(DISTINCT t.id) as total_count, SUM(t.amount) as total_sum, AVG(t.amount) as average_paid " .
  "FROM transactions t " .
  "JOIN users u ON t.user_id = u.id " .
  "LEFT JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
  "LEFT JOIN manuals m ON b.manual_id = m.id " .
  "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";

$stats_sql .= " AND (b.ref_id IS NOT NULL OR (t.status = 'refunded' AND t.medium = 'MANUAL'))";

if ($school > 0) {
  $stats_sql .= " AND (b.school_id = $school OR (b.school_id IS NULL AND u.school = $school))";
}
if ($faculty != 0) {
  $stats_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
}
if ($dept > 0) {
  $stats_sql .= " AND m.dept = $dept";
}

$stats_sql .= $date_filter;

$stats_query = mysqli_query($conn, $stats_sql);

if ($stats_query) {
  $row = mysqli_fetch_assoc($stats_query);
  $stats = [
    'count' => (int)($row['total_count'] ?? 0),
    'sum' => (int)($row['total_sum'] ?? 0),
    'average' => (float)($row['average_paid'] ?? 0)
  ];
  $status = 'success';
} else {
  $message = 'Failed to fetch transaction statistics.';
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'stats' => $stats
]);
?>
