<?php
session_start();
include('config.php');
include('page_config.php');

$admin_role = (int)($_SESSION['nivas_adminRole'] ?? 0);
$admin_school = (int)($admin_['school'] ?? 0);
$admin_faculty = (int)($admin_['faculty'] ?? 0);

$range = $_GET['range'] ?? '7d';
$comparison_current_label = 'Last 7 Days';
$comparison_prev_label = 'Previous 7 Days';
switch ($range) {
  case 'weekly':
  case '7d':
    $range = '7d';
    $current_start = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 14 DAY)";
    $comparison_current_label = 'Last 7 Days';
    $comparison_prev_label = 'Previous 7 Days';
    break;
  case 'monthly':
  case '30d':
    $range = '30d';
    $current_start = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 60 DAY)";
    $comparison_current_label = 'Last 30 Days';
    $comparison_prev_label = 'Previous 30 Days';
    break;
  case 'quarter':
  case '90d':
    $range = '90d';
    $current_start = "DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 180 DAY)";
    $comparison_current_label = 'Last 90 Days';
    $comparison_prev_label = 'Previous 90 Days';
    break;
  case 'this_month':
    $current_start = "DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $prev_start = "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')";
    $comparison_current_label = 'This Month';
    $comparison_prev_label = 'Last Month';
    break;
  case 'yearly':
  case 'this_year':
    $range = 'this_year';
    $current_start = "DATE_FORMAT(CURDATE(), '%Y-01-01')";
    $prev_start = "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), '%Y-01-01')";
    $comparison_current_label = 'This Year';
    $comparison_prev_label = 'Last Year';
    break;
  default:
    $range = '7d';
    $current_start = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 14 DAY)";
    $comparison_current_label = 'Last 7 Days';
    $comparison_prev_label = 'Previous 7 Days';
}

$range_labels = [
  '7d' => '7 Days',
  '30d' => '30 Days',
  '90d' => '90 Days',
  'this_month' => 'This Month',
  'this_year' => 'This Year'
];
$range_label = $range_labels[$range] ?? '7 Days';

$revenue_base = "SELECT COALESCE(SUM(t.profit),0) AS total FROM transactions t WHERE t.status = 'successful'";
if ($admin_role == 5 && $admin_school > 0) {
  $revenue_base .= " AND EXISTS (SELECT 1 FROM manuals_bought b JOIN manuals m ON b.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id WHERE b.ref_id = t.ref_id AND b.status='successful' AND b.school_id = $admin_school";
  if ($admin_faculty != 0) {
    $revenue_base .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
  $revenue_base .= ")";
}
$revenue_sql = $revenue_base . " AND t.created_at >= $current_start";
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, $revenue_sql))["total"];
$prev_revenue_sql = $revenue_base . " AND t.created_at >= $prev_start AND t.created_at < $current_start";
$prev_revenue = mysqli_fetch_assoc(mysqli_query($conn, $prev_revenue_sql))["total"];

if ($admin_role == 5) {
  $total_revenue *= 0.1;
  $prev_revenue *= 0.1;
}

$growth_diff = $total_revenue - $prev_revenue;
$growth_percent = $prev_revenue > 0
  ? (abs($growth_diff) / $prev_revenue) * 100
  : ($total_revenue > 0 ? 100 : 0);
$growth_percent = round($growth_percent, 2);
$growth_sign = $growth_diff >= 0 ? '+' : '-';
$revenue_class = $growth_diff >= 0 ? 'text-success' : 'text-danger';
$revenue_icon = $growth_diff >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt';

$sales_base = "SELECT COALESCE(SUM(t.amount),0) AS total FROM transactions t WHERE t.status = 'successful'";
if ($admin_role == 5 && $admin_school > 0) {
  $sales_base .= " AND EXISTS (SELECT 1 FROM manuals_bought b JOIN manuals m ON b.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id WHERE b.ref_id = t.ref_id AND b.status='successful' AND b.school_id = $admin_school";
  if ($admin_faculty != 0) {
    $sales_base .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
  $sales_base .= ")";
}
$sales_sql = $sales_base . " AND t.created_at >= $current_start";
$total_sales = mysqli_fetch_assoc(mysqli_query($conn, $sales_sql))["total"];
$sales_prev_sql = $sales_base . " AND t.created_at >= $prev_start AND t.created_at < $current_start";
$prev_sales = mysqli_fetch_assoc(mysqli_query($conn, $sales_prev_sql))["total"];
$sales_diff = $total_sales - $prev_sales;
$sales_growth_percent = $prev_sales > 0
  ? (abs($sales_diff) / $prev_sales) * 100
  : ($total_sales > 0 ? 100 : 0);
$sales_growth_percent = round($sales_growth_percent, 2);
$sales_growth_sign = $sales_diff >= 0 ? '+' : '-';
$sales_class = $sales_diff >= 0 ? 'text-success' : 'text-danger';
$sales_icon = $sales_diff >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt';

$chart_categories = [$range_label];
$chart_current = [(int)$total_revenue];
$chart_previous = [(int)$prev_revenue];

header('Content-Type: application/json');
echo json_encode([
  'range' => $range,
  'range_label' => $range_label,
  'total_revenue' => (int)$total_revenue,
  'growth_percent' => $growth_percent,
  'growth_sign' => $growth_sign,
  'revenue_class' => $revenue_class,
  'revenue_icon' => $revenue_icon,
  'total_sales' => (int)$total_sales,
  'sales_growth_percent' => $sales_growth_percent,
  'sales_growth_sign' => $sales_growth_sign,
  'sales_class' => $sales_class,
  'sales_icon' => $sales_icon,
  'chart_categories' => $chart_categories,
  'chart_current' => $chart_current,
  'chart_previous' => $chart_previous,
  'chart_current_label' => $comparison_current_label,
  'chart_previous_label' => $comparison_prev_label
]);
