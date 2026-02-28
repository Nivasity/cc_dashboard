<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$reservations = [];

$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;

if (!$admin_id) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'error',
    'message' => 'Unauthorized',
    'reservations' => []
  ]);
  exit;
}

if (!in_array($admin_role, [1, 2, 3, 4], true)) {
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'failed',
    'message' => 'Forbidden',
    'reservations' => []
  ]);
  exit;
}

$sql = "SELECT
          rr.id,
          rr.ref_id,
          rr.split_sequence,
          rr.amount,
          rr.status,
          rr.reserved_at,
          rr.consumed_at,
          rr.released_at,
          rr.school_id,
          r.id AS refund_id,
          r.ref_id AS refund_ref_id,
          COALESCE(s.name, CONCAT('School #', rr.school_id)) AS school_name,
          CASE
            WHEN u.id IS NULL THEN '-'
            ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
          END AS student_name
        FROM refund_reservations rr
        LEFT JOIN refunds r ON r.id = rr.refund_id
        LEFT JOIN schools s ON s.id = rr.school_id
        LEFT JOIN users u ON u.id = r.student_id
        ORDER BY COALESCE(rr.reserved_at, rr.consumed_at, rr.released_at) DESC, rr.id DESC
        LIMIT 5";

$query = mysqli_query($conn, $sql);
if ($query) {
  while ($row = mysqli_fetch_assoc($query)) {
    $eventAt = $row['reserved_at'] ?? $row['consumed_at'] ?? $row['released_at'];
    $reservations[] = [
      'reservation_ref' => $row['ref_id'],
      'refund_ref' => $row['refund_ref_id'],
      'split_sequence' => (int) ($row['split_sequence'] ?? 0),
      'school' => $row['school_name'],
      'student' => trim((string) ($row['student_name'] ?? '')),
      'amount' => (float) ($row['amount'] ?? 0),
      'status' => (string) ($row['status'] ?? ''),
      'date' => $eventAt ? date('M j, Y', strtotime($eventAt)) : '',
      'time' => $eventAt ? date('h:i a', strtotime($eventAt)) : '',
    ];
  }
  $status = 'success';
} else {
  $message = 'Failed to fetch refund reservations.';
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'reservations' => $reservations
]);
?>
