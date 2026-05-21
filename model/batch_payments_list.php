<?php
session_start();
require_once(__DIR__ . '/config.php');

function bp_batch_column_exists(mysqli $conn, $table, $column) {
  $table_safe = mysqli_real_escape_string($conn, (string)$table);
  $column_safe = mysqli_real_escape_string($conn, (string)$column);
  $sql = "SELECT 1
          FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$table_safe'
            AND COLUMN_NAME = '$column_safe'
          LIMIT 1";
  $res = mysqli_query($conn, $sql);
  return $res instanceof mysqli_result && mysqli_num_rows($res) > 0;
}

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

$has_paid_by_name = bp_batch_column_exists($conn, 'manual_payment_batches', 'paid_by_name');
$has_paid_by_phone = bp_batch_column_exists($conn, 'manual_payment_batches', 'paid_by_phone');
$has_payment_reason = bp_batch_column_exists($conn, 'manual_payment_batches', 'payment_reason');
$has_receipt_path = bp_batch_column_exists($conn, 'manual_payment_batches', 'receipt_path');

$paid_by_name_select = $has_paid_by_name ? 'b.paid_by_name' : "'' AS paid_by_name";
$paid_by_phone_select = $has_paid_by_phone ? 'b.paid_by_phone' : "'' AS paid_by_phone";
$payment_reason_select = $has_payment_reason ? 'b.payment_reason' : "'' AS payment_reason";
$receipt_path_select = $has_receipt_path ? 'b.receipt_path' : "'' AS receipt_path";

$sql = "SELECT b.id, b.tx_ref, b.gateway, b.total_students, b.total_amount, b.status, b.created_at, 
               m.title AS manual_title, m.course_code, d.name AS dept_name, s.name AS school_name,
               $paid_by_name_select,
               $paid_by_phone_select,
               $payment_reason_select,
               $receipt_path_select
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
    $receipt_path = trim((string)($row['receipt_path'] ?? ''));
    $gateway = strtoupper((string)($row['gateway'] ?? 'PAYSTACK'));
    $batches[] = [
      'id' => (int)$row['id'],
      'tx_ref' => $row['tx_ref'],
      'gateway' => $gateway,
      'manual' => $row['manual_title'] . ' - ' . $row['course_code'],
      'dept' => $row['dept_name'],
      'school' => $row['school_name'],
      'total_students' => (int)$row['total_students'],
      'total_amount' => (int)$row['total_amount'],
      'status' => $row['status'],
      'created_at' => $row['created_at'],
      'paid_by_name' => (string)($row['paid_by_name'] ?? ''),
      'paid_by_phone' => (string)($row['paid_by_phone'] ?? ''),
      'payment_reason' => (string)($row['payment_reason'] ?? ''),
      'receipt_url' => $receipt_path !== '' ? '/' . ltrim($receipt_path, '/') : '',
      'is_manual' => $gateway === 'MANUAL',
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

