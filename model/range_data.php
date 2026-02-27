<?php
session_start();
include('config.php');
include('page_config.php');
include_once('dashboard_revenue_range.php');

$admin_role = (int)($_SESSION['nivas_adminRole'] ?? 0);
$admin_school = (int)($admin_['school'] ?? 0);
$admin_faculty = (int)($admin_['faculty'] ?? 0);

$payload = get_dashboard_revenue_range_payload(
  $conn,
  $admin_role,
  $admin_school,
  $admin_faculty,
  $_GET['range'] ?? '24h'
);

header('Content-Type: application/json');
echo json_encode($payload);
