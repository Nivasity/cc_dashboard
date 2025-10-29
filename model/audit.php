<?php
session_start();
include('config.php');
include('functions.php');

header('Content-Type: application/json');

$role = $_SESSION['nivas_adminRole'] ?? null;
if (!in_array((int) $role, [1, 2], true)) {
  http_response_code(403);
  echo json_encode([
    'status' => 'error',
    'message' => 'Unauthorized'
  ]);
  exit();
}

$get_data = $_GET['get_data'] ?? '';
if ($get_data !== 'audit_logs') {
  echo json_encode([
    'status' => 'error',
    'message' => 'Invalid request'
  ]);
  exit();
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
$limit = max(1, min($limit, 1000));

$where = [];
$params = [];
$types = '';

$start_date = $_GET['start_date'] ?? '';
if ($start_date) {
  $start = DateTime::createFromFormat('Y-m-d', $start_date);
  if ($start) {
    $where[] = 'al.created_at >= ?';
    $types .= 's';
    $params[] = $start->format('Y-m-d 00:00:00');
  }
}

$end_date = $_GET['end_date'] ?? '';
if ($end_date) {
  $end = DateTime::createFromFormat('Y-m-d', $end_date);
  if ($end) {
    $where[] = 'al.created_at <= ?';
    $types .= 's';
    $params[] = $end->format('Y-m-d 23:59:59');
  }
}

$admin_id = isset($_GET['admin_id']) ? (int) $_GET['admin_id'] : 0;
if ($admin_id > 0) {
  $where[] = 'al.admin_id = ?';
  $types .= 'i';
  $params[] = $admin_id;
}

$action = trim($_GET['action'] ?? '');
if ($action !== '') {
  $where[] = 'al.action = ?';
  $types .= 's';
  $params[] = $action;
}

$entity_type = trim($_GET['entity_type'] ?? '');
if ($entity_type !== '') {
  $where[] = 'al.entity_type = ?';
  $types .= 's';
  $params[] = $entity_type;
}

$sql = "SELECT al.id, al.admin_id, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.user_agent, al.created_at,
        CONCAT(a.first_name, ' ', a.last_name) AS admin_name, a.email AS admin_email
        FROM audit_logs al
        LEFT JOIN admins a ON al.admin_id = a.id";

if (!empty($where)) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY al.created_at DESC LIMIT ?';
$types .= 'i';
$params[] = $limit;

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode([
    'status' => 'error',
    'message' => 'Unable to prepare statement'
  ]);
  exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
  $details = $row['details'];
  $formatted = $details;
  if ($details !== null && $details !== '') {
    $decoded = json_decode($details, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $formatted = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
  }

  $logs[] = [
    'id' => (int) $row['id'],
    'admin_id' => (int) $row['admin_id'],
    'admin_name' => trim($row['admin_name'] ?? 'Unknown'),
    'admin_email' => $row['admin_email'],
    'action' => $row['action'],
    'entity_type' => $row['entity_type'],
    'entity_id' => $row['entity_id'],
    'details' => $details,
    'details_formatted' => $formatted,
    'ip_address' => $row['ip_address'],
    'user_agent' => $row['user_agent'],
    'created_at' => date('Y-m-d H:i:s', strtotime($row['created_at']))
  ];
}

$stmt->close();

echo json_encode([
  'status' => 'success',
  'logs' => $logs
]);
