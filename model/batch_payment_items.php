<?php
session_start();
require_once(__DIR__ . '/config.php');

function bp_item_column_exists(mysqli $conn, $table, $column) {
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
$items = [];
$unmatched_count = 0;
$batch = null;

$has_item_first_name = bp_item_column_exists($conn, 'manual_payment_batch_items', 'student_first_name');
$has_item_last_name = bp_item_column_exists($conn, 'manual_payment_batch_items', 'student_last_name');
$has_paid_by_name = bp_item_column_exists($conn, 'manual_payment_batches', 'paid_by_name');
$has_paid_by_phone = bp_item_column_exists($conn, 'manual_payment_batches', 'paid_by_phone');
$has_payment_reason = bp_item_column_exists($conn, 'manual_payment_batches', 'payment_reason');
$has_receipt_path = bp_item_column_exists($conn, 'manual_payment_batches', 'receipt_path');
$has_receipt_name = bp_item_column_exists($conn, 'manual_payment_batches', 'receipt_name');

$item_first_name_select = $has_item_first_name ? 'i.student_first_name' : "'' AS student_first_name";
$item_last_name_select = $has_item_last_name ? 'i.student_last_name' : "'' AS student_last_name";
$paid_by_name_select = $has_paid_by_name ? 'b.paid_by_name' : "'' AS paid_by_name";
$paid_by_phone_select = $has_paid_by_phone ? 'b.paid_by_phone' : "'' AS paid_by_phone";
$payment_reason_select = $has_payment_reason ? 'b.payment_reason' : "'' AS payment_reason";
$receipt_path_select = $has_receipt_path ? 'b.receipt_path' : "'' AS receipt_path";
$receipt_name_select = $has_receipt_name ? 'b.receipt_name' : "'' AS receipt_name";

$batch_id = intval($_GET['batch_id'] ?? 0);
if ($batch_id <= 0) {
  $message = 'Invalid batch id.';
} else {
  $summary_sql = "SELECT b.id, b.tx_ref, b.gateway, b.status, b.total_students, b.total_amount, b.created_at,
                         m.title AS manual_title, m.course_code,
                         $paid_by_name_select,
                         $paid_by_phone_select,
                         $payment_reason_select,
                         $receipt_path_select,
                         $receipt_name_select
                  FROM manual_payment_batches b
                  LEFT JOIN manuals m ON b.manual_id = m.id
                  WHERE b.id = $batch_id
                  LIMIT 1";
  $summary_res = mysqli_query($conn, $summary_sql);
  if ($summary_res) {
    $summary_row = mysqli_fetch_assoc($summary_res);
    if ($summary_row) {
      $receipt_path = trim((string)($summary_row['receipt_path'] ?? ''));
      $batch = [
        'id' => (int)($summary_row['id'] ?? 0),
        'tx_ref' => (string)($summary_row['tx_ref'] ?? ''),
        'gateway' => strtoupper((string)($summary_row['gateway'] ?? 'PAYSTACK')),
        'status' => (string)($summary_row['status'] ?? ''),
        'total_students' => (int)($summary_row['total_students'] ?? 0),
        'total_amount' => (int)($summary_row['total_amount'] ?? 0),
        'created_at' => (string)($summary_row['created_at'] ?? ''),
        'manual' => trim((string)($summary_row['manual_title'] ?? '') . ' - ' . (string)($summary_row['course_code'] ?? ''), ' -'),
        'paid_by_name' => (string)($summary_row['paid_by_name'] ?? ''),
        'paid_by_phone' => (string)($summary_row['paid_by_phone'] ?? ''),
        'payment_reason' => (string)($summary_row['payment_reason'] ?? ''),
        'receipt_name' => (string)($summary_row['receipt_name'] ?? ''),
        'receipt_url' => $receipt_path !== '' ? '/' . ltrim($receipt_path, '/') : '',
        'is_manual' => strtoupper((string)($summary_row['gateway'] ?? '')) === 'MANUAL',
      ];
    }
  }

  $sql = "SELECT i.id, i.student_id, i.student_matric, i.manual_id, i.price, i.ref_id, i.status,
                 u.first_name, u.last_name, u.matric_no, u.email,
                 $item_first_name_select,
                 $item_last_name_select
          FROM manual_payment_batch_items i
          LEFT JOIN users u ON i.student_id = u.id
          LEFT JOIN manual_payment_batches b ON i.batch_id = b.id
          WHERE i.batch_id = $batch_id";
  $res = mysqli_query($conn, $sql);
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $matched = (int)($row['student_id'] ?? 0) > 0;
      $stored_name = trim((string)($row['student_first_name'] ?? '') . ' ' . (string)($row['student_last_name'] ?? ''));
      if (!$matched) {
        $unmatched_count++;
      }
      $items[] = [
        'id' => (int)$row['id'],
        'student' => $matched ? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) : ($stored_name !== '' ? $stored_name : 'Unmatched student record'),
        'matric' => $matched ? ($row['matric_no'] ?? '') : ($row['student_matric'] ?? ''),
        'email' => $matched ? ($row['email'] ?? '') : '',
        'manual_id' => (int)$row['manual_id'],
        'price' => (int)$row['price'],
        'ref_id' => $row['ref_id'],
        'status' => $row['status'],
        'matched' => $matched,
        'note' => $matched
          ? 'Matched to a student record in users.'
          : ($stored_name !== ''
            ? 'No users record matched this matric number. Stored with the uploaded student name and matric number only.'
            : 'No users record matched this matric number. Stored with student_id 0 and matric only.')
      ];
    }
    $status = 'success';
  } else {
    $message = 'Failed to fetch batch items.';
  }
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'items' => $items,
  'unmatched_count' => $unmatched_count,
  'batch' => $batch
]);
?>

