<?php
session_start();
include('config.php');
include('page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = $admin_['school'];
$school_clause = ($admin_role == 5) ? " AND school = $admin_school" : '';

$range = $_GET['range'] ?? '24h';
switch ($range) {
  case 'weekly':
    $current_start = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 14 DAY)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
    break;
  case 'monthly':
    $current_start = "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 2 MONTH)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    break;
  case 'yearly':
    $current_start = "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 2 YEAR)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    break;
  default:
    $current_start = "DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 2 DAY)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 1 DAY)";
}

$revenue_base = "SELECT COALESCE(SUM(t.profit),0) AS total FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'successful'";
if ($admin_role == 5) {
  $revenue_base .= " AND u.school = $admin_school";
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

$sales_base = "SELECT COALESCE(SUM(t.amount),0) AS total FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'successful'";
if ($admin_role == 5) {
  $sales_base .= " AND u.school = $admin_school";
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

header('Content-Type: application/json');
echo json_encode([
  'total_revenue' => (int)$total_revenue,
  'growth_percent' => $growth_percent,
  'growth_sign' => $growth_sign,
  'revenue_class' => $revenue_class,
  'revenue_icon' => $revenue_icon,
  'total_sales' => (int)$total_sales,
  'sales_growth_percent' => $sales_growth_percent,
  'sales_growth_sign' => $sales_growth_sign,
  'sales_class' => $sales_class,
  'sales_icon' => $sales_icon
]);
