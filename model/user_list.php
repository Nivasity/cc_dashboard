<?php
session_start();
header('Content-Type: application/json');

include('config.php');

$response = ['status' => 'error', 'message' => 'Unauthorized'];
$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : null;

if ($admin_role === null || !in_array($admin_role, [1, 2, 3], true)) {
  echo json_encode($response);
  exit();
}

$type = $_REQUEST['type'] ?? '';
$type = in_array($type, ['hoc', 'org_admin'], true) ? $type : '';

function columnExists(mysqli $conn, string $table, string $column): bool {
  static $cache = [];
  $key = $table . ':' . $column;
  if (array_key_exists($key, $cache)) {
    return $cache[$key];
  }
  $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
  $cache[$key] = $result && mysqli_num_rows($result) > 0;
  return $cache[$key];
}

function formatStatus(string $status): string {
  $status = strtolower($status);
  return match ($status) {
    'inreview' => 'In Review',
    'denied', 'deactivate' => 'Deactivated',
    default => ucfirst($status),
  };
}

function formatDate(?string $value): ?string {
  if (!$value || $value === '0000-00-00 00:00:00') {
    return null;
  }
  return date('M j, Y', strtotime($value));
}

function formatDateTime(?string $value): ?string {
  if (!$value || $value === '0000-00-00 00:00:00') {
    return null;
  }
  return date('M j, Y g:i a', strtotime($value));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
  $user_id = intval($_POST['user_id'] ?? 0);
  $action = $_POST['action'] ?? '';

  if (!$user_id || !in_array($type, ['hoc', 'org_admin'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
  }

  if (!in_array($action, ['activate', 'deactivate'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit();
  }

  $target_status = $action === 'activate' ? 'verified' : 'denied';
  $role_check = $type === 'hoc' ? "'hoc'" : "'org_admin'";

  mysqli_query($conn, "UPDATE users SET status = '$target_status' WHERE id = $user_id AND role = $role_check");

  if (mysqli_affected_rows($conn) > 0) {
    $message = $action === 'activate' ? 'User activated successfully!' : 'User deactivated successfully!';
    echo json_encode(['status' => 'success', 'message' => $message]);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed or no changes made']);
  }
  exit();
}

if ($type === '') {
  echo json_encode(['status' => 'error', 'message' => 'Invalid list type']);
  exit();
}

$hasCreatedAt = columnExists($conn, 'users', 'created_at');
$createdAtSelect = $hasCreatedAt ? 'u.created_at' : 'NULL AS created_at';
$users = [];

if ($type === 'hoc') {
  $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.last_login, $createdAtSelect, s.name AS school_name, d.name AS dept_name
          FROM users u
          LEFT JOIN schools s ON u.school = s.id
          LEFT JOIN depts d ON u.dept = d.id
          WHERE u.role = 'hoc'
          ORDER BY u.first_name, u.last_name";
} else {
  $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.last_login, $createdAtSelect,
                 o.business_name, o.business_address, o.web_url, o.work_email, o.created_at AS organisation_created_at
          FROM users u
          LEFT JOIN organisation o ON o.user_id = u.id
          WHERE u.role = 'org_admin'
          ORDER BY u.first_name, u.last_name";
}

$query = mysqli_query($conn, $sql);
if ($query) {
  while ($row = mysqli_fetch_assoc($query)) {
    $raw_status = $row['status'] ?? '';
    $is_active = !in_array(strtolower((string)$raw_status), ['denied', 'deactivate'], true);
    $joined = $hasCreatedAt
      ? ($row['created_at'] ?? null)
      : ($type === 'org_admin' ? ($row['organisation_created_at'] ?? null) : null);
    $users[] = [
      'id' => (int)$row['id'],
      'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
      'email' => $row['email'] ?? '',
      'phone' => $row['phone'] ?? '',
      'status' => formatStatus((string)$raw_status),
      'raw_status' => $raw_status,
      'date_joined' => $joined ? formatDate($joined) : null,
      'last_login' => formatDateTime($row['last_login'] ?? null),
      'is_active' => $is_active,
      'meta' => $type === 'hoc'
        ? [
            'school' => $row['school_name'] ?? '-',
            'dept' => $row['dept_name'] ?? '-',
          ]
        : [
            'business_name' => $row['business_name'] ?? '-',
            'business_address' => $row['business_address'] ?? '-',
            'work_email' => $row['work_email'] ?? '-',
            'web_url' => $row['web_url'] ?? '-',
          ],
    ];
  }
}

echo json_encode([
  'status' => 'success',
  'users' => $users,
]);
