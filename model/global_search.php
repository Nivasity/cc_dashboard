<?php
session_start();
require_once(__DIR__ . '/config.php');

header('Content-Type: application/json');

$adminId = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;
$adminRole = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;

if ($adminId <= 0 || $adminRole <= 0) {
  respondJson(401, [
    'status' => 'failed',
    'message' => 'Unauthorized',
  ]);
}

$adminRows = fetchRows(
  $conn,
  "SELECT id, role, school, faculty
   FROM admins
   WHERE id = {$adminId}
   LIMIT 1"
);

if ($adminRows === []) {
  respondJson(401, [
    'status' => 'failed',
    'message' => 'Admin not found.',
  ]);
}

$admin = $adminRows[0];
$adminSchoolId = (int) ($admin['school'] ?? 0);
$adminFacultyId = (int) ($admin['faculty'] ?? 0);
$isSchoolScopedAdmin = $adminRole === 5 && $adminSchoolId > 0;

$canAcademics = in_array($adminRole, [1, 2, 3, 5], true);
$canFinance = in_array($adminRole, [1, 2, 3, 4, 5], true);
$canRefunds = in_array($adminRole, [1, 2, 3, 4], true);
$canMaterials = in_array($adminRole, [1, 2, 3, 5], true);
$canStudents = in_array($adminRole, [1, 2, 3, 5], true);
$canSupport = in_array($adminRole, [1, 2, 3, 4, 5], true);
$canUserSupport = in_array($adminRole, [1, 2, 3], true);

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
  respondJson(200, [
    'status' => 'success',
    'query' => '',
    'groups' => [],
    'total' => 0,
  ]);
}

if (function_exists('mb_substr')) {
  $query = mb_substr($query, 0, 100);
} else {
  $query = substr($query, 0, 100);
}

if ((function_exists('mb_strlen') ? mb_strlen($query) : strlen($query)) < 2) {
  respondJson(200, [
    'status' => 'success',
    'query' => $query,
    'groups' => [],
    'total' => 0,
  ]);
}

$likeTerm = '%' . mysqli_real_escape_string($conn, $query) . '%';

$groups = [
  'academics' => [
    'key' => 'academics',
    'label' => 'Academics',
    'items' => [],
  ],
  'finance' => [
    'key' => 'finance',
    'label' => 'Finance',
    'items' => [],
  ],
  'materials' => [
    'key' => 'materials',
    'label' => 'Materials',
    'items' => [],
  ],
  'students' => [
    'key' => 'students',
    'label' => 'Students',
    'items' => [],
  ],
  'support' => [
    'key' => 'support',
    'label' => 'Support Tickets',
    'items' => [],
  ],
];

if ($canAcademics) {
  if ($adminRole !== 5) {
    $schoolRows = fetchRows(
      $conn,
      "SELECT id, name, code
       FROM schools
       WHERE status = 'active'
         AND (name LIKE '{$likeTerm}' OR code LIKE '{$likeTerm}')
       ORDER BY name ASC
       LIMIT 5"
    );

    foreach ($schoolRows as $row) {
      appendSearchItem(
        $groups,
        'academics',
        'school',
        (string) ($row['name'] ?? ''),
        'School' . (!empty($row['code']) ? ' - ' . (string) $row['code'] : ''),
        'school.php'
      );
    }
  }

  $facultyScopeSql = '';
  if ($isSchoolScopedAdmin) {
    $facultyScopeSql .= " AND f.school_id = {$adminSchoolId}";
    if ($adminFacultyId > 0) {
      $facultyScopeSql .= " AND f.id = {$adminFacultyId}";
    }
  }

  $facultyRows = fetchRows(
    $conn,
    "SELECT f.id, f.name, s.name AS school_name
     FROM faculties f
     LEFT JOIN schools s ON s.id = f.school_id
     WHERE f.status = 'active'
       AND f.name LIKE '{$likeTerm}'
       {$facultyScopeSql}
     ORDER BY f.name ASC
     LIMIT 6"
  );

  foreach ($facultyRows as $row) {
    $subtitle = 'Faculty';
    if (!empty($row['school_name'])) {
      $subtitle .= ' - ' . (string) $row['school_name'];
    }
    appendSearchItem(
      $groups,
      'academics',
      'faculty',
      (string) ($row['name'] ?? ''),
      $subtitle,
      'school.php?tab=faculties'
    );
  }

  $deptScopeSql = '';
  if ($isSchoolScopedAdmin) {
    $deptScopeSql .= " AND d.school_id = {$adminSchoolId}";
    if ($adminFacultyId > 0) {
      $deptScopeSql .= " AND d.faculty_id = {$adminFacultyId}";
    }
  }

  $deptRows = fetchRows(
    $conn,
    "SELECT d.id, d.name, f.name AS faculty_name, s.name AS school_name
     FROM depts d
     LEFT JOIN faculties f ON f.id = d.faculty_id
     LEFT JOIN schools s ON s.id = d.school_id
     WHERE d.status = 'active'
       AND d.name LIKE '{$likeTerm}'
       {$deptScopeSql}
     ORDER BY d.name ASC
     LIMIT 8"
  );

  foreach ($deptRows as $row) {
    $subtitle = 'Department';
    if (!empty($row['faculty_name'])) {
      $subtitle .= ' - ' . (string) $row['faculty_name'];
    } elseif (!empty($row['school_name'])) {
      $subtitle .= ' - ' . (string) $row['school_name'];
    }
    appendSearchItem(
      $groups,
      'academics',
      'department',
      (string) ($row['name'] ?? ''),
      $subtitle,
      'school.php?tab=departments'
    );
  }
}

if ($canFinance) {
  $transactionScopeSql = '';
  if ($isSchoolScopedAdmin) {
    $transactionScopeSql .= " AND u.school = {$adminSchoolId}";
    if ($adminFacultyId > 0) {
      $transactionScopeSql .= " AND d.faculty_id = {$adminFacultyId}";
    }
  }

  $transactionRows = fetchRows(
    $conn,
    "SELECT
       t.ref_id,
       t.amount,
       t.status,
       t.created_at,
       CONCAT_WS(' ', NULLIF(COALESCE(u.first_name, ''), ''), NULLIF(COALESCE(u.last_name, ''), '')) AS student_name,
       u.email AS student_email
     FROM transactions t
     LEFT JOIN users u ON u.id = t.user_id
     LEFT JOIN depts d ON d.id = u.dept
     WHERE (
       t.ref_id LIKE '{$likeTerm}'
       OR COALESCE(u.email, '') LIKE '{$likeTerm}'
       OR COALESCE(u.matric_no, '') LIKE '{$likeTerm}'
       OR CONCAT_WS(' ', COALESCE(u.first_name, ''), COALESCE(u.last_name, '')) LIKE '{$likeTerm}'
     )
     {$transactionScopeSql}
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT 8"
  );

  foreach ($transactionRows as $row) {
    $student = trim((string) ($row['student_name'] ?? ''));
    if ($student === '') {
      $student = (string) ($row['student_email'] ?? 'Student');
    }
    $subtitle = 'Transaction - ' . $student
      . ' - ' . formatAmount((float) ($row['amount'] ?? 0))
      . ' - ' . ucfirst((string) ($row['status'] ?? 'pending'));
    appendSearchItem(
      $groups,
      'finance',
      'transaction',
      (string) ($row['ref_id'] ?? ''),
      $subtitle,
      'transactions.php?ref_id=' . rawurlencode((string) ($row['ref_id'] ?? ''))
    );
  }

  if ($canRefunds) {
    $refundScopeSql = '';
    if ($isSchoolScopedAdmin) {
      $refundScopeSql .= " AND r.school_id = {$adminSchoolId}";
      if ($adminFacultyId > 0) {
        $refundScopeSql .= " AND d.faculty_id = {$adminFacultyId}";
      }
    }

    $refundRows = fetchRows(
      $conn,
      "SELECT
         r.id,
         r.ref_id,
         r.amount,
         r.remaining_amount,
         r.status,
         r.created_at,
         CONCAT_WS(' ', NULLIF(COALESCE(u.first_name, ''), ''), NULLIF(COALESCE(u.last_name, ''), '')) AS student_name
       FROM refunds r
       LEFT JOIN users u ON u.id = r.student_id
       LEFT JOIN depts d ON d.id = u.dept
       WHERE (
         r.ref_id LIKE '{$likeTerm}'
         OR COALESCE(r.reason, '') LIKE '{$likeTerm}'
         OR CONCAT_WS(' ', COALESCE(u.first_name, ''), COALESCE(u.last_name, '')) LIKE '{$likeTerm}'
         OR COALESCE(u.email, '') LIKE '{$likeTerm}'
         OR CAST(r.id AS CHAR) LIKE '{$likeTerm}'
       )
       {$refundScopeSql}
       ORDER BY r.created_at DESC, r.id DESC
       LIMIT 8"
    );

    foreach ($refundRows as $row) {
      $subtitle = 'Refund - ' . trim((string) ($row['student_name'] ?? 'Student'))
        . ' - Remaining ' . formatAmount((float) ($row['remaining_amount'] ?? 0))
        . ' - ' . ucfirst((string) ($row['status'] ?? 'pending'));
      appendSearchItem(
        $groups,
        'finance',
        'refund',
        'Refund #' . (string) ($row['id'] ?? '0') . ' - ' . (string) ($row['ref_id'] ?? ''),
        $subtitle,
        'refund_detail.php?id=' . (int) ($row['id'] ?? 0)
      );
    }
  }

  $batchScopeSql = '';
  if ($isSchoolScopedAdmin) {
    $batchScopeSql .= " AND b.school_id = {$adminSchoolId}";
    if ($adminFacultyId > 0) {
      $batchScopeSql .= " AND d.faculty_id = {$adminFacultyId}";
    }
  }

  $batchRows = fetchRows(
    $conn,
    "SELECT
       b.id,
       b.tx_ref,
       b.total_amount,
       b.status,
       b.created_at,
       m.title AS manual_title,
       d.name AS dept_name
     FROM manual_payment_batches b
     LEFT JOIN manuals m ON m.id = b.manual_id
     LEFT JOIN depts d ON d.id = b.dept_id
     WHERE (
       b.tx_ref LIKE '{$likeTerm}'
       OR COALESCE(m.title, '') LIKE '{$likeTerm}'
       OR COALESCE(m.course_code, '') LIKE '{$likeTerm}'
       OR COALESCE(d.name, '') LIKE '{$likeTerm}'
     )
     {$batchScopeSql}
     ORDER BY b.created_at DESC, b.id DESC
     LIMIT 8"
  );

  foreach ($batchRows as $row) {
    $subtitle = 'Batch Payment'
      . (!empty($row['manual_title']) ? ' - ' . (string) $row['manual_title'] : '')
      . (!empty($row['dept_name']) ? ' - ' . (string) $row['dept_name'] : '')
      . ' - ' . formatAmount((float) ($row['total_amount'] ?? 0))
      . ' - ' . ucfirst((string) ($row['status'] ?? 'pending'));
    appendSearchItem(
      $groups,
      'finance',
      'batch_payment',
      (string) ($row['tx_ref'] ?? ''),
      $subtitle,
      'batch_payments.php?tx_ref=' . rawurlencode((string) ($row['tx_ref'] ?? ''))
    );
  }
}

if ($canMaterials) {
  $materialScopeSql = '';
  if ($isSchoolScopedAdmin) {
    $materialScopeSql .= " AND m.school_id = {$adminSchoolId}";
    if ($adminFacultyId > 0) {
      $materialScopeSql .= " AND m.faculty = {$adminFacultyId}";
    }
  }

  $materialRows = fetchRows(
    $conn,
    "SELECT
       m.id,
       m.title,
       m.course_code,
       m.code,
       m.price,
       m.status,
       s.name AS school_name
     FROM manuals m
     LEFT JOIN schools s ON s.id = m.school_id
     WHERE (
       m.title LIKE '{$likeTerm}'
       OR m.course_code LIKE '{$likeTerm}'
       OR m.code LIKE '{$likeTerm}'
     )
     {$materialScopeSql}
     ORDER BY m.created_at DESC, m.id DESC
     LIMIT 10"
  );

  foreach ($materialRows as $row) {
    $subtitle = 'Material'
      . (!empty($row['course_code']) ? ' - ' . (string) $row['course_code'] : '')
      . (!empty($row['code']) ? ' - #' . (string) $row['code'] : '')
      . (!empty($row['school_name']) ? ' - ' . (string) $row['school_name'] : '')
      . ' - ' . formatAmount((float) ($row['price'] ?? 0));
    appendSearchItem(
      $groups,
      'materials',
      'material',
      (string) ($row['title'] ?? ''),
      $subtitle,
      'course_materials.php?manual_id=' . (int) ($row['id'] ?? 0)
    );
  }
}

if ($canStudents) {
  $studentScopeSql = '';
  if ($isSchoolScopedAdmin) {
    $studentScopeSql .= " AND u.school = {$adminSchoolId}";
    if ($adminFacultyId > 0) {
      $studentScopeSql .= " AND d.faculty_id = {$adminFacultyId}";
    }
  }

  $studentRows = fetchRows(
    $conn,
    "SELECT
       u.id,
       u.first_name,
       u.last_name,
       u.email,
       u.phone,
       u.matric_no,
       u.role,
       u.status,
       s.name AS school_name,
       d.name AS dept_name
     FROM users u
     LEFT JOIN schools s ON s.id = u.school
     LEFT JOIN depts d ON d.id = u.dept
     WHERE u.role IN ('student', 'hoc')
       AND (
         COALESCE(u.email, '') LIKE '{$likeTerm}'
         OR COALESCE(u.phone, '') LIKE '{$likeTerm}'
         OR COALESCE(u.matric_no, '') LIKE '{$likeTerm}'
         OR CONCAT_WS(' ', COALESCE(u.first_name, ''), COALESCE(u.last_name, '')) LIKE '{$likeTerm}'
       )
       {$studentScopeSql}
     ORDER BY u.last_login DESC, u.id DESC
     LIMIT 10"
  );

  foreach ($studentRows as $row) {
    $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
    if ($fullName === '') {
      $fullName = (string) ($row['email'] ?? ('Student #' . (int) ($row['id'] ?? 0)));
    }

    $lookupValue = trim((string) ($row['email'] ?? ''));
    if ($lookupValue === '') {
      $lookupValue = trim((string) ($row['matric_no'] ?? ''));
    }
    if ($lookupValue === '') {
      $lookupValue = trim((string) ($row['phone'] ?? ''));
    }
    if ($lookupValue === '') {
      continue;
    }

    $subtitle = strtoupper((string) ($row['role'] ?? 'student'));
    if (!empty($row['matric_no'])) {
      $subtitle .= ' - ' . (string) $row['matric_no'];
    } elseif (!empty($row['email'])) {
      $subtitle .= ' - ' . (string) $row['email'];
    }
    if (!empty($row['dept_name'])) {
      $subtitle .= ' - ' . (string) $row['dept_name'];
    } elseif (!empty($row['school_name'])) {
      $subtitle .= ' - ' . (string) $row['school_name'];
    }

    appendSearchItem(
      $groups,
      'students',
      'student_profile',
      $fullName,
      $subtitle,
      'students.php?tab=profile&student_data=' . rawurlencode($lookupValue)
    );
  }
}

if ($canSupport) {
  if ($canUserSupport) {
    $supportRows = fetchRows(
      $conn,
      "SELECT
         st.code,
         st.subject,
         st.status,
         st.priority,
         st.created_at,
         CONCAT_WS(' ', NULLIF(COALESCE(u.first_name, ''), ''), NULLIF(COALESCE(u.last_name, ''), '')) AS student_name
       FROM support_tickets_v2 st
       LEFT JOIN users u ON u.id = st.user_id
       WHERE (
         st.code LIKE '{$likeTerm}'
         OR st.subject LIKE '{$likeTerm}'
         OR COALESCE(u.email, '') LIKE '{$likeTerm}'
         OR CONCAT_WS(' ', COALESCE(u.first_name, ''), COALESCE(u.last_name, '')) LIKE '{$likeTerm}'
       )
       ORDER BY st.last_message_at DESC, st.id DESC
       LIMIT 8"
    );

    foreach ($supportRows as $row) {
      $subtitle = 'User Ticket - '
        . (!empty($row['student_name']) ? (string) $row['student_name'] : 'Student')
        . ' - ' . ucfirst((string) ($row['status'] ?? 'open'))
        . ' - ' . ucfirst((string) ($row['priority'] ?? 'medium'));
      appendSearchItem(
        $groups,
        'support',
        'user_ticket',
        (string) ($row['code'] ?? ''),
        $subtitle,
        'tickets.php?status=' . rawurlencode((string) ($row['status'] ?? 'open')) . '&code=' . rawurlencode((string) ($row['code'] ?? ''))
      );
    }
  }

  $internalRows = fetchRows(
    $conn,
    "SELECT
       ast.code,
       ast.subject,
       ast.status,
       ast.priority,
       ast.updated_at
     FROM admin_support_tickets ast
     WHERE (
       ast.created_by_admin_id = {$adminId}
       OR ast.assigned_admin_id = {$adminId}
       OR ast.assigned_role_id = {$adminRole}
     )
       AND (
         ast.code LIKE '{$likeTerm}'
         OR ast.subject LIKE '{$likeTerm}'
         OR COALESCE(ast.category, '') LIKE '{$likeTerm}'
       )
     ORDER BY ast.updated_at DESC, ast.id DESC
     LIMIT 8"
  );

  foreach ($internalRows as $row) {
    $subtitle = 'Internal Ticket - '
      . ucfirst((string) ($row['status'] ?? 'open'))
      . ' - ' . ucfirst((string) ($row['priority'] ?? 'medium'));
    appendSearchItem(
      $groups,
      'support',
      'internal_ticket',
      (string) ($row['code'] ?? ''),
      $subtitle,
      'admin_tickets.php?code=' . rawurlencode((string) ($row['code'] ?? ''))
    );
  }
}

$total = 0;
$groupList = [];
foreach ($groups as $group) {
  $count = count($group['items']);
  if ($count <= 0) {
    continue;
  }
  $group['count'] = $count;
  $groupList[] = $group;
  $total += $count;
}

respondJson(200, [
  'status' => 'success',
  'query' => $query,
  'groups' => $groupList,
  'total' => $total,
]);

function fetchRows(mysqli $conn, string $sql): array
{
  $result = mysqli_query($conn, $sql);
  if (!$result instanceof mysqli_result) {
    return [];
  }

  $rows = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }
  mysqli_free_result($result);

  return $rows;
}

function appendSearchItem(array &$groups, string $groupKey, string $type, string $title, string $subtitle, string $url): void
{
  if (!isset($groups[$groupKey])) {
    return;
  }

  $cleanTitle = trim($title);
  if ($cleanTitle === '') {
    return;
  }

  $groups[$groupKey]['items'][] = [
    'type' => $type,
    'title' => $cleanTitle,
    'subtitle' => trim($subtitle),
    'url' => $url,
  ];
}

function formatAmount(float $amount): string
{
  return 'NGN ' . number_format($amount, 2);
}

function respondJson(int $statusCode, array $payload): void
{
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit();
}
