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

// First query: Get total count and sum
$stats_sql = "SELECT COUNT(DISTINCT t.id) as total_count, SUM(t.amount) as total_sum " .
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

$stats_sql .= buildDateFilter($conn, $date_range, $start_date, $end_date);

$stats_query = mysqli_query($conn, $stats_sql);

// Second query: Get the most common payment amount (mode)
$mode_sql = "SELECT t.amount, COUNT(*) as frequency " .
  "FROM transactions t " .
  "JOIN users u ON t.user_id = u.id " .
  "LEFT JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
  "LEFT JOIN manuals m ON b.manual_id = m.id " .
  "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";

$mode_sql .= " AND (b.ref_id IS NOT NULL OR (t.status = 'refunded' AND t.medium = 'MANUAL'))";

if ($school > 0) {
  $mode_sql .= " AND (b.school_id = $school OR (b.school_id IS NULL AND u.school = $school))";
}
if ($faculty != 0) {
  $mode_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
}
if ($dept > 0) {
  $mode_sql .= " AND m.dept = $dept";
}

$mode_sql .= buildDateFilter($conn, $date_range, $start_date, $end_date);
$mode_sql .= " GROUP BY t.amount ORDER BY frequency DESC, t.amount DESC LIMIT 1";

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
    $prev_stats_sql = "SELECT COUNT(DISTINCT t.id) as total_count, SUM(t.amount) as total_sum " .
      "FROM transactions t " .
      "JOIN users u ON t.user_id = u.id " .
      "LEFT JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
      "LEFT JOIN manuals m ON b.manual_id = m.id " .
      "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
    
    $prev_stats_sql .= " AND (b.ref_id IS NOT NULL OR (t.status = 'refunded' AND t.medium = 'MANUAL'))";
    
    if ($school > 0) {
      $prev_stats_sql .= " AND (b.school_id = $school OR (b.school_id IS NULL AND u.school = $school))";
    }
    if ($faculty != 0) {
      $prev_stats_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
    }
    if ($dept > 0) {
      $prev_stats_sql .= " AND m.dept = $dept";
    }
    
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
      
      $prev_start_str = $prev_start->format('Y-m-d');
      $prev_end_str = $prev_end->format('Y-m-d');
      $prev_stats_sql .= " AND t.created_at BETWEEN '{$prev_start_str} 00:00:00' AND '{$prev_end_str} 23:59:59'";
    } else {
      $days = intval($date_range);
      if ($days > 0) {
        $prev_stats_sql .= " AND t.created_at >= DATE_SUB(NOW(), INTERVAL " . ($days * 2) . " DAY)";
        $prev_stats_sql .= " AND t.created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)";
      }
    }
    
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
