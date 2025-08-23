<?php
session_start();
include('config.php');
include('page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = $admin_['school'];
$school_clause = ($admin_role == 5) ? " AND u.school = $admin_school" : '';

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$curr_year = $year;
$prev_year = $curr_year - 1;

$revenue_base = "SELECT COALESCE(SUM(t.profit),0) AS total FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'successful'";
if ($admin_role == 5) {
  $revenue_base .= " AND u.school = $admin_school";
}

$curr_sql = $revenue_base . " AND YEAR(t.created_at) = $curr_year";
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, $curr_sql))["total"];
$prev_sql = $revenue_base . " AND YEAR(t.created_at) = $prev_year";
$prev_revenue = mysqli_fetch_assoc(mysqli_query($conn, $prev_sql))["total"];

if ($admin_role == 5) {
  $total_revenue *= 0.1;
  $prev_revenue *= 0.1;
}

$growth_percent = $prev_revenue > 0 ? (($total_revenue - $prev_revenue) / $prev_revenue) * 100 : 0;
$growth_percent = round($growth_percent, 2);

$monthly_current = [];
$monthly_previous = [];
for ($m = 1; $m <= 12; $m++) {
  $month_sql = $revenue_base . " AND YEAR(t.created_at) = $curr_year AND MONTH(t.created_at) = $m";
  $month_total = mysqli_fetch_assoc(mysqli_query($conn, $month_sql))["total"];
  $prev_month_sql = $revenue_base . " AND YEAR(t.created_at) = $prev_year AND MONTH(t.created_at) = $m";
  $prev_month_total = mysqli_fetch_assoc(mysqli_query($conn, $prev_month_sql))["total"];
  if ($admin_role == 5) {
    $month_total *= 0.1;
    $prev_month_total *= 0.1;
  }
  $monthly_current[] = (int)$month_total;
  $monthly_previous[] = (int)$prev_month_total;
}

header('Content-Type: application/json');
echo json_encode([
  'total_revenue' => (int)$total_revenue,
  'prev_revenue' => (int)$prev_revenue,
  'growth_percent' => $growth_percent,
  'curr_year' => $curr_year,
  'prev_year' => $prev_year,
  'monthly_current' => $monthly_current,
  'monthly_previous' => $monthly_previous
]);
