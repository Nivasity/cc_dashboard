<?php
session_start();

require_once(__DIR__ . '/config.php');

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

function build_granted_date_clause($date_range, $start_date, $end_date) {
  switch ($date_range) {
    case '7':
    case '30':
    case '90':
      return "DATE(e.granted_at) >= DATE_SUB(CURDATE(), INTERVAL " . (int)$date_range . " DAY)";
    case 'custom':
      if (
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) === 1
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) === 1
        && $start_date <= $end_date
      ) {
        return "DATE(e.granted_at) BETWEEN '$start_date' AND '$end_date'";
      }
      return '';
    case 'all':
    default:
      return '';
  }
}

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int)$_SESSION['nivas_adminRole'] : 0;
$admin_id = isset($_SESSION['nivas_adminId']) ? (int)$_SESSION['nivas_adminId'] : 0;

if ($admin_id <= 0 || !in_array($admin_role, [1, 2, 3], true)) {
  respond_json(403, ['success' => false, 'message' => 'Unauthorized access']);
}

$action = $_GET['action'] ?? 'list';
$manuals_has_host_faculty = has_column($conn, 'manuals', 'host_faculty');
$exports_have_bought_ids = has_column($conn, 'manual_export_audits', 'bought_ids_json');
$manuals_bought_has_export_id = has_column($conn, 'manuals_bought', 'export_id');
$effective_faculty_expr = $manuals_has_host_faculty
  ? "COALESCE(NULLIF(m.host_faculty, 0), NULLIF(m.faculty, 0), d.faculty_id, 0)"
  : "COALESCE(NULLIF(m.faculty, 0), d.faculty_id, 0)";

if ($action === 'list') {
  $school = isset($_GET['school']) ? (int)$_GET['school'] : 0;
  $faculty = isset($_GET['faculty']) ? (int)$_GET['faculty'] : 0;
  $dept = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
  $date_range = trim((string)($_GET['date_range'] ?? '30'));
  $start_date = trim((string)($_GET['start_date'] ?? ''));
  $end_date = trim((string)($_GET['end_date'] ?? ''));

  $where = ["e.grant_status = 'granted'"];
  if ($school > 0) {
    $where[] = "m.school_id = $school";
  }
  if ($faculty > 0) {
    $where[] = "$effective_faculty_expr = $faculty";
  }
  if ($dept > 0) {
    $where[] = "m.dept = $dept";
  }

  $date_clause = build_granted_date_clause($date_range, $start_date, $end_date);
  if ($date_clause !== '') {
    $where[] = $date_clause;
  }

  $sql = "SELECT
      e.id,
      e.code,
      e.students_count,
      e.total_amount,
      e.granted_at,
      m.id AS manual_id,
      m.title,
      m.course_code,
      COALESCE(s.name, 'Unknown School') AS school_name,
      COALESCE(f.name, 'Unknown Faculty') AS faculty_name,
      COALESCE(d.name, 'Unknown Department') AS dept_name,
      TRIM(CONCAT(COALESCE(hoc.first_name, ''), ' ', COALESCE(hoc.last_name, ''))) AS hoc_name,
      TRIM(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))) AS granted_by_name
    FROM manual_export_audits e
    INNER JOIN manuals m ON m.id = e.manual_id
    LEFT JOIN schools s ON s.id = m.school_id
    LEFT JOIN depts d ON d.id = m.dept
    LEFT JOIN faculties f ON f.id = $effective_faculty_expr
    LEFT JOIN users hoc ON hoc.id = e.hoc_user_id
    LEFT JOIN admins a ON a.id = e.granted_by
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.granted_at DESC, e.id DESC";

  $result = mysqli_query($conn, $sql);
  if (!$result) {
    respond_json(500, ['success' => false, 'message' => 'Failed to load granted exports']);
  }

  $exports = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $exports[] = [
      'id' => (int)$row['id'],
      'code' => (string)$row['code'],
      'students_count' => (int)$row['students_count'],
      'total_amount' => (int)$row['total_amount'],
      'granted_at' => (string)$row['granted_at'],
      'manual_id' => (int)$row['manual_id'],
      'title' => (string)$row['title'],
      'course_code' => (string)$row['course_code'],
      'school_name' => (string)$row['school_name'],
      'faculty_name' => (string)$row['faculty_name'],
      'dept_name' => (string)$row['dept_name'],
      'hoc_name' => (string)$row['hoc_name'],
      'granted_by_name' => (string)$row['granted_by_name']
    ];
  }

  respond_json(200, [
    'success' => true,
    'exports' => $exports,
    'count' => count($exports)
  ]);
}

if ($action === 'students') {
  $export_id = isset($_GET['export_id']) ? (int)$_GET['export_id'] : 0;
  if ($export_id <= 0) {
    respond_json(400, ['success' => false, 'message' => 'Invalid export selected']);
  }

  $export_fields = [
    'e.id',
    'e.code',
    'e.manual_id',
    'e.students_count',
    'e.granted_at',
    'e.from_bought_id',
    'e.to_bought_id'
  ];
  if ($exports_have_bought_ids) {
    $export_fields[] = 'e.bought_ids_json';
  }

  $export_sql = "SELECT
      " . implode(",\n      ", $export_fields) . ",
      m.title,
      m.course_code,
      TRIM(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))) AS granted_by_name
    FROM manual_export_audits e
    INNER JOIN manuals m ON m.id = e.manual_id
    LEFT JOIN admins a ON a.id = e.granted_by
    WHERE e.id = $export_id
      AND e.grant_status = 'granted'
    LIMIT 1";

  $export_result = mysqli_query($conn, $export_sql);
  $export_row = $export_result ? mysqli_fetch_assoc($export_result) : null;
  if (!$export_row) {
    respond_json(404, ['success' => false, 'message' => 'Granted export not found']);
  }

  $bought_ids = $exports_have_bought_ids ? parse_bought_ids_json($export_row['bought_ids_json'] ?? null) : [];
  $student_where = [];

  if (count($bought_ids) > 0) {
    $student_where[] = 'mb.id IN (' . implode(',', $bought_ids) . ')';
  } elseif ((int)$export_row['from_bought_id'] > 0 && (int)$export_row['to_bought_id'] > 0) {
    $from_bought_id = (int)$export_row['from_bought_id'];
    $to_bought_id = (int)$export_row['to_bought_id'];
    $student_where[] = "mb.id BETWEEN $from_bought_id AND $to_bought_id";
    $student_where[] = 'mb.manual_id = ' . (int)$export_row['manual_id'];
  } elseif ($manuals_bought_has_export_id) {
    $student_where[] = 'mb.export_id = ' . $export_id;
  }

  if (count($student_where) === 0) {
    respond_json(200, [
      'success' => true,
      'export' => [
        'id' => (int)$export_row['id'],
        'code' => (string)$export_row['code'],
        'title' => (string)$export_row['title'],
        'course_code' => (string)$export_row['course_code'],
        'students_count' => (int)$export_row['students_count'],
        'granted_at' => (string)$export_row['granted_at'],
        'granted_by_name' => (string)$export_row['granted_by_name']
      ],
      'students' => []
    ]);
  }

  $student_sql = "SELECT
      mb.id AS bought_id,
      mb.price,
      mb.created_at,
      mb.grant_status,
      u.id AS student_id,
      u.first_name,
      u.last_name,
      u.email,
      u.matric_no,
      COALESCE(d.name, 'Unknown Department') AS dept_name,
      COALESCE(f.name, 'Unknown Faculty') AS faculty_name
    FROM manuals_bought mb
    INNER JOIN users u ON u.id = mb.buyer
    LEFT JOIN depts d ON d.id = u.dept
    LEFT JOIN faculties f ON f.id = d.faculty_id
    WHERE " . implode(' AND ', $student_where) . "
    ORDER BY u.first_name ASC, u.last_name ASC, mb.id ASC";

  $student_result = mysqli_query($conn, $student_sql);
  if (!$student_result) {
    respond_json(500, ['success' => false, 'message' => 'Failed to load exported students']);
  }

  $students = [];
  while ($row = mysqli_fetch_assoc($student_result)) {
    $students[] = [
      'bought_id' => (int)$row['bought_id'],
      'student_id' => (int)$row['student_id'],
      'full_name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
      'email' => (string)$row['email'],
      'matric_no' => (string)$row['matric_no'],
      'dept_name' => (string)$row['dept_name'],
      'faculty_name' => (string)$row['faculty_name'],
      'price' => (int)$row['price'],
      'created_at' => (string)$row['created_at'],
      'is_granted' => ((int)$row['grant_status']) === 1
    ];
  }

  respond_json(200, [
    'success' => true,
    'export' => [
      'id' => (int)$export_row['id'],
      'code' => (string)$export_row['code'],
      'title' => (string)$export_row['title'],
      'course_code' => (string)$export_row['course_code'],
      'students_count' => (int)$export_row['students_count'],
      'granted_at' => (string)$export_row['granted_at'],
      'granted_by_name' => (string)$export_row['granted_by_name']
    ],
    'students' => $students
  ]);
}

respond_json(400, ['success' => false, 'message' => 'Invalid action']);
?>