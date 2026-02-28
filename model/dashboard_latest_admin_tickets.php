<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$tickets = [];

$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_role = $_SESSION['nivas_adminRole'] ?? null;

if (!$admin_id) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'error',
    'message' => 'Unauthorized',
    'tickets' => []
  ]);
  exit;
}

$sql = "SELECT t.id, t.code, t.subject, t.status, t.priority, t.category,
               t.created_at, t.last_message_at,
               ca.first_name AS created_first_name, ca.last_name AS created_last_name,
               aa.first_name AS assigned_first_name, aa.last_name AS assigned_last_name
        FROM admin_support_tickets t
        LEFT JOIN admins ca ON t.created_by_admin_id = ca.id
        LEFT JOIN admins aa ON t.assigned_admin_id = aa.id
        WHERE 1=1";

// Restrict to tickets created by this admin, assigned to this admin, or to this admin's role
$aid = (int)$admin_id;
$roleId = (int)$admin_role;
$scope = "(t.created_by_admin_id = $aid OR t.assigned_admin_id = $aid OR t.assigned_role_id = $roleId)";
$sql .= " AND $scope";

$sql .= " ORDER BY COALESCE(t.last_message_at, t.created_at) DESC LIMIT 5";
$q = mysqli_query($conn, $sql);

if ($q) {
  while ($row = mysqli_fetch_assoc($q)) {
    // Use the same timestamp logic as the ORDER BY clause for consistency
    $lastActivityAt = $row['last_message_at'] ?? $row['created_at'];
    $tickets[] = [
      'id' => (int) $row['id'],
      'code' => $row['code'],
      'subject' => $row['subject'],
      'status' => $row['status'],
      'priority' => $row['priority'],
      'category' => $row['category'] ?? 'N/A',
      'date' => $lastActivityAt ? date('M j, Y', strtotime($lastActivityAt)) : '',
      'time' => $lastActivityAt ? date('h:i a', strtotime($lastActivityAt)) : '',
      'created_by' => trim(($row['created_first_name'] ?? '') . ' ' . ($row['created_last_name'] ?? '')),
      'assigned_to' => trim(($row['assigned_first_name'] ?? '') . ' ' . ($row['assigned_last_name'] ?? '')) ?: 'Unassigned'
    ];
  }
  $status = 'success';
} else {
  $message = 'Failed to fetch internal tickets.';
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'tickets' => $tickets
]);
?>
