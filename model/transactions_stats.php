<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/transactions_helpers.php');

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
  $admin_id_safe = intval($admin_id);
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id_safe"));
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

function buildTransactionStatsBaseSql($conn, $school, $faculty, $dept, $date_range, $start_date, $end_date)
{
  $school = (int) $school;
  $faculty = (int) $faculty;
  $dept = (int) $dept;

  $base_sql = "SELECT t.id, t.amount " .
    "FROM transactions t " .
    "JOIN users u ON t.user_id = u.id " .
    "LEFT JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
    "LEFT JOIN manuals m ON b.manual_id = m.id " .
    "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";

  $base_sql .= buildPurchaseTransactionContextFilter('t');
  $base_sql .= " AND (b.ref_id IS NOT NULL OR (t.status = 'refunded' AND t.medium = 'MANUAL'))";

  if ($school > 0) {
    $base_sql .= " AND (b.school_id = $school OR (b.school_id IS NULL AND u.school = $school))";
  }
  if ($faculty != 0) {
    $base_sql .= buildHostedMaterialFacultyFilter('m', $faculty);
  }
  if ($dept > 0) {
    $base_sql .= buildHostedMaterialDeptFilter('m', $dept);
  }

  $base_sql .= buildDateFilter($conn, $date_range, $start_date, $end_date);
  $base_sql .= " GROUP BY t.id, t.amount";

  return $base_sql;
}

$detail_sql = buildTransactionStatsBaseSql($conn, $school, $faculty, $dept, $date_range, $start_date, $end_date);

// First query: Get total count and sum from de-duplicated transactions.
$stats_sql = "SELECT COUNT(*) AS total_count, COALESCE(SUM(filtered.amount), 0) AS total_sum FROM ({$detail_sql}) filtered";
$stats_query = mysqli_query($conn, $stats_sql);

// Second query: Get the most common payment amount (mode) from de-duplicated transactions.
$mode_sql = "SELECT filtered.amount, COUNT(*) AS frequency FROM ({$detail_sql}) filtered GROUP BY filtered.amount ORDER BY frequency DESC, filtered.amount DESC LIMIT 1";

$mode_query = mysqli_query($conn, $mode_sql);

if ($stats_query && $mode_query) {
  $row = mysqli_fetch_assoc($stats_query);
  $mode_row = mysqli_fetch_assoc($mode_query);
  
  $current_count = (int)($row['total_count'] ?? 0);
  $current_sum = (int)($row['total_sum'] ?? 0);
  $current_mode = (int)($mode_row['amount'] ?? 0);
  $mode_frequency = (int)($mode_row['frequency'] ?? 0);
  
  // Calculate previous period stats for comparison
  $prev_count = 0;
  $prev_sum = 0;
  $count_change_percent = 0;
  $sum_change_percent = 0;
  
  // Build query for previous period
  if ($date_range !== 'all') {
    $prev_date_range = $date_range;
    $prev_start_date = $start_date;
    $prev_end_date = $end_date;
    
    // Calculate previous period based on date range
    if ($date_range === 'custom' && $start_date && $end_date) {
      $start_dt = new DateTime($start_date);
      $end_dt = new DateTime($end_date);
      $interval = $start_dt->diff($end_dt);
      $days = $interval->days;
      
      $prev_end = clone $start_dt;
      $prev_end->modify('-1 day');
      $prev_start = clone $prev_end;
      $prev_start->modify("-{$days} days");
      
      $prev_start_date = $prev_start->format('Y-m-d');
      $prev_end_date = $prev_end->format('Y-m-d');
    } else {
      $days = intval($date_range);
      if ($days > 0) {
        $prev_date_range = 'custom';
        $prev_end_date = date('Y-m-d', strtotime('-' . $days . ' days'));
        $prev_start_date = date('Y-m-d', strtotime('-' . (($days * 2) - 1) . ' days'));
      }
    }

    $prev_detail_sql = buildTransactionStatsBaseSql($conn, $school, $faculty, $dept, $prev_date_range, $prev_start_date, $prev_end_date);
    $prev_stats_sql = "SELECT COUNT(*) AS total_count, COALESCE(SUM(filtered.amount), 0) AS total_sum FROM ({$prev_detail_sql}) filtered";
    $prev_query = mysqli_query($conn, $prev_stats_sql);
    if ($prev_query) {
      $prev_row = mysqli_fetch_assoc($prev_query);
      $prev_count = (int)($prev_row['total_count'] ?? 0);
      $prev_sum = (int)($prev_row['total_sum'] ?? 0);
      
      // Calculate percentage changes
      if ($prev_count > 0) {
        $count_change_percent = round((($current_count - $prev_count) / $prev_count) * 100);
      }
      if ($prev_sum > 0) {
        $sum_change_percent = round((($current_sum - $prev_sum) / $prev_sum) * 100);
      }
    }
  }
  
  $stats = [
    'count' => $current_count,
    'sum' => $current_sum,
    'average' => $current_mode,
    'count_change' => $count_change_percent,
    'sum_change' => $sum_change_percent,
    'mode_frequency' => $mode_frequency
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
