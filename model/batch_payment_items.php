<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$items = [];

$batch_id = intval($_GET['batch_id'] ?? 0);
if ($batch_id <= 0) {
  $message = 'Invalid batch id.';
} else {
  $sql = "SELECT i.id, i.student_id, i.manual_id, i.price, i.ref_id, i.status,
                 u.first_name, u.last_name, u.matric_no, u.email
          FROM manual_payment_batch_items i
          JOIN users u ON i.student_id = u.id
          WHERE i.batch_id = $batch_id";
  $res = mysqli_query($conn, $sql);
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $items[] = [
        'id' => (int)$row['id'],
        'student' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'matric' => $row['matric_no'],
        'email' => $row['email'],
        'manual_id' => (int)$row['manual_id'],
        'price' => (int)$row['price'],
        'ref_id' => $row['ref_id'],
        'status' => $row['status']
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
  'items' => $items
]);
?>

