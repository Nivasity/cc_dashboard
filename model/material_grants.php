<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');

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

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$admin_id = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;

if ($admin_role !== 6 || $admin_id <= 0) {
  respond_json(403, ['success' => false, 'message' => 'Unauthorized access']);
}

$admin_scope_query = mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id LIMIT 1");
$admin_info = $admin_scope_query ? mysqli_fetch_assoc($admin_scope_query) : null;
$admin_school = isset($admin_info['school']) ? (int) $admin_info['school'] : 0;
$admin_faculty = isset($admin_info['faculty']) ? (int) $admin_info['faculty'] : 0;

if ($admin_school <= 0 || $admin_faculty <= 0) {
  respond_json(403, [
    'success' => false,
    'message' => 'Account scope is not configured. Assign both school and faculty for role 6.'
  ]);
}

$action = $_GET['action'] ?? '';
$scope_clause = "m.school_id = $admin_school AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
$material_status_clause = "(m.status = 'open' OR (m.status = 'closed' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)))";

if ($action === 'departments') {
  $dept_sql = "SELECT id, name
    FROM depts
    WHERE status = 'active'
      AND school_id = $admin_school
      AND faculty_id = $admin_faculty
    ORDER BY name ASC";

  $dept_result = mysqli_query($conn, $dept_sql);
  if (!$dept_result) {
    respond_json(500, ['success' => false, 'message' => 'Failed to load departments']);
  }

  $departments = [];
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
      AND $material_status_clause";

  if ($dept_id > 0) {
    $materials_sql .= " AND m.dept = $dept_id";
  }

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
  $lookup_value = trim((string)($_POST['lookup_value'] ?? ''));

  if ($manual_id <= 0) {
    respond_json(400, ['success' => false, 'message' => 'Select a material first']);
  }
  if ($lookup_value === '') {
    respond_json(400, ['success' => false, 'message' => 'Enter export code, student email, or matric number']);
  }

  if (!has_column($conn, 'manual_export_audits', 'from_bought_id') || !has_column($conn, 'manual_export_audits', 'to_bought_id')) {
    respond_json(500, [
      'success' => false,
      'message' => 'manual_export_audits schema is missing from_bought_id/to_bought_id columns'
    ]);
  }

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
    LIMIT 1";

  $manual_result = mysqli_query($conn, $manual_sql);
  $manual = $manual_result ? mysqli_fetch_assoc($manual_result) : null;

  if (!$manual) {
    respond_json(404, ['success' => false, 'message' => 'Selected material is unavailable for your scope']);
  }

  if ($dept_id > 0 && (int)$manual['dept'] !== $dept_id) {
    respond_json(400, ['success' => false, 'message' => 'Selected material does not belong to the selected department']);
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
    $export_stmt = mysqli_prepare(
      $conn,
      "SELECT
          e.id,
          e.code,
          e.manual_id,
          e.hoc_user_id,
          e.students_count,
          e.total_amount,
          e.from_bought_id,
          e.to_bought_id,
          e.grant_status,
          e.downloaded_at,
          e.granted_at,
          e.granted_by,
          COALESCE(u.first_name, '') AS hoc_first_name,
          COALESCE(u.last_name, '') AS hoc_last_name
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
    $from_bought_id = isset($export_row['from_bought_id']) ? (int)$export_row['from_bought_id'] : 0;
    $to_bought_id = isset($export_row['to_bought_id']) ? (int)$export_row['to_bought_id'] : 0;

    if ($from_bought_id <= 0 || $to_bought_id <= 0 || $to_bought_id < $from_bought_id) {
      respond_json(422, [
        'success' => false,
        'message' => 'Export range is invalid. Ensure from_bought_id/to_bought_id are stored for this export.'
      ]);
    }

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
        AND mb.id BETWEEN $from_bought_id AND $to_bought_id
      ORDER BY mb.grant_status DESC, mb.id ASC";

    $rows_result = mysqli_query($conn, $rows_sql);
    if (!$rows_result) {
      respond_json(500, ['success' => false, 'message' => 'Failed to load export rows']);
    }

    while ($row = mysqli_fetch_assoc($rows_result)) {
      $records[] = normalize_student_row($row);
    }

    if (count($records) === 0) {
      respond_json(404, ['success' => false, 'message' => 'No purchase rows found within export range']);
    }

    $lookup_context = [
      'mode' => 'export',
      'manual_id' => $manual_id,
      'export_id' => (int)$export_row['id'],
      'export_code' => (string)$export_row['code'],
      'from_bought_id' => $from_bought_id,
      'to_bought_id' => $to_bought_id,
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

  if (!has_column($conn, 'manual_export_audits', 'from_bought_id') || !has_column($conn, 'manual_export_audits', 'to_bought_id')) {
    respond_json(500, [
      'success' => false,
      'message' => 'manual_export_audits schema is missing from_bought_id/to_bought_id columns'
    ]);
  }

  $manual_scope_sql = "SELECT m.id
    FROM manuals m
    LEFT JOIN depts d ON m.dept = d.id
    WHERE m.id = $manual_id
      AND $scope_clause
    LIMIT 1";
  $manual_scope_result = mysqli_query($conn, $manual_scope_sql);
  if (!$manual_scope_result || mysqli_num_rows($manual_scope_result) === 0) {
    respond_json(403, ['success' => false, 'message' => 'Material is outside your scope']);
  }

  if ($mode === 'export') {
    $export_id = isset($_POST['export_id']) ? (int)$_POST['export_id'] : 0;
    if ($export_id <= 0) {
      respond_json(400, ['success' => false, 'message' => 'Invalid export']);
    }

    $export_sql = "SELECT
        e.id,
        e.code,
        e.from_bought_id,
        e.to_bought_id,
        e.grant_status
      FROM manual_export_audits e
      LEFT JOIN manuals m ON e.manual_id = m.id
      LEFT JOIN depts d ON m.dept = d.id
      WHERE e.id = $export_id
        AND e.manual_id = $manual_id
        AND $scope_clause
      LIMIT 1";

    $export_result = mysqli_query($conn, $export_sql);
    $export = $export_result ? mysqli_fetch_assoc($export_result) : null;

    if (!$export) {
      respond_json(403, ['success' => false, 'message' => 'Export not found or outside your scope']);
    }

    $from_bought_id = (int)($export['from_bought_id'] ?? 0);
    $to_bought_id = (int)($export['to_bought_id'] ?? 0);

    if ($from_bought_id <= 0 || $to_bought_id <= 0 || $to_bought_id < $from_bought_id) {
      respond_json(422, ['success' => false, 'message' => 'Export range is invalid']);
    }

    $count_sql = "SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN grant_status = 0 THEN 1 ELSE 0 END) AS pending_rows
      FROM manuals_bought
      WHERE status = 'successful'
        AND manual_id = $manual_id
        AND id BETWEEN $from_bought_id AND $to_bought_id";

    $count_result = mysqli_query($conn, $count_sql);
    $count_row = $count_result ? mysqli_fetch_assoc($count_result) : null;
    $total_rows = (int)($count_row['total_rows'] ?? 0);
    $pending_rows = (int)($count_row['pending_rows'] ?? 0);

    if ($total_rows <= 0) {
      respond_json(404, ['success' => false, 'message' => 'No purchase rows found for this export range']);
    }

    $granted_now = 0;
    if ($pending_rows > 0) {
      $grant_sql = "UPDATE manuals_bought
        SET grant_status = 1,
            export_id = $export_id
        WHERE status = 'successful'
          AND manual_id = $manual_id
          AND id BETWEEN $from_bought_id AND $to_bought_id
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
        AND id BETWEEN $from_bought_id AND $to_bought_id
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

    log_audit_event(
      $conn,
      $admin_id,
      'grant',
      'manual_export_rows',
      $export_id,
      [
        'mode' => 'export',
        'manual_id' => $manual_id,
        'from_bought_id' => $from_bought_id,
        'to_bought_id' => $to_bought_id,
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
        'already_granted' => max($total_rows - $granted_now, 0)
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
      mb.grant_status
    FROM manuals_bought mb
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
      'granted_now' => $granted_now
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
      'granted_now' => $granted_now
    ]
  ]);
}

respond_json(400, ['success' => false, 'message' => 'Invalid action']);
