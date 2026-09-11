<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/transactions_helpers.php');

$status = 'failed';
$message = '';
$transactions = [];

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;

if (!$admin_id) {
  http_response_code(401);
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

// Drive from manuals_bought so manually-recorded batch payments (which have
// no transactions row at all — see manual_payment_batches) still appear
// here, alongside gateway purchases and gateway/online bulk purchases.
// Refunded rows never have a successful manuals_bought row, so they're
// naturally excluded.
$purchase_context_expr = buildPurchaseTransactionContextExpression('t');
$tran_sql = "SELECT b.ref_id, SUM(b.price) AS amount, 'successful' AS status, " .
  "COALESCE(MAX(t.medium), 'MANUAL (Offline Batch)') AS medium, " .
  "MIN(b.created_at) AS created_at, u.first_name, u.last_name, u.matric_no, " .
  "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code) SEPARATOR ', ') AS materials " .
  "FROM manuals_bought b " .
  "JOIN users u ON b.buyer = u.id " .
  "JOIN manuals m ON b.manual_id = m.id " .
  "LEFT JOIN transactions t ON t.ref_id = b.ref_id AND {$purchase_context_expr} IN ('purchase', 'bulk_material_purchase') " .
  "WHERE b.status = 'successful'";

if ($admin_role == 5 && $admin_school > 0) {
  $school_safe = (int)$admin_school;
  $tran_sql .= " AND (b.school_id = $school_safe OR (b.school_id IS NULL AND u.school = $school_safe))";
  if ($admin_faculty != 0) {
    $tran_sql .= buildHostedMaterialFacultyFilter('m', $admin_faculty);
  }
}

$tran_sql .= " GROUP BY b.ref_id, u.first_name, u.last_name, u.matric_no ORDER BY created_at DESC LIMIT 5";
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
      'status' => $row['status'],
      'medium' => $row['medium']
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
