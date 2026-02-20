<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');

$notification_helper_file = __DIR__ . '/notification_helpers.php';
if (file_exists($notification_helper_file)) {
  require_once($notification_helper_file);
}

$mail_helper_file = __DIR__ . '/mail.php';
if (file_exists($mail_helper_file)) {
  require_once($mail_helper_file);
}

header('Content-Type: application/json');

function respond_json($code, $payload) {
  http_response_code($code);
  echo json_encode($payload);
  exit();
}

function has_column($conn, $table, $column) {
  $table_safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
  $column_safe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
  if ($table_safe === '' || $column_safe === '') {
    return false;
  }

  $sql = "SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'";
  $result = mysqli_query($conn, $sql);
  return $result && mysqli_num_rows($result) > 0;
}

function normalize_student_row($row) {
  $first = trim((string)($row['first_name'] ?? ''));
  $last = trim((string)($row['last_name'] ?? ''));
  $full_name = trim($first . ' ' . $last);

  return [
    'bought_id' => (int)($row['bought_id'] ?? 0),
    'student_id' => (int)($row['student_id'] ?? 0),
    'full_name' => $full_name,
    'first_name' => $first,
    'last_name' => $last,
    'email' => (string)($row['email'] ?? ''),
    'matric_no' => (string)($row['matric_no'] ?? ''),
    'dept_name' => (string)($row['dept_name'] ?? ''),
    'price' => (int)($row['price'] ?? 0),
    'bought_at' => (string)($row['created_at'] ?? ''),
    'grant_status' => (int)($row['grant_status'] ?? 0),
    'is_granted' => ((int)($row['grant_status'] ?? 0)) === 1,
    'grant_status_text' => ((int)($row['grant_status'] ?? 0)) === 1 ? 'Granted' : 'Pending',
    'export_id' => isset($row['export_id']) && $row['export_id'] !== null ? (int)$row['export_id'] : null
  ];
}

function parse_admin_departments($raw_departments) {
  if ($raw_departments === null || $raw_departments === '') {
    return [];
  }

  $decoded = json_decode((string)$raw_departments, true);
  if (!is_array($decoded)) {
    return [];
  }

  $ids = [];
  foreach ($decoded as $dept_id) {
    $dept_id = (int)$dept_id;
    if ($dept_id > 0) {
      $ids[] = $dept_id;
    }
  }

  return array_values(array_unique($ids));
}

function build_department_visibility_clause($dept_id, $admin_faculty, $admin_departments) {
  $dept_id = (int)$dept_id;
  $admin_faculty = (int)$admin_faculty;
  $admin_departments = array_values(array_filter(array_map('intval', (array)$admin_departments), function ($id) {
    return $id > 0;
  }));

  if ($dept_id > 0) {
    return "(m.dept = $dept_id OR (m.dept = 0 AND m.faculty = $admin_faculty))";
  }

  if (count($admin_departments) > 0) {
    $dept_csv = implode(',', $admin_departments);
    return "(m.dept IN ($dept_csv) OR (m.dept = 0 AND m.faculty = $admin_faculty))";
  }

  return "(d.faculty_id = $admin_faculty OR (m.dept = 0 AND m.faculty = $admin_faculty))";
}

function parse_bought_ids_json($raw_json) {
  if ($raw_json === null) {
    return [];
  }

  $decoded = json_decode((string)$raw_json, true);
  if (!is_array($decoded)) {
    return [];
  }

  $ids = [];
  foreach ($decoded as $id) {
    $id = (int)$id;
    if ($id > 0) {
      $ids[] = $id;
    }
  }

  return array_values(array_unique($ids));
}

function try_send_notification($conn, $admin_id, $user_ids, $title, $body, $type = 'material', $data = []) {
  if (!function_exists('sendNotification')) {
    return ['success' => false, 'message' => 'Notification helper unavailable'];
  }

  try {
    $result = sendNotification($conn, $admin_id, $user_ids, $title, $body, $type, $data);
    if (!is_array($result)) {
      return ['success' => false, 'message' => 'Unexpected notification response'];
    }
    return $result;
  } catch (Throwable $e) {
    error_log('Grant notification error: ' . $e->getMessage());
    return ['success' => false, 'message' => 'Notification dispatch failed'];
  }
}

function try_send_email($subject, $body, $to_email) {
  $to_email = trim((string)$to_email);
  if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
    return ['success' => false, 'message' => 'Invalid recipient email'];
  }

  if (!function_exists('sendMail')) {
    return ['success' => false, 'message' => 'Email helper unavailable'];
  }

  try {
    $status = sendMail($subject, $body, $to_email);
    return ['success' => $status === 'success', 'message' => $status];
  } catch (Throwable $e) {
    error_log('Grant email error: ' . $e->getMessage());
    return ['success' => false, 'message' => 'Email dispatch failed'];
  }
}

function try_send_email_batch($subject, $body, $emails) {
  $emails = array_values(array_unique(array_filter(array_map('trim', (array)$emails), function ($email) {
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
  })));

  if (count($emails) === 0) {
    return ['success' => false, 'message' => 'No valid recipient emails', 'success_count' => 0, 'fail_count' => 0];
  }

  if (function_exists('sendMailBatch')) {
    try {
      $batch = sendMailBatch($subject, $body, $emails);
      $success_count = (int)($batch['success_count'] ?? 0);
      $fail_count = (int)($batch['fail_count'] ?? 0);
      return [
        'success' => $success_count > 0 && $fail_count === 0,
        'message' => 'batch',
        'success_count' => $success_count,
        'fail_count' => $fail_count
      ];
    } catch (Throwable $e) {
      error_log('Grant batch email error: ' . $e->getMessage());
      return ['success' => false, 'message' => 'Batch email dispatch failed', 'success_count' => 0, 'fail_count' => count($emails)];
    }
  }

  $success_count = 0;
  $fail_count = 0;
  foreach ($emails as $email) {
    $single = try_send_email($subject, $body, $email);
    if (!empty($single['success'])) {
      $success_count++;
    } else {
      $fail_count++;
    }
  }

  return [
    'success' => $success_count > 0 && $fail_count === 0,
    'message' => 'loop',
    'success_count' => $success_count,
    'fail_count' => $fail_count
  ];
}

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$admin_id = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;

if ($admin_role !== 6 || $admin_id <= 0) {
  respond_json(403, ['success' => false, 'message' => 'Unauthorized access']);
}

$admins_has_departments_column = has_column($conn, 'admins', 'departments');
$admin_scope_query = mysqli_query(
  $conn,
  $admins_has_departments_column
    ? "SELECT school, faculty, departments FROM admins WHERE id = $admin_id LIMIT 1"
    : "SELECT school, faculty FROM admins WHERE id = $admin_id LIMIT 1"
);
$admin_info = $admin_scope_query ? mysqli_fetch_assoc($admin_scope_query) : null;
$admin_school = isset($admin_info['school']) ? (int) $admin_info['school'] : 0;
$admin_faculty = isset($admin_info['faculty']) ? (int) $admin_info['faculty'] : 0;
$admin_departments = $admins_has_departments_column
  ? parse_admin_departments($admin_info['departments'] ?? null)
  : [];
$has_department_scope = count($admin_departments) > 0;

if ($admin_school <= 0 || $admin_faculty <= 0) {
  respond_json(403, [
    'success' => false,
    'message' => 'Account scope is not configured. Assign both school and faculty for role 6.'
  ]);
}

$action = $_GET['action'] ?? '';
$scope_clause = "m.school_id = $admin_school AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
$material_status_clause = "(m.status = 'open' OR (m.status = 'closed' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)))";
$export_has_bought_ids_json = has_column($conn, 'manual_export_audits', 'bought_ids_json');

if ($action === 'departments') {
  $dept_sql = "SELECT id, name
    FROM depts
    WHERE status = 'active'
      AND school_id = $admin_school
      AND faculty_id = $admin_faculty";

  if ($has_department_scope) {
    $dept_sql .= " AND id IN (" . implode(',', $admin_departments) . ")";
  }

  $dept_sql .= "
    ORDER BY name ASC";

  $dept_result = mysqli_query($conn, $dept_sql);
  if (!$dept_result) {
    respond_json(500, ['success' => false, 'message' => 'Failed to load departments']);
  }

  $departments = [
    [
      'id' => 0,
      'name' => 'All Departments'
    ]
  ];
  while ($row = mysqli_fetch_assoc($dept_result)) {
    $departments[] = [
      'id' => (int)$row['id'],
      'name' => (string)$row['name']
    ];
  }

  respond_json(200, ['success' => true, 'departments' => $departments]);
}

if ($action === 'materials') {
  $dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
  if ($dept_id < 0) {
    $dept_id = 0;
  }

  if ($has_department_scope && $dept_id > 0 && !in_array($dept_id, $admin_departments, true)) {
    respond_json(403, ['success' => false, 'message' => 'Selected department is outside your scope']);
  }

  $department_visibility_clause = build_department_visibility_clause($dept_id, $admin_faculty, $admin_departments);

  $materials_sql = "SELECT
      m.id,
      m.code,
      m.title,
      m.course_code,
      m.price,
      m.status,
      m.created_at,
      m.dept,
      COALESCE(d.name, '') AS dept_name
    FROM manuals m
    LEFT JOIN depts d ON m.dept = d.id
    WHERE $scope_clause
      AND $material_status_clause
      AND $department_visibility_clause";

  $materials_sql .= " ORDER BY (m.status = 'open') DESC, m.created_at DESC";

  $materials_result = mysqli_query($conn, $materials_sql);
  if (!$materials_result) {
    respond_json(500, ['success' => false, 'message' => 'Failed to load materials']);
  }

  $materials = [];
  while ($row = mysqli_fetch_assoc($materials_result)) {
    $materials[] = [
      'id' => (int)$row['id'],
      'code' => (string)$row['code'],
      'title' => (string)$row['title'],
      'course_code' => (string)$row['course_code'],
      'price' => (int)$row['price'],
      'status' => (string)$row['status'],
      'created_at' => (string)$row['created_at'],
      'dept_id' => (int)$row['dept'],
      'dept_name' => (string)$row['dept_name'],
      'label' => trim((string)$row['code']) . ' - ' . trim((string)$row['title'])
    ];
  }

  respond_json(200, ['success' => true, 'materials' => $materials]);
}

if ($action === 'lookup' || $action === 'grant') {
  $required_manuals_bought_cols = ['id', 'grant_status', 'export_id'];
  foreach ($required_manuals_bought_cols as $col) {
    if (!has_column($conn, 'manuals_bought', $col)) {
      respond_json(500, [
        'success' => false,
        'message' => 'manuals_bought schema is missing required column: ' . $col
      ]);
    }
  }
}

if ($action === 'lookup') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(405, ['success' => false, 'message' => 'Method not allowed']);
  }

  $manual_id = isset($_POST['manual_id']) ? (int)$_POST['manual_id'] : 0;
  $dept_id = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
  if ($dept_id < 0) {
    $dept_id = 0;
  }
  $lookup_value = trim((string)($_POST['lookup_value'] ?? ''));

  if ($manual_id <= 0) {
    respond_json(400, ['success' => false, 'message' => 'Select a material first']);
  }
  if ($lookup_value === '') {
    respond_json(400, ['success' => false, 'message' => 'Enter export code, student email, or matric number']);
  }
  if ($has_department_scope && $dept_id > 0 && !in_array($dept_id, $admin_departments, true)) {
    respond_json(403, ['success' => false, 'message' => 'Selected department is outside your scope']);
  }

  $department_visibility_clause = build_department_visibility_clause($dept_id, $admin_faculty, $admin_departments);
  $manual_sql = "SELECT
      m.id,
      m.code,
      m.title,
      m.course_code,
      m.price,
      m.status,
      m.created_at,
      m.dept,
      COALESCE(d.name, '') AS dept_name
    FROM manuals m
    LEFT JOIN depts d ON m.dept = d.id
    WHERE m.id = $manual_id
      AND $scope_clause
      AND $material_status_clause
      AND $department_visibility_clause
    LIMIT 1";

  $manual_result = mysqli_query($conn, $manual_sql);
  $manual = $manual_result ? mysqli_fetch_assoc($manual_result) : null;

  if (!$manual) {
    respond_json(404, ['success' => false, 'message' => 'Selected material is unavailable for your scope']);
  }

  $is_email_lookup = strpos($lookup_value, '@') !== false;
  $lookup_mode = '';
  $records = [];
  $lookup_context = [
    'mode' => null,
    'manual_id' => $manual_id
  ];
  $message = '';

  $export_row = null;
  if (!$is_email_lookup) {
    $export_select_columns = [
      "e.id",
      "e.code",
      "e.manual_id",
      "e.hoc_user_id",
      "e.students_count",
      "e.total_amount",
      "e.bought_ids_json"
    ];
    $export_select_columns[] = "e.grant_status";
    $export_select_columns[] = "e.downloaded_at";
    $export_select_columns[] = "e.granted_at";
    $export_select_columns[] = "e.granted_by";
    $export_select_columns[] = "COALESCE(u.first_name, '') AS hoc_first_name";
    $export_select_columns[] = "COALESCE(u.last_name, '') AS hoc_last_name";

    $export_stmt = mysqli_prepare(
      $conn,
      "SELECT
          " . implode(",
          ", $export_select_columns) . "
        FROM manual_export_audits e
        LEFT JOIN manuals m ON e.manual_id = m.id
        LEFT JOIN depts d ON m.dept = d.id
        LEFT JOIN users u ON e.hoc_user_id = u.id
        WHERE e.code = ?
          AND e.manual_id = ?
          AND $scope_clause
        LIMIT 1"
    );

    if ($export_stmt) {
      mysqli_stmt_bind_param($export_stmt, 'si', $lookup_value, $manual_id);
      mysqli_stmt_execute($export_stmt);
      $export_result = mysqli_stmt_get_result($export_stmt);
      $export_row = $export_result ? mysqli_fetch_assoc($export_result) : null;
      mysqli_stmt_close($export_stmt);
    }
  }

  if ($export_row) {
    $lookup_mode = 'export';
    $export_bought_ids = parse_bought_ids_json($export_row['bought_ids_json'] ?? null);

    if (count($export_bought_ids) === 0) {
      respond_json(422, [
        'success' => false,
        'message' => 'Old export detected, please re-download from student side.'
      ]);
    }

    $bought_ids_csv = implode(',', array_map('intval', $export_bought_ids));

    $rows_sql = "SELECT
        mb.id AS bought_id,
        mb.buyer AS student_id,
        mb.price,
        mb.created_at,
        mb.grant_status,
        mb.export_id,
        COALESCE(u.first_name, '') AS first_name,
        COALESCE(u.last_name, '') AS last_name,
        COALESCE(u.email, '') AS email,
        COALESCE(u.matric_no, '') AS matric_no,
        COALESCE(dp.name, '') AS dept_name
      FROM manuals_bought mb
      JOIN users u ON mb.buyer = u.id
      LEFT JOIN depts dp ON u.dept = dp.id
      WHERE mb.status = 'successful'
        AND mb.manual_id = $manual_id
        AND mb.id IN ($bought_ids_csv)
      ORDER BY mb.grant_status DESC, mb.id ASC";

    $rows_result = mysqli_query($conn, $rows_sql);
    if (!$rows_result) {
      respond_json(500, ['success' => false, 'message' => 'Failed to load export rows']);
    }

    while ($row = mysqli_fetch_assoc($rows_result)) {
      $records[] = normalize_student_row($row);
    }

    if (count($records) === 0) {
      respond_json(404, ['success' => false, 'message' => 'No purchase rows found within export scope']);
    }

    $lookup_context = [
      'mode' => 'export',
      'manual_id' => $manual_id,
      'export_id' => (int)$export_row['id'],
      'export_code' => (string)$export_row['code'],
      'bought_ids_count' => count($export_bought_ids),
      'bought_ids_source' => 'json',
      'export_grant_status' => (string)$export_row['grant_status']
    ];

    $hoc_name = trim((string)$export_row['hoc_first_name'] . ' ' . (string)$export_row['hoc_last_name']);
    $message = 'Loaded export ' . (string)$export_row['code'] . ($hoc_name !== '' ? (' for ' . $hoc_name) : '');
  } else {
    $lookup_mode = 'single';

    if ($is_email_lookup) {
      $student_stmt = mysqli_prepare(
        $conn,
        "SELECT
            u.id,
            COALESCE(u.first_name, '') AS first_name,
            COALESCE(u.last_name, '') AS last_name,
            COALESCE(u.email, '') AS email,
            COALESCE(u.matric_no, '') AS matric_no,
            COALESCE(dp.name, '') AS dept_name
          FROM users u
          LEFT JOIN depts dp ON u.dept = dp.id
          WHERE u.email = ?
          LIMIT 1"
      );
    } else {
      $student_stmt = mysqli_prepare(
        $conn,
        "SELECT
            u.id,
            COALESCE(u.first_name, '') AS first_name,
            COALESCE(u.last_name, '') AS last_name,
            COALESCE(u.email, '') AS email,
            COALESCE(u.matric_no, '') AS matric_no,
            COALESCE(dp.name, '') AS dept_name
          FROM users u
          LEFT JOIN depts dp ON u.dept = dp.id
          WHERE u.matric_no = ?
          LIMIT 1"
      );
    }

    if (!$student_stmt) {
      respond_json(500, ['success' => false, 'message' => 'Failed to prepare student lookup']);
    }

    mysqli_stmt_bind_param($student_stmt, 's', $lookup_value);
    mysqli_stmt_execute($student_stmt);
    $student_result = mysqli_stmt_get_result($student_stmt);
    $student = $student_result ? mysqli_fetch_assoc($student_result) : null;
    mysqli_stmt_close($student_stmt);

    if (!$student) {
      respond_json(404, ['success' => false, 'message' => 'No export or student found for this lookup value']);
    }

    $student_id = (int)$student['id'];
    $single_sql = "SELECT
        mb.id AS bought_id,
        mb.buyer AS student_id,
        mb.price,
        mb.created_at,
        mb.grant_status,
        mb.export_id,
        COALESCE(u.first_name, '') AS first_name,
        COALESCE(u.last_name, '') AS last_name,
        COALESCE(u.email, '') AS email,
        COALESCE(u.matric_no, '') AS matric_no,
        COALESCE(dp.name, '') AS dept_name
      FROM manuals_bought mb
      JOIN users u ON mb.buyer = u.id
      LEFT JOIN depts dp ON u.dept = dp.id
      WHERE mb.status = 'successful'
        AND mb.manual_id = $manual_id
        AND mb.buyer = $student_id
      ORDER BY mb.id DESC
      LIMIT 1";

    $single_result = mysqli_query($conn, $single_sql);
    $single_row = $single_result ? mysqli_fetch_assoc($single_result) : null;

    if (!$single_row) {
      respond_json(404, ['success' => false, 'message' => 'Student has no successful payment for this material']);
    }

    $records[] = normalize_student_row($single_row);

    $lookup_context = [
      'mode' => 'single',
      'manual_id' => $manual_id,
      'student_id' => $student_id,
      'bought_id' => (int)$single_row['bought_id']
    ];

    $message = 'Loaded student payment record';
  }

  $students_count = count($records);
  $total_amount = 0;
  $granted_count = 0;
  foreach ($records as $rec) {
    $total_amount += (int)$rec['price'];
    if (!empty($rec['is_granted'])) {
      $granted_count++;
    }
  }
  $pending_count = $students_count - $granted_count;

  respond_json(200, [
    'success' => true,
    'message' => $message,
    'manual' => [
      'id' => (int)$manual['id'],
      'code' => (string)$manual['code'],
      'title' => (string)$manual['title'],
      'course_code' => (string)$manual['course_code'],
      'price' => (int)$manual['price'],
      'status' => (string)$manual['status'],
      'dept_id' => (int)$manual['dept'],
      'dept_name' => (string)$manual['dept_name']
    ],
    'summary' => [
      'students_count' => $students_count,
      'price' => (int)$manual['price'],
      'total_amount' => $total_amount,
      'granted_count' => $granted_count,
      'pending_count' => $pending_count
    ],
    'lookup' => $lookup_context,
    'records' => $records
  ]);
}

if ($action === 'grant') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(405, ['success' => false, 'message' => 'Method not allowed']);
  }

  $mode = trim((string)($_POST['mode'] ?? ''));
  $manual_id = isset($_POST['manual_id']) ? (int)$_POST['manual_id'] : 0;

  if (!in_array($mode, ['export', 'single'], true)) {
    respond_json(400, ['success' => false, 'message' => 'Invalid grant mode']);
  }
  if ($manual_id <= 0) {
    respond_json(400, ['success' => false, 'message' => 'Invalid material']);
  }

  $manual_scope_sql = "SELECT m.id
    FROM manuals m
    LEFT JOIN depts d ON m.dept = d.id
    WHERE m.id = $manual_id
      AND $scope_clause
      AND " . build_department_visibility_clause(0, $admin_faculty, $admin_departments) . "
    LIMIT 1";
  $manual_scope_result = mysqli_query($conn, $manual_scope_sql);
  if (!$manual_scope_result || mysqli_num_rows($manual_scope_result) === 0) {
    respond_json(403, ['success' => false, 'message' => 'Material is outside your scope']);
  }

  if ($mode === 'export') {
    if (!$export_has_bought_ids_json) {
      respond_json(500, [
        'success' => false,
        'message' => 'manual_export_audits schema is missing bought_ids_json column'
      ]);
    }

    $export_id = isset($_POST['export_id']) ? (int)$_POST['export_id'] : 0;
    if ($export_id <= 0) {
      respond_json(400, ['success' => false, 'message' => 'Invalid export']);
    }

    $export_select_columns = [
      "e.id",
      "e.code",
      "e.grant_status",
      "e.bought_ids_json",
      "e.hoc_user_id",
      "COALESCE(hoc.email, '') AS hoc_email",
      "COALESCE(hoc.first_name, '') AS hoc_first_name",
      "COALESCE(hoc.last_name, '') AS hoc_last_name"
    ];

    $export_sql = "SELECT
        " . implode(",
        ", $export_select_columns) . "
      FROM manual_export_audits e
      LEFT JOIN manuals m ON e.manual_id = m.id
      LEFT JOIN depts d ON m.dept = d.id
      LEFT JOIN users hoc ON e.hoc_user_id = hoc.id
      WHERE e.id = $export_id
        AND e.manual_id = $manual_id
        AND $scope_clause
      LIMIT 1";

    $export_result = mysqli_query($conn, $export_sql);
    $export = $export_result ? mysqli_fetch_assoc($export_result) : null;

    if (!$export) {
      respond_json(403, ['success' => false, 'message' => 'Export not found or outside your scope']);
    }

    $export_bought_ids = parse_bought_ids_json($export['bought_ids_json'] ?? null);

    if (count($export_bought_ids) === 0) {
      respond_json(422, ['success' => false, 'message' => 'Old export detected, please re-download from student side.']);
    }

    $bought_ids_csv = implode(',', array_map('intval', $export_bought_ids));
    $student_ids = [];
    $student_emails = [];
    $student_ids_sql = "SELECT DISTINCT buyer AS student_id
      FROM manuals_bought
      WHERE status = 'successful'
        AND manual_id = $manual_id
        AND id IN ($bought_ids_csv)";
    $student_ids_result = mysqli_query($conn, $student_ids_sql);
    if ($student_ids_result) {
      while ($student_row = mysqli_fetch_assoc($student_ids_result)) {
        $sid = (int)($student_row['student_id'] ?? 0);
        if ($sid > 0) {
          $student_ids[] = $sid;
        }
      }
    }
    $student_ids = array_values(array_unique($student_ids));
    if (count($student_ids) > 0) {
      $student_ids_csv = implode(',', array_map('intval', $student_ids));
      $student_emails_sql = "SELECT email
        FROM users
        WHERE id IN ($student_ids_csv)
          AND email IS NOT NULL
          AND email <> ''";
      $student_emails_result = mysqli_query($conn, $student_emails_sql);
      if ($student_emails_result) {
        while ($student_email_row = mysqli_fetch_assoc($student_emails_result)) {
          $email = trim((string)($student_email_row['email'] ?? ''));
          if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $student_emails[] = $email;
          }
        }
      }
      $student_emails = array_values(array_unique($student_emails));
    }

    $count_sql = "SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN grant_status = 0 THEN 1 ELSE 0 END) AS pending_rows
      FROM manuals_bought
      WHERE status = 'successful'
        AND manual_id = $manual_id
        AND id IN ($bought_ids_csv)";

    $count_result = mysqli_query($conn, $count_sql);
    $count_row = $count_result ? mysqli_fetch_assoc($count_result) : null;
    $total_rows = (int)($count_row['total_rows'] ?? 0);
    $pending_rows = (int)($count_row['pending_rows'] ?? 0);

    if ($total_rows <= 0) {
      respond_json(404, ['success' => false, 'message' => 'No purchase rows found for this export scope']);
    }

    $granted_now = 0;
    if ($pending_rows > 0) {
      $grant_sql = "UPDATE manuals_bought
        SET grant_status = 1,
            export_id = $export_id
        WHERE status = 'successful'
          AND manual_id = $manual_id
          AND id IN ($bought_ids_csv)
          AND grant_status = 0";

      if (!mysqli_query($conn, $grant_sql)) {
        respond_json(500, ['success' => false, 'message' => 'Failed to grant export rows']);
      }
      $granted_now = mysqli_affected_rows($conn);
    }

    $bind_export_sql = "UPDATE manuals_bought
      SET export_id = $export_id
      WHERE status = 'successful'
        AND manual_id = $manual_id
        AND id IN ($bought_ids_csv)
        AND (export_id IS NULL OR export_id = 0)";
    mysqli_query($conn, $bind_export_sql);

    $now = date('Y-m-d H:i:s');
    $update_export_sql = "UPDATE manual_export_audits
      SET grant_status = 'granted',
          granted_by = $admin_id,
          granted_at = '$now'
      WHERE id = $export_id";

    if (!mysqli_query($conn, $update_export_sql)) {
      respond_json(500, ['success' => false, 'message' => 'Failed to update export grant status']);
    }

    $hoc_user_id = (int)($export['hoc_user_id'] ?? 0);
    $hoc_name = trim((string)($export['hoc_first_name'] ?? '') . ' ' . (string)($export['hoc_last_name'] ?? ''));
    if ($hoc_name === '') {
      $hoc_name = 'HOC';
    }
    $hoc_email = trim((string)($export['hoc_email'] ?? ''));

    $student_notification = ['success' => false, 'message' => 'No new grants to notify'];
    $hoc_notification = ['success' => false, 'message' => 'No new grants to notify'];
    $student_email_result = ['success' => false, 'message' => 'No new grants to email', 'success_count' => 0, 'fail_count' => 0];
    $hoc_email_result = ['success' => false, 'message' => 'No new grants to email'];
    if ($granted_now > 0 && count($student_ids) > 0) {
      $student_notification = try_send_notification(
        $conn,
        $admin_id,
        $student_ids,
        'Material Collection Grant Approved',
        "Your payment has been granted for collection at the material collection center by $hoc_name. If you have issues, please reach out to the HOC/Collection center.",
        'material',
        [
          'action' => 'material_collection_grant',
          'mode' => 'export',
          'manual_id' => $manual_id,
          'export_id' => $export_id,
          'hoc_name' => $hoc_name
        ]
      );
    }
    if ($granted_now > 0 && count($student_emails) > 0) {
      $student_email_result = try_send_email_batch(
        'Material Collection Grant Approved',
        "Your payment has been granted for collection at the material collection center by $hoc_name.<br><br>If you have issues, please reach out to the HOC/Collection center.",
        $student_emails
      );
    }

    if ($granted_now > 0 && $hoc_user_id > 0) {
      $hoc_notification = try_send_notification(
        $conn,
        $admin_id,
        $hoc_user_id,
        'Material Export Grant Confirmed',
        "Export code {$export['code']} has been granted for material collection. If you did not initiate this request, please complain to our help desk immediately.",
        'material',
        [
          'action' => 'material_collection_grant',
          'mode' => 'export',
          'manual_id' => $manual_id,
          'export_id' => $export_id,
          'export_code' => (string)$export['code']
        ]
      );
    }
    if ($granted_now > 0 && $hoc_email !== '') {
      $hoc_email_result = try_send_email(
        'Material Export Grant Confirmed',
        "Export code {$export['code']} has been granted for material collection.<br><br>If you did not initiate this request, please complain to our help desk immediately.",
        $hoc_email
      );
    }

    log_audit_event(
      $conn,
      $admin_id,
      'grant',
      'manual_export_rows',
      $export_id,
      [
        'mode' => 'export',
        'manual_id' => $manual_id,
        'bought_ids_count' => count($export_bought_ids),
        'bought_ids_source' => 'json',
        'student_notified_count' => count($student_ids),
        'student_notification_sent' => !empty($student_notification['success']),
        'hoc_notification_sent' => !empty($hoc_notification['success']),
        'student_emailed_count' => (int)($student_email_result['success_count'] ?? 0),
        'student_email_failed_count' => (int)($student_email_result['fail_count'] ?? 0),
        'hoc_email_sent' => !empty($hoc_email_result['success']),
        'total_rows' => $total_rows,
        'granted_now' => $granted_now
      ]
    );

    respond_json(200, [
      'success' => true,
      'message' => $granted_now > 0 ? 'Grant completed successfully' : 'All rows were already granted',
      'result' => [
        'mode' => 'export',
        'manual_id' => $manual_id,
        'export_id' => $export_id,
        'total_rows' => $total_rows,
        'granted_now' => $granted_now,
        'already_granted' => max($total_rows - $granted_now, 0),
        'students_notified' => count($student_ids),
        'student_notification_sent' => !empty($student_notification['success']),
        'hoc_notification_sent' => !empty($hoc_notification['success']),
        'students_emailed' => (int)($student_email_result['success_count'] ?? 0),
        'student_email_failed_count' => (int)($student_email_result['fail_count'] ?? 0),
        'hoc_email_sent' => !empty($hoc_email_result['success'])
      ]
    ]);
  }

  $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
  $bought_id = isset($_POST['bought_id']) ? (int)$_POST['bought_id'] : 0;

  if ($student_id <= 0) {
    respond_json(400, ['success' => false, 'message' => 'Invalid student']);
  }

  $row_sql = "SELECT
      mb.id AS bought_id,
      mb.grant_status,
      COALESCE(u.email, '') AS student_email
    FROM manuals_bought mb
    LEFT JOIN users u ON mb.buyer = u.id
    WHERE mb.status = 'successful'
      AND mb.manual_id = $manual_id
      AND mb.buyer = $student_id";

  if ($bought_id > 0) {
    $row_sql .= " AND mb.id = $bought_id";
  }

  $row_sql .= " ORDER BY mb.id DESC LIMIT 1";

  $row_result = mysqli_query($conn, $row_sql);
  $target_row = $row_result ? mysqli_fetch_assoc($row_result) : null;

  if (!$target_row) {
    respond_json(404, ['success' => false, 'message' => 'Student payment row not found']);
  }

  $target_bought_id = (int)$target_row['bought_id'];
  $was_granted = ((int)$target_row['grant_status']) === 1;

  $granted_now = 0;
  if (!$was_granted) {
    $single_grant_sql = "UPDATE manuals_bought
      SET grant_status = 1,
          export_id = NULL
      WHERE id = $target_bought_id
        AND grant_status = 0";

    if (!mysqli_query($conn, $single_grant_sql)) {
      respond_json(500, ['success' => false, 'message' => 'Failed to grant student record']);
    }

    $granted_now = mysqli_affected_rows($conn);
  }

  $single_notification = ['success' => false, 'message' => 'No new grants to notify'];
  if ($granted_now > 0) {
    $single_notification = try_send_notification(
      $conn,
      $admin_id,
      $student_id,
      'Material Collection Grant Approved',
      'Your payment has been granted for collection at the material collection center. If you did not initiate this request, please complain to our help desk immediately.',
      'material',
      [
        'action' => 'material_collection_grant',
        'mode' => 'single',
        'manual_id' => $manual_id,
        'bought_id' => $target_bought_id
      ]
    );
  }
  $single_email_result = ['success' => false, 'message' => 'No new grants to email'];
  if ($granted_now > 0) {
    $single_email_result = try_send_email(
      'Material Collection Grant Approved',
      'Your payment has been granted for collection at the material collection center.<br><br>If you did not initiate this request, please complain to our help desk immediately.',
      (string)($target_row['student_email'] ?? '')
    );
  }

  log_audit_event(
    $conn,
    $admin_id,
    'grant',
    'manual_single_row',
    $target_bought_id,
    [
      'mode' => 'single',
      'manual_id' => $manual_id,
      'student_id' => $student_id,
      'granted_now' => $granted_now,
      'student_notification_sent' => !empty($single_notification['success']),
      'student_email_sent' => !empty($single_email_result['success'])
    ]
  );

  respond_json(200, [
    'success' => true,
    'message' => $granted_now > 0 ? 'Student record granted successfully' : 'Student record was already granted',
    'result' => [
      'mode' => 'single',
      'manual_id' => $manual_id,
      'student_id' => $student_id,
      'bought_id' => $target_bought_id,
      'granted_now' => $granted_now,
      'student_notification_sent' => !empty($single_notification['success']),
      'student_email_sent' => !empty($single_email_result['success'])
    ]
  ]);
}

respond_json(400, ['success' => false, 'message' => 'Invalid action']);
