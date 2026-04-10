<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$items = [];
$unmatched_count = 0;

$batch_id = intval($_GET['batch_id'] ?? 0);
if ($batch_id <= 0) {
  $message = 'Invalid batch id.';
} else {
  $sql = "SELECT i.id, i.student_id, i.student_matric, i.manual_id, i.price, i.ref_id, i.status,
                 u.first_name, u.last_name, u.matric_no, u.email
          FROM manual_payment_batch_items i
          LEFT JOIN users u ON i.student_id = u.id
          WHERE i.batch_id = $batch_id";
  $res = mysqli_query($conn, $sql);
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $matched = (int)($row['student_id'] ?? 0) > 0;
      if (!$matched) {
        $unmatched_count++;
      }
      $items[] = [
        'id' => (int)$row['id'],
        'student' => $matched ? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) : 'Unmatched student record',
        'matric' => $matched ? ($row['matric_no'] ?? '') : ($row['student_matric'] ?? ''),
        'email' => $matched ? ($row['email'] ?? '') : '',
        'manual_id' => (int)$row['manual_id'],
        'price' => (int)$row['price'],
        'ref_id' => $row['ref_id'],
        'status' => $row['status'],
        'matched' => $matched,
        'note' => $matched
          ? 'Matched to a student record in users.'
          : 'No users record matched this matric number. Stored with student_id 0 and matric only.'
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
  'unmatched_count' => $unmatched_count
]);
?>

