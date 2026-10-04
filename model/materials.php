<?php
session_start();
include('config.php');
include('functions.php');

// Configuration constants
define('UNSELECTED_VALUE', 0);
define('MAX_CODE_GENERATION_ATTEMPTS', 10);
define('CODE_LENGTH', 8);
define('COVERAGE_SCHOOL', 'School');
define('COVERAGE_FACULTY', 'Faculty');
define('COVERAGE_CUSTOM', 'Custom');
define('DEPT_OPTION_ALL_SCHOOL', '__all_school__');
define('DEPT_OPTION_ALL_FACULTY', '__all_faculty__');

/**
 * Build date filter SQL clause based on date range parameters for materials
 * 
 * @param mysqli $conn Database connection for escaping strings
 * @param string $date_range Date range type ('7', '30', '90', 'all', 'custom')
 * @param string $start_date Start date for custom range (Y-m-d format)
 * @param string $end_date End date for custom range (Y-m-d format)
 * @return string SQL WHERE clause fragment (with leading AND)
 */
function buildMaterialDateFilter($conn, $date_range, $start_date, $end_date) {
  $date_filter = "";
  
  if ($date_range === 'custom' && $start_date && $end_date) {
    // Validate date format
    $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
    $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
    
    if ($start_dt && $end_dt && $start_dt->format('Y-m-d') === $start_date && $end_dt->format('Y-m-d') === $end_date) {
      $start_date = mysqli_real_escape_string($conn, $start_date);
      $end_date = mysqli_real_escape_string($conn, $end_date);
      $date_filter = " AND m.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    }
  } elseif ($date_range !== 'all') {
    $days = intval($date_range);
    if ($days > 0) {
      $date_filter = " AND m.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    }
  }
  
  return $date_filter;
}

/**
 * Build SQL clause for faculty filter using host_faculty first, then faculty.
 *
 * @param int $faculty_id
 * @return string
 */
function buildMaterialFacultyFilterClause($faculty_id) {
  $faculty_id = intval($faculty_id);
  if ($faculty_id <= 0) {
    return '';
  }

  return " AND (
    CASE
      WHEN m.host_faculty IS NOT NULL AND m.host_faculty <> 0 THEN m.host_faculty
      ELSE IFNULL(m.faculty, 0)
    END = $faculty_id
  )";
}

/**
 * Build SQL clause for department filter.
 * Modes:
 *   - 'only_available': materials with Custom coverage explicitly targeted/posted for this department (excludes School/Faculty-wide coverage)
 *   - 'sold_to_dept' (default): materials buyable by students of this department (school-wide, faculty-wide, custom for dept, or bought by students of this dept)
 *
 * @param mysqli $conn
 * @param int $dept_id
 * @param string $dept_filter_type
 * @return string
 */
function buildMaterialDeptFilterClause($conn, $dept_id, $dept_filter_type = 'sold_to_dept') {
  $dept_id = intval($dept_id);
  if ($dept_id <= 0) {
    return '';
  }

  if ($dept_filter_type === 'only_available') {
    // Materials posted specifically for this department (Custom coverage only) —
    // excludes School/Faculty-wide materials whose expanded `depts` CSV also
    // happens to include this department's id.
    return " AND m.coverage = 'Custom' AND (
      (IFNULL(m.dept, 0) <> 0 AND m.dept = $dept_id)
      OR
      (IFNULL(m.dept, 0) = 0 AND m.depts IS NOT NULL AND m.depts <> '' AND FIND_IN_SET($dept_id, m.depts))
    )";
  }

  // Get department's faculty_id for faculty-wide coverage match
  $dept_faculty_id = 0;
  $dept_res = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $dept_id LIMIT 1");
  if ($dept_res && $dept_row = mysqli_fetch_assoc($dept_res)) {
    $dept_faculty_id = intval($dept_row['faculty_id'] ?? 0);
  }

  $faculty_match_clause = "";
  if ($dept_faculty_id > 0) {
    $faculty_match_clause = "OR (m.coverage = 'Faculty' AND (
      CASE WHEN m.host_faculty IS NOT NULL AND m.host_faculty <> 0 THEN m.host_faculty ELSE IFNULL(m.faculty, 0) END = $dept_faculty_id
    ))";
  }

  return " AND (
    (IFNULL(m.dept, 0) <> 0 AND m.dept = $dept_id)
    OR (IFNULL(m.dept, 0) = 0 AND m.depts IS NOT NULL AND m.depts <> '' AND FIND_IN_SET($dept_id, m.depts))
    OR (m.coverage = 'School')
    $faculty_match_clause
    OR EXISTS (
      SELECT 1 FROM manuals_bought mb_dept
      JOIN users u_dept ON mb_dept.buyer = u_dept.id
      WHERE mb_dept.manual_id = m.id AND mb_dept.status = 'successful' AND u_dept.dept = $dept_id
    )
  )";
}

/**
 * Build SQL clause for filtering materials by creator type.
 *
 * @param string $creator_type
 * @return string
 */
function buildMaterialCreatorFilterClause($creator_type) {
  if ($creator_type === 'users') {
    return " AND IFNULL(m.admin_id, 0) = 0 AND IFNULL(m.user_id, 0) <> 0";
  }

  return " AND IFNULL(m.admin_id, 0) <> 0";
}

/**
 * Parse comma-separated department IDs into unique integer array.
 *
 * @param string|null $csv
 * @return array<int>
 */
function parseDeptCsv($csv) {
  if ($csv === null) {
    return array();
  }

  $parts = explode(',', (string)$csv);
  $ids = array();
  foreach ($parts as $part) {
    $id = intval(trim($part));
    if ($id > 0) {
      $ids[$id] = $id;
    }
  }
  return array_values($ids);
}

/**
 * Convert array of department IDs to normalized CSV string.
 *
 * @param array<int> $dept_ids
 * @return string
 */
function toDeptCsv($dept_ids) {
  $normalized = array();
  foreach ($dept_ids as $id) {
    $int_id = intval($id);
    if ($int_id > 0) {
      $normalized[$int_id] = $int_id;
    }
  }
  ksort($normalized);
  return implode(',', array_values($normalized));
}

/**
 * Get active department IDs by school.
 *
 * @param mysqli $conn
 * @param int $school_id
 * @return array<int>
 */
function getActiveDeptIdsBySchool($conn, $school_id) {
  static $cache = array();
  $cache_key = 'school_' . intval($school_id);
  if (isset($cache[$cache_key])) {
    return $cache[$cache_key];
  }

  $school_id = intval($school_id);
  if ($school_id <= 0) {
    return array();
  }

  $ids = array();
  $query = mysqli_query($conn, "SELECT id FROM depts WHERE status = 'active' AND school_id = $school_id");
  if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
      $id = intval($row['id'] ?? 0);
      if ($id > 0) {
        $ids[] = $id;
      }
    }
  }

  $cache[$cache_key] = $ids;
  return $ids;
}

/**
 * Get active department IDs by faculty within a school.
 *
 * @param mysqli $conn
 * @param int $school_id
 * @param int $faculty_id
 * @return array<int>
 */
function getActiveDeptIdsByFaculty($conn, $school_id, $faculty_id) {
  static $cache = array();
  $cache_key = 'school_' . intval($school_id) . '_faculty_' . intval($faculty_id);
  if (isset($cache[$cache_key])) {
    return $cache[$cache_key];
  }

  $school_id = intval($school_id);
  $faculty_id = intval($faculty_id);
  if ($school_id <= 0 || $faculty_id <= 0) {
    return array();
  }

  $ids = array();
  $query = mysqli_query($conn, "SELECT id FROM depts WHERE status = 'active' AND school_id = $school_id AND faculty_id = $faculty_id");
  if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
      $id = intval($row['id'] ?? 0);
      if ($id > 0) {
        $ids[] = $id;
      }
    }
  }

  $cache[$cache_key] = $ids;
  return $ids;
}

/**
 * Resolve coverage and concrete department IDs from posted department selections.
 *
 * @param mysqli $conn
 * @param int $school_id
 * @param int $faculty_id
 * @param array $raw_selected
 * @return array{success:bool,message:string,coverage:string,dept_ids:array<int>,dept_csv:string}
 */
function resolveCoverageFromPostedDepartments($conn, $school_id, $faculty_id, $raw_selected) {
  $selected = array();
  if (!is_array($raw_selected)) {
    $raw_selected = array($raw_selected);
  }
  foreach ($raw_selected as $value) {
    $selected[] = trim((string)$value);
  }

  $has_all_school = in_array(DEPT_OPTION_ALL_SCHOOL, $selected, true);
  $has_all_faculty = in_array(DEPT_OPTION_ALL_FACULTY, $selected, true);

  $coverage = COVERAGE_CUSTOM;
  $dept_ids = array();

  if ($has_all_school) {
    $coverage = COVERAGE_SCHOOL;
    $dept_ids = getActiveDeptIdsBySchool($conn, $school_id);
  } elseif ($has_all_faculty) {
    if (intval($faculty_id) <= 0) {
      return array(
        'success' => false,
        'message' => 'Select a faculty before choosing all faculty departments',
        'coverage' => COVERAGE_CUSTOM,
        'dept_ids' => array(),
        'dept_csv' => ''
      );
    }
    $coverage = COVERAGE_FACULTY;
    $dept_ids = getActiveDeptIdsByFaculty($conn, $school_id, $faculty_id);
  } else {
    foreach ($selected as $value) {
      $id = intval($value);
      if ($id > 0) {
        $dept_ids[$id] = $id;
      }
    }
    $dept_ids = array_values($dept_ids);
  }

  if (count($dept_ids) === 0) {
    return array(
      'success' => false,
      'message' => 'Select at least one department scope',
      'coverage' => COVERAGE_CUSTOM,
      'dept_ids' => array(),
      'dept_csv' => ''
    );
  }

  return array(
    'success' => true,
    'message' => '',
    'coverage' => $coverage,
    'dept_ids' => $dept_ids,
    'dept_csv' => toDeptCsv($dept_ids)
  );
}

/**
 * Resolve effective coverage/departments for listing and editing (supports legacy rows).
 *
 * @param mysqli $conn
 * @param array $row
 * @return array{coverage:string,coverage_label:string,dept_ids:array<int>,dept_count:int}
 */
function resolveMaterialCoverage($conn, $row) {
  $dept_ids = parseDeptCsv($row['depts'] ?? null);
  $coverage = trim((string)($row['coverage'] ?? ''));
  if ($coverage === '') {
    $coverage = COVERAGE_CUSTOM;
  }

  $legacy_school = intval($row['school_id'] ?? 0);
  $legacy_faculty = intval($row['faculty'] ?? 0);
  $legacy_dept = intval($row['dept'] ?? 0);

  if (count($dept_ids) === 0) {
    if ($legacy_dept > 0) {
      $dept_ids = array($legacy_dept);
      $coverage = COVERAGE_CUSTOM;
    } elseif ($legacy_faculty > 0) {
      $dept_ids = getActiveDeptIdsByFaculty($conn, $legacy_school, $legacy_faculty);
      $coverage = COVERAGE_FACULTY;
    } else {
      $dept_ids = getActiveDeptIdsBySchool($conn, $legacy_school);
      $coverage = COVERAGE_SCHOOL;
    }
  }

  if ($coverage !== COVERAGE_SCHOOL && $coverage !== COVERAGE_FACULTY && $coverage !== COVERAGE_CUSTOM) {
    $coverage = COVERAGE_CUSTOM;
  }

  $dept_count = count($dept_ids);
  if ($coverage === COVERAGE_CUSTOM) {
    $coverage_label = $dept_count . ' Department' . ($dept_count === 1 ? '' : 's');
  } elseif ($coverage === COVERAGE_FACULTY) {
    $coverage_label = 'Faculty';
  } else {
    $coverage_label = 'School';
  }

  return array(
    'coverage' => $coverage,
    'coverage_label' => $coverage_label,
    'dept_ids' => $dept_ids,
    'dept_count' => $dept_count
  );
}

$statusRes = 'failed';
$messageRes = '';
$faculties = $departments = $materials = null;
$restrict_faculty = false;

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$admin_id = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;
$admin_school = $admin_faculty = UNSELECTED_VALUE;
$admin_exists = false;
if ($admin_id > 0) {
  $stmt = mysqli_prepare($conn, "SELECT school, faculty FROM admins WHERE id = ?");
  mysqli_stmt_bind_param($stmt, 'i', $admin_id);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $info = mysqli_fetch_assoc($result);
  if ($info) {
    $admin_exists = true;
    $admin_school = intval($info['school'] ?? UNSELECTED_VALUE);
    $admin_faculty = intval($info['faculty'] ?? UNSELECTED_VALUE);
  }
  mysqli_stmt_close($stmt);
}

// Every request (lists, CSV export, lookups and changes) needs a signed-in admin.
// Without this, material lists with revenue and the CSV (names, matric numbers) were public.
if (!$admin_exists) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'error',
    'message' => 'Your admin session has expired. Sign in again and retry.',
  ]);
  exit;
}

function ensureValidMaterialAdminSession($admin_id, $admin_exists, &$statusRes, &$messageRes) {
  if (intval($admin_id) > 0 && $admin_exists) {
    return true;
  }

  $statusRes = 'error';
  $messageRes = 'Your admin session is invalid. Sign in again and retry.';
  return false;
}

// Semester tagging (nivasity docs/WHITE_LABEL_TRANSITION_PLAN.md, section 4).
// Every material belongs to one semester; students only see the school's current semester.
// Untagged (NULL) materials are legacy rows and cannot be (re)opened until a semester is set.
define('MATERIAL_STATUS_AWAITING', 'awaiting_confirmation');

function materialSemesterReady($conn) {
  static $ready = null;
  if ($ready !== null) {
    return $ready;
  }
  $manualsCol = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'semester'");
  $schoolsCol = mysqli_query($conn, "SHOW COLUMNS FROM schools LIKE 'current_semester'");
  $confirmedCol = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'confirmed_by'");
  $ready = $manualsCol && mysqli_num_rows($manualsCol) > 0
    && $schoolsCol && mysqli_num_rows($schoolsCol) > 0
    && $confirmedCol && mysqli_num_rows($confirmedCol) > 0;
  return $ready;
}

function normalizeMaterialSemester($value) {
  $semester = intval($value);
  return ($semester === 1 || $semester === 2) ? $semester : 0;
}

function materialSemesterLabel($semester) {
  $semester = normalizeMaterialSemester($semester);
  if ($semester === 1) return 'First Semester';
  if ($semester === 2) return 'Second Semester';
  return 'No semester set';
}

// Academic session (year) on top of the semester: a material's period is session + semester
// (nivasity sql/add_academic_session.sql). Students only see the school's current period.
function materialSessionReady($conn) {
  static $ready = null;
  if ($ready !== null) {
    return $ready;
  }
  $a = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'session'");
  $b = mysqli_query($conn, "SHOW COLUMNS FROM schools LIKE 'current_session'");
  $c = mysqli_query($conn, "SHOW TABLES LIKE 'academic_periods'");
  $ready = materialSemesterReady($conn) && $a && mysqli_num_rows($a) > 0 && $b && mysqli_num_rows($b) > 0 && $c && mysqli_num_rows($c) > 0;
  return $ready;
}

function normalizeMaterialSession($value) {
  $value = trim((string) $value);
  if (!preg_match('#^(\d{4})/(\d{4})$#', $value, $m) || intval($m[2]) !== intval($m[1]) + 1) {
    return '';
  }
  return $value;
}

function materialSchoolCurrentSession($conn, $school_id) {
  $school_id = intval($school_id);
  if ($school_id <= 0 || !materialSessionReady($conn)) {
    return '';
  }
  $res = mysqli_query($conn, "SELECT current_session FROM schools WHERE id = $school_id LIMIT 1");
  $row = $res ? mysqli_fetch_assoc($res) : null;
  return $row ? normalizeMaterialSession($row['current_session'] ?? '') : '';
}

// First -> Second of the same session; Second -> First of the next session.
function materialNextPeriod($session, $semester) {
  if (intval($semester) === 1) {
    return [$session, 2];
  }
  if (preg_match('#^(\d{4})/(\d{4})$#', (string) $session, $m)) {
    return [(intval($m[1]) + 1) . '/' . (intval($m[2]) + 1), 1];
  }
  return [$session, 1];
}

function materialPeriodLabel($semester, $session) {
  $label = materialSemesterLabel($semester);
  $session = normalizeMaterialSession($session);
  return ($session !== '' && normalizeMaterialSemester($semester) > 0) ? $label . ' ' . $session : $label;
}

// Session posted with a material, or the school's current session when none was chosen.
function materialPostedSession($conn, $school_id) {
  $posted = normalizeMaterialSession($_POST['session'] ?? '');
  return $posted !== '' ? $posted : materialSchoolCurrentSession($conn, $school_id);
}

function materialSchoolCurrentSemester($conn, $school_id) {
  $school_id = intval($school_id);
  if ($school_id <= 0 || !materialSemesterReady($conn)) {
    return 0;
  }
  $res = mysqli_query($conn, "SELECT current_semester FROM schools WHERE id = $school_id LIMIT 1");
  $row = $res ? mysqli_fetch_assoc($res) : null;
  return $row ? (normalizeMaterialSemester($row['current_semester']) ?: 1) : 0;
}

// Faculty-scoped school admins cannot switch the whole school's semester.
function adminCanSwitchSchoolSemester($admin_role, $admin_school, $admin_faculty, $school_id) {
  if (intval($admin_role) != 5) {
    return true;
  }
  return intval($admin_school) === intval($school_id) && intval($admin_faculty) == 0;
}

// Same scope rule as the open/close toggle.
function adminCanManageMaterial($admin_role, $admin_school, $admin_faculty, $manual) {
  if (intval($admin_role) != 5) {
    return true;
  }
  if (intval($manual['school_id']) !== intval($admin_school)) {
    return false;
  }
  return intval($admin_faculty) == 0 || intval($manual['faculty']) === intval($admin_faculty);
}

function buildMaterialSemesterFilterClause($conn, $semester_state) {
  if (!materialSemesterReady($conn)) {
    return '';
  }
  switch ($semester_state) {
    case '1':
    case '2':
      return ' AND m.semester = ' . intval($semester_state);
    case 'awaiting':
      return " AND m.status = '" . MATERIAL_STATUS_AWAITING . "'";
    case 'untagged':
      return ' AND m.semester IS NULL';
    default:
      return '';
  }
}

$semester_select_sql = materialSemesterReady($conn)
  ? 'm.semester, m.confirmed_at, sch.current_semester AS school_current_semester,'
    . (materialSessionReady($conn) ? ' m.session, sch.current_session AS school_current_session,' : ' NULL AS session, NULL AS school_current_session,')
  : 'NULL AS semester, NULL AS confirmed_at, NULL AS school_current_semester, NULL AS session, NULL AS school_current_session,';
$semester_join_sql = materialSemesterReady($conn) ? ' LEFT JOIN schools sch ON sch.id = m.school_id' : '';
$semester_state = (string) ($_GET['semester_state'] ?? '');

// Handle CSV download for filtered materials
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  $dept_filter_type = $_GET['dept_filter_type'] ?? 'sold_to_dept';
  $creator_type = $_GET['creator_type'] ?? 'admins';
  if ($creator_type !== 'users') {
    $creator_type = 'admins';
  }
  $date_range = $_GET['date_range'] ?? 'all';
  $start_date = $_GET['start_date'] ?? '';
  $end_date = $_GET['end_date'] ?? '';
  
  if ($admin_role == 5) {
    $school = $admin_school;
    if ($admin_faculty != 0) { $faculty = $admin_faculty; }
  }

  $material_sql = "SELECT m.id, m.code, m.title, m.course_code, m.price, m.level, m.user_id, m.admin_id, m.school_id, m.faculty, m.host_faculty, m.dept, m.depts, m.coverage, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, COUNT(DISTINCT b.ref_id) AS purchase_count, m.status AS status, m.status AS db_status, $semester_select_sql u.first_name AS user_first_name, u.last_name AS user_last_name, u.matric_no, a.first_name AS admin_first_name, a.last_name AS admin_last_name, ar.name AS admin_role, f.name AS faculty_name, d.name AS dept_name FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN users u ON m.user_id = u.id LEFT JOIN admins a ON m.admin_id = a.id LEFT JOIN admin_roles ar ON a.role = ar.id LEFT JOIN faculties f ON m.faculty = f.id LEFT JOIN depts d ON m.dept = d.id$semester_join_sql WHERE 1=1";
  if ($admin_role == 5) {
    $material_sql .= " AND m.school_id = $admin_school";
    if ($admin_faculty != 0) {
      $material_sql .= buildMaterialFacultyFilterClause($admin_faculty);
    }
  } else {
    if ($school > 0) {
      $material_sql .= " AND m.school_id = $school";
    }
    if ($faculty != 0) {
      $material_sql .= buildMaterialFacultyFilterClause($faculty);
    }
  }
  if ($dept > 0) {
    $material_sql .= buildMaterialDeptFilterClause($conn, $dept, $dept_filter_type);
  }
  $material_sql .= buildMaterialCreatorFilterClause($creator_type);
  $material_sql .= buildMaterialSemesterFilterClause($conn, $semester_state);
  if ($creator_type === 'users') {
    $material_sql .= buildMaterialDateFilter($conn, $date_range, $start_date, $end_date);
  }
  $material_sql .= " GROUP BY m.id ORDER BY m.created_at DESC";
  $mat_query = mysqli_query($conn, $material_sql);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="materials_' . date('Ymd_His') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Title (Course Code)', 'Posted By', 'Role/Matric No', 'Unit Price', 'Revenue', 'Qty Sold', 'Coverage', 'Semester', 'Availability']);
  while ($row = mysqli_fetch_assoc($mat_query)) {
    $coverage_info = resolveMaterialCoverage($conn, $row);
    // Use admin data if admin_id exists and is not 0, otherwise use user data
    if (!empty($row['admin_id']) && $row['admin_id'] != 0) {
      $posted_by = trim(($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? ''));
      $role_or_matric = $row['admin_role'] ?? '';
    } else {
      $posted_by = trim(($row['user_first_name'] ?? '') . ' ' . ($row['user_last_name'] ?? ''));
      $role_or_matric = $row['matric_no'] ?? '';
    }
    fputcsv($out, [
      $row['title'] . ' (' . $row['course_code'] . ')',
      $posted_by,
      $role_or_matric,
      $row['price'],
      $row['revenue'],
      $row['qty_sold'],
      $coverage_info['coverage_label'],
      materialPeriodLabel($row['semester'] ?? 0, $row['session'] ?? ''),
      $row['status']
    ]);
  }
  fclose($out);
  exit;
}

if(isset($_GET['fetch'])){
  $fetch = $_GET['fetch'];
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  if ($admin_role == 5) {
    $school = $admin_school;
    if ($admin_faculty != 0) {
      $faculty = $admin_faculty;
    }
  }

  if($fetch == 'faculties'){
    if($admin_role == 5){
      if($admin_faculty != 0){
        $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND id = $admin_faculty");
        $restrict_faculty = true;
      } else {
        $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
      }
    } else {
      $fac_query = ($school > 0) ?
        mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $school ORDER BY name") :
        mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
    }
    $faculties = array();
    while($row = mysqli_fetch_assoc($fac_query)){
      $faculties[] = $row;
    }
    $statusRes = 'success';
  }

  if($fetch == 'departments'){
    $for_material = intval($_GET['for_material'] ?? 0);
    if ($for_material === 1) {
      // Material form: always return school departments, but prioritize selected faculty at the top.
      if ($admin_role == 5) {
        $school = $admin_school;
        if ($admin_faculty != 0) {
          $faculty = $admin_faculty;
        }
      }

      if ($school > 0) {
        if ($faculty > 0) {
          $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' AND school_id = $school ORDER BY (faculty_id = $faculty) DESC, name ASC");
        } else {
          $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' AND school_id = $school ORDER BY name ASC");
        }
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' ORDER BY name ASC");
      }
    } else if($admin_role == 5){
      if($admin_faculty != 0){
        $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' AND faculty_id = $admin_faculty ORDER BY name");
      } elseif($faculty != 0){
        $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' AND faculty_id = $faculty AND school_id = $admin_school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
      }
    } else {
      if($faculty != 0){
        $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' AND faculty_id = $faculty ORDER BY name");
      } elseif($school > 0){
        $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' AND school_id = $school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name, faculty_id FROM depts WHERE status = 'active' ORDER BY name");
      }
    }
    $departments = array();
    while($row = mysqli_fetch_assoc($dept_query)){
      $departments[] = $row;
    }
    $statusRes = 'success';
  }

  if($fetch == 'materials'){
    $dept_filter_type = $_GET['dept_filter_type'] ?? 'sold_to_dept';
    $creator_type = $_GET['creator_type'] ?? 'admins';
    if ($creator_type !== 'users') {
      $creator_type = 'admins';
    }
    $date_range = $_GET['date_range'] ?? 'all';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    $material_sql = "SELECT m.id, m.code, m.title, m.course_code, m.price, m.level, m.user_id, m.admin_id, m.school_id, m.faculty, m.host_faculty, m.dept, m.depts, m.coverage, IFNULL(SUM(b.price),0) AS revenue, COUNT(b.manual_id) AS qty_sold, COUNT(DISTINCT b.ref_id) AS purchase_count, m.status AS status, m.status AS db_status, $semester_select_sql u.first_name AS user_first_name, u.last_name AS user_last_name, u.matric_no, a.first_name AS admin_first_name, a.last_name AS admin_last_name, ar.name AS admin_role, f.name AS faculty_name, d.name AS dept_name FROM manuals m LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status='successful' LEFT JOIN users u ON m.user_id = u.id LEFT JOIN admins a ON m.admin_id = a.id LEFT JOIN admin_roles ar ON a.role = ar.id LEFT JOIN faculties f ON m.faculty = f.id LEFT JOIN depts d ON m.dept = d.id$semester_join_sql WHERE 1=1";
    if($admin_role == 5){
      $material_sql .= " AND m.school_id = $admin_school";
      if($admin_faculty != 0){
        $material_sql .= buildMaterialFacultyFilterClause($admin_faculty);
      }
    } else {
      if($school > 0){
        $material_sql .= " AND m.school_id = $school";
      }
      if($faculty != 0){
        $material_sql .= buildMaterialFacultyFilterClause($faculty);
      }
    }
    if($dept > 0){
      $material_sql .= buildMaterialDeptFilterClause($conn, $dept, $dept_filter_type);
    }
    $material_sql .= buildMaterialCreatorFilterClause($creator_type);
    $material_sql .= buildMaterialSemesterFilterClause($conn, $semester_state);
    if ($creator_type === 'users') {
      $material_sql .= buildMaterialDateFilter($conn, $date_range, $start_date, $end_date);
    }
    $material_sql .= " GROUP BY m.id ORDER BY m.created_at DESC";
    $mat_query = mysqli_query($conn, $material_sql);
    
    // Check for SQL errors
    if (!$mat_query) {
      $statusRes = 'error';
      $messageRes = 'Database query failed: ' . mysqli_error($conn);
      error_log("Materials query error: " . mysqli_error($conn));
      error_log("SQL: " . $material_sql);
    } else {
      $materials = array();
      $total_amount_paid = 0;
      $total_qty_sold = 0;
      $max_qty_sold = 0;
      $best_selling_code = 'N/A';

      while($row = mysqli_fetch_assoc($mat_query)){
        $coverage_info = resolveMaterialCoverage($conn, $row);

        // Use admin data if admin_id exists and is not 0, otherwise use user data
        if (!empty($row['admin_id']) && $row['admin_id'] != 0) {
          $posted_by = trim(($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? ''));
          $role_or_matric = $row['admin_role'] ?? '';
          $is_admin = true;
        } else {
          $posted_by = trim(($row['user_first_name'] ?? '') . ' ' . ($row['user_last_name'] ?? ''));
          $role_or_matric = $row['matric_no'] ?? '';
          $is_admin = false;
        }
        
        $revenue = (float) ($row['revenue'] ?? 0);
        $qty_sold = (int) ($row['qty_sold'] ?? 0);
        $total_amount_paid += $revenue;
        $total_qty_sold += $qty_sold;

        if ($qty_sold > 0 && $qty_sold > $max_qty_sold) {
          $max_qty_sold = $qty_sold;
          $best_selling_code = $row['course_code'] ?? 'N/A';
        }

        $materials[] = array(
          'id' => $row['id'],
          'code' => $row['code'],
          'title' => $row['title'],
          'course_code' => $row['course_code'],
          'price' => $row['price'],
          'revenue' => $row['revenue'],
          'qty_sold' => $row['qty_sold'],
          'purchase_count' => $row['purchase_count'] ?? 0,
          'status' => $row['status'],
          'db_status' => $row['db_status'],
          'posted_by' => $posted_by,
          'role_or_matric' => $role_or_matric,
          'is_admin' => $is_admin,
          'faculty_name' => $row['faculty_name'] ?? '',
          'dept_name' => $row['dept_name'] ?? '',
          'level' => $row['level'] ?? null,
          'school_id' => $row['school_id'] ?? null,
          'faculty_id' => $row['faculty'] ?? null,
          'host_faculty' => $row['host_faculty'] ?? null,
          'dept_id' => $row['dept'] ?? null,
          'dept_ids' => $coverage_info['dept_ids'],
          'depts_csv' => toDeptCsv($coverage_info['dept_ids']),
          'coverage' => $coverage_info['coverage'],
          'coverage_label' => $coverage_info['coverage_label'],
          'dept_count' => $coverage_info['dept_count'],
          'semester' => normalizeMaterialSemester($row['semester'] ?? 0),
          'semester_label' => materialPeriodLabel($row['semester'] ?? 0, $row['session'] ?? ''),
          'school_current_semester' => normalizeMaterialSemester($row['school_current_semester'] ?? 0),
          'session' => normalizeMaterialSession($row['session'] ?? ''),
          'school_current_session' => normalizeMaterialSession($row['school_current_session'] ?? ''),
          'confirmed_at' => $row['confirmed_at'] ?? null
        );
      }

      $stats = array(
        'total_amount_paid' => $total_amount_paid,
        'total_count' => count($materials),
        'total_qty_sold' => $total_qty_sold,
        'best_selling_code' => $best_selling_code,
        'best_selling_qty' => $max_qty_sold
      );
      $statusRes = 'success';
    }
  }
  
  // Current semester of a school plus what a switch would do
  if($fetch == 'school_semester'){
    if (!materialSemesterReady($conn)) {
      echo json_encode(['status' => 'success', 'semester_ready' => false]);
      exit;
    }
    if ($school <= 0) {
      echo json_encode(['status' => 'error', 'message' => 'Select a school']);
      exit;
    }

    $current = materialSchoolCurrentSemester($conn, $school);
    $current_session = materialSchoolCurrentSession($conn, $school);
    list($next_session, $next) = materialNextPeriod($current_session, $current);
    $awaiting = MATERIAL_STATUS_AWAITING;
    if (materialSessionReady($conn)) {
      $ns = mysqli_real_escape_string($conn, $next_session);
      $in_next = "(semester = $next AND session = '$ns')";
      $counts_res = mysqli_query($conn, "SELECT
          SUM(status = 'open' AND NOT $in_next) AS to_awaiting,
          SUM(status = 'open' AND $in_next) AS going_live,
          SUM(status = '$awaiting') AS awaiting,
          SUM(status = '$awaiting' AND $in_next) AS awaiting_next,
          SUM(status <> 'closed' AND (semester IS NULL OR session IS NULL)) AS untagged
        FROM manuals WHERE school_id = $school");
    } else {
      $counts_res = mysqli_query($conn, "SELECT
          SUM(status = 'open' AND (semester = $current OR semester IS NULL)) AS to_awaiting,
          SUM(status = 'open' AND semester = $next) AS going_live,
          SUM(status = '$awaiting') AS awaiting,
          SUM(status = '$awaiting' AND semester = $next) AS awaiting_next,
          SUM(status <> 'closed' AND semester IS NULL) AS untagged
        FROM manuals WHERE school_id = $school");
    }
    $history = [];
    if (materialSessionReady($conn)) {
      $hist_res = mysqli_query($conn, "SELECT p.session, p.semester, p.started_at,
          TRIM(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))) AS started_by
        FROM academic_periods p LEFT JOIN admins a ON a.id = p.started_by
        WHERE p.school_id = $school ORDER BY p.started_at DESC, p.id DESC LIMIT 20");
      while ($hist_res && ($h = mysqli_fetch_assoc($hist_res))) {
        $history[] = [
          'label' => materialPeriodLabel($h['semester'], $h['session']),
          'started_at' => $h['started_at'],
          'started_by' => $h['started_by'] !== '' ? $h['started_by'] : 'Initial setup',
        ];
      }
    }
    $counts = $counts_res ? mysqli_fetch_assoc($counts_res) : [];

    echo json_encode([
      'status' => 'success',
      'semester_ready' => true,
      'school_id' => $school,
      'current_semester' => $current,
      'current_session' => $current_session,
      'current_label' => materialPeriodLabel($current, $current_session),
      'next_semester' => $next,
      'next_session' => $next_session,
      'next_label' => materialPeriodLabel($next, $next_session),
      'session_ready' => materialSessionReady($conn),
      'history' => $history,
      'can_switch' => adminCanSwitchSchoolSemester($admin_role, $admin_school, $admin_faculty, $school),
      'counts' => [
        'to_awaiting' => intval($counts['to_awaiting'] ?? 0),
        'going_live' => intval($counts['going_live'] ?? 0),
        'awaiting' => intval($counts['awaiting'] ?? 0),
        'awaiting_next' => intval($counts['awaiting_next'] ?? 0),
        'untagged' => intval($counts['untagged'] ?? 0)
      ]
    ]);
    exit;
  }

  // Fetch human-readable names for edit mode (school, faculties, dept, level)
  if($fetch == 'material_names'){
    $school_id = intval($_GET['school_id'] ?? 0);
    $host_faculty_id = intval($_GET['host_faculty_id'] ?? 0);
    $faculty_id = intval($_GET['faculty_id'] ?? 0);
    $dept_id = intval($_GET['dept_id'] ?? 0);
    $level = intval($_GET['level'] ?? 0);
    
    $school_name = 'Unknown School';
    $host_faculty_name = 'Unknown Faculty';
    $faculty_name = 'Unknown Faculty';
    $dept_name = 'All Departments';
    $level_text = 'All Levels';
    
    // Fetch school name
    if ($school_id > 0) {
      $school_res = mysqli_query($conn, "SELECT name FROM schools WHERE id = $school_id");
      if ($school_row = mysqli_fetch_assoc($school_res)) {
        $school_name = $school_row['name'];
      }
    }
    
    // Fetch host faculty name
    if ($host_faculty_id > 0) {
      $fac_res = mysqli_query($conn, "SELECT name FROM faculties WHERE id = $host_faculty_id");
      if ($fac_row = mysqli_fetch_assoc($fac_res)) {
        $host_faculty_name = $fac_row['name'];
      }
    }
    
    // Fetch faculty name (who can buy)
    if ($faculty_id > 0) {
      $fac_res = mysqli_query($conn, "SELECT name FROM faculties WHERE id = $faculty_id");
      if ($fac_row = mysqli_fetch_assoc($fac_res)) {
        $faculty_name = $fac_row['name'];
      }
    }
    
    // Fetch department name
    if ($dept_id > 0) {
      $dept_res = mysqli_query($conn, "SELECT name FROM depts WHERE id = $dept_id");
      if ($dept_row = mysqli_fetch_assoc($dept_res)) {
        $dept_name = $dept_row['name'];
      }
    }
    
    // Format level text
    if ($level > 0) {
      $level_text = $level . ' Level';
    }
    
    echo json_encode([
      'status' => 'success',
      'school_name' => $school_name,
      'host_faculty_name' => $host_faculty_name,
      'faculty_name' => $faculty_name,
      'dept_name' => $dept_name,
      'level_text' => $level_text
    ]);
    exit;
  }
}

if(isset($_POST['toggle_id'])){
  if (!ensureValidMaterialAdminSession($admin_id, $admin_exists, $statusRes, $messageRes)) {
    goto materials_response;
  }

  $id = intval($_POST['toggle_id']);
  $manual_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT m.status, m.school_id, m.faculty, m.dept, m.depts, m.coverage, m.title, m.course_code FROM manuals m WHERE m.id = $id"));
  if($manual_res){
    if($admin_role == 5 && ($manual_res['school_id'] != $admin_school || ($admin_faculty != 0 && $manual_res['faculty'] != $admin_faculty))){
      $statusRes = 'error';
      $messageRes = 'Unauthorized';
    } else {
      $current_status = (string) $manual_res['status'];
      $reopen_blocked = materialSemesterReady($conn) && $current_status !== 'open' && $current_status !== MATERIAL_STATUS_AWAITING;
      if ($reopen_blocked) {
        // Reopening must go through Confirm so the price and semester are checked.
        $statusRes = 'error';
        $messageRes = 'Use "Confirm & Open" to reopen this material after checking its price and semester.';
        goto materials_response;
      }
      $new_status = ($current_status === 'open' || $current_status === MATERIAL_STATUS_AWAITING) ? 'closed' : 'open';
      mysqli_query($conn, "UPDATE manuals SET status = '$new_status' WHERE id = $id");
      if(mysqli_affected_rows($conn) > 0){
        $statusRes = 'success';
        $messageRes = 'Material status updated';
        
        // Send notification when material is closed (awaiting materials were already hidden)
        if ($new_status === 'closed' && $admin_id) {
          if ($current_status === 'open') {
            $coverage_info = resolveMaterialCoverage($conn, $manual_res);
            require_once __DIR__ . '/notification_helpers.php';
            notifyCourseMaterialClosed(
              $conn,
              $admin_id,
              $id,
              $manual_res['title'],
              $manual_res['course_code'],
              $coverage_info['dept_ids'],
              $manual_res['school_id']
            );
          }

          // Log the action
          log_audit_event($conn, $admin_id, $current_status === MATERIAL_STATUS_AWAITING ? 'retire' : 'close', 'course_material', $id, [
            'title' => $manual_res['title'],
            'course_code' => $manual_res['course_code']
          ]);
        }
      } else {
        $statusRes = 'error';
        $messageRes = 'Update failed';
      }
    }
  } else {
    $statusRes = 'error';
    $messageRes = 'Material not found';
  }
}

// Handle new material creation
if(isset($_POST['create_material'])){
  if (!ensureValidMaterialAdminSession($admin_id, $admin_exists, $statusRes, $messageRes)) {
    goto materials_response;
  }

  $school = intval($_POST['school'] ?? UNSELECTED_VALUE);
  $host_faculty = intval($_POST['host_faculty'] ?? UNSELECTED_VALUE);
  $faculty = intval($_POST['faculty'] ?? UNSELECTED_VALUE);
  $selected_depts = $_POST['depts'] ?? array();
  if (!is_array($selected_depts)) {
    $selected_depts = array($selected_depts);
  }
  // Backward compatibility for older clients posting a single "dept" field.
  $legacy_posted_dept = intval($_POST['dept'] ?? 0);
  if (count($selected_depts) === 0 && $legacy_posted_dept > 0) {
    $selected_depts = array($legacy_posted_dept);
  }
  $level = !empty($_POST['level']) ? intval($_POST['level']) : null;
  $title = trim($_POST['title'] ?? '');
  $course_code = trim($_POST['course_code'] ?? '');
  $price_input = trim($_POST['price'] ?? '');
  $semester = normalizeMaterialSemester($_POST['semester'] ?? 0);

  // Validate required fields first
  // Note: Don't use empty() for price_input as '0' is a valid value for free materials
  if(empty($title) || empty($course_code) || $price_input === ''){
    $statusRes = 'error';
    $messageRes = 'All required fields must be filled';
  }
  elseif(materialSemesterReady($conn) && $semester === 0){
    $statusRes = 'error';
    $messageRes = 'Select the semester this material is sold in';
  }
  // Then validate price is a non-negative integer (no decimals, no leading zeros except '0' itself)
  // ctype_digit() returns true for strings containing only digits 0-9
  elseif(!ctype_digit($price_input)){
    $statusRes = 'error';
    $messageRes = 'Price must be a valid non-negative integer';
  }
  else {
    $price = intval($price_input);
    
    // Validate school, host_faculty and faculty are selected
    if($school == UNSELECTED_VALUE || $host_faculty == UNSELECTED_VALUE || $faculty == UNSELECTED_VALUE){
      $statusRes = 'error';
      $messageRes = 'School, Faculty Host, and Faculty are required';
    } else {
      // Validate admin permissions
      if($admin_role == 5){
        if($school != $admin_school){
          $statusRes = 'error';
          $messageRes = 'Unauthorized: Invalid school';
        } elseif($admin_faculty != UNSELECTED_VALUE && ($host_faculty != $admin_faculty || $faculty != $admin_faculty)){
          $statusRes = 'error';
          $messageRes = 'Unauthorized: Invalid faculty';
        }
      }
      
      if(!isset($statusRes) || $statusRes !== 'error'){
        $coverage_res = resolveCoverageFromPostedDepartments($conn, $school, $faculty, $selected_depts);
        if (!$coverage_res['success']) {
          $statusRes = 'error';
          $messageRes = $coverage_res['message'];
        } elseif ($admin_role == 5 && $admin_faculty != UNSELECTED_VALUE && $coverage_res['coverage'] === COVERAGE_SCHOOL) {
          $statusRes = 'error';
          $messageRes = 'Unauthorized: Faculty admins cannot set school-wide coverage';
        }
      }

      if(!isset($statusRes) || $statusRes !== 'error'){
        $dept_ids = $coverage_res['dept_ids'];
        $dept_csv = $coverage_res['dept_csv'];
        $coverage = $coverage_res['coverage'];
        $legacy_dept = ($coverage === COVERAGE_CUSTOM && count($dept_ids) === 1) ? intval($dept_ids[0]) : 0;
        $legacy_faculty = ($coverage === COVERAGE_SCHOOL) ? 0 : $faculty;

        // Set a default far-future due date since the field is required in DB but no longer used
        $due_date_mysql = '2099-12-31 23:59:59';
        
        // Generate unique alphanumeric code using cryptographically secure random
        // Using uppercase letters and numbers for better readability (avoid confusion like 0/O, 1/l)
        $code = '';
        $isUnique = false;
        $attempts = 0;
        // Character set: A-Z and 0-9 (36 characters, CODE_LENGTH positions = 36^CODE_LENGTH possibilities)
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        
        while(!$isUnique && $attempts < MAX_CODE_GENERATION_ATTEMPTS){
          $code = '';
          // Generate random characters using random_int for cryptographic security
          for($i = 0; $i < CODE_LENGTH; $i++){
            $code .= $characters[random_int(0, $charactersLength - 1)];
          }
          
          // Use prepared statement to check uniqueness
          $check_stmt = mysqli_prepare($conn, "SELECT id FROM manuals WHERE code = ?");
          mysqli_stmt_bind_param($check_stmt, 's', $code);
          mysqli_stmt_execute($check_stmt);
          mysqli_stmt_store_result($check_stmt);
          if(mysqli_stmt_num_rows($check_stmt) == 0){
            $isUnique = true;
          }
          mysqli_stmt_close($check_stmt);
          $attempts++;
        }
        
        if(!$isUnique){
          $statusRes = 'error';
          $messageRes = 'Failed to generate unique code. Please try again.';
        } else {
          // Insert new material using prepared statement
          // Note: host_faculty is the faculty hosting the material, faculty is who can buy it
          if($level !== null){
            $insert_stmt = mysqli_prepare($conn, 
              "INSERT INTO manuals (title, course_code, price, code, due_date, quantity, dept, depts, coverage, faculty, host_faculty, level, user_id, admin_id, school_id, status, created_at) 
               VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'open', NOW())");
            
            mysqli_stmt_bind_param($insert_stmt, 'ssississiiiii', 
              $title, $course_code, $price, $code, $due_date_mysql, 
              $legacy_dept, $dept_csv, $coverage, $legacy_faculty, $host_faculty, $level, $admin_id, $school);
          } else {
            $insert_stmt = mysqli_prepare($conn, 
              "INSERT INTO manuals (title, course_code, price, code, due_date, quantity, dept, depts, coverage, faculty, host_faculty, user_id, admin_id, school_id, status, created_at) 
               VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 0, ?, ?, 'open', NOW())");
            
            mysqli_stmt_bind_param($insert_stmt, 'ssississiiii', 
              $title, $course_code, $price, $code, $due_date_mysql, 
              $legacy_dept, $dept_csv, $coverage, $legacy_faculty, $host_faculty, $admin_id, $school);
          }
          
          if(mysqli_stmt_execute($insert_stmt)){
            $material_id = mysqli_insert_id($conn);
            $statusRes = 'success';
            $messageRes = 'Course material created successfully with code: ' . $code;

            $school_semester = materialSchoolCurrentSemester($conn, $school);
            $visible_now = true;
            if (materialSemesterReady($conn)) {
              $session = materialPostedSession($conn, $school);
              $session_sql = materialSessionReady($conn) ? ", session = '" . mysqli_real_escape_string($conn, $session) . "'" : '';
              mysqli_query($conn, "UPDATE manuals SET semester = $semester$session_sql WHERE id = " . intval($material_id));
              $visible_now = ($semester === $school_semester) && (!materialSessionReady($conn) || $session === materialSchoolCurrentSession($conn, $school));
              if (!$visible_now) {
                $messageRes .= '. It will go live when ' . materialPeriodLabel($semester, $session) . ' starts.';
              }
            }
            
            // Log the action
            if(function_exists('log_audit_event')){
              log_audit_event($conn, $admin_id, 'create', 'course_material', $material_id, [
                'title' => $title,
                'course_code' => $course_code,
                'code' => $code
              ]);
            }
            
            // Send notification to students (only when they can see it now)
            if ($visible_now) {
              require_once __DIR__ . '/notification_helpers.php';
              notifyCourseMaterialCreated(
                $conn,
                $admin_id,
                $material_id,
                $title,
                $course_code,
                $dept_ids,
                $faculty,
                $school
              );
            }
          } else {
            $statusRes = 'error';
            $messageRes = 'Failed to create material. Please try again.';
          }
          mysqli_stmt_close($insert_stmt);
        }
      }
    }
  }
}

// Handle material update
if(isset($_POST['update_material'])){
  if (!ensureValidMaterialAdminSession($admin_id, $admin_exists, $statusRes, $messageRes)) {
    goto materials_response;
  }

  $material_id = intval($_POST['material_id'] ?? 0);
  $school = intval($_POST['school'] ?? UNSELECTED_VALUE);
  $host_faculty = intval($_POST['host_faculty'] ?? UNSELECTED_VALUE);
  $faculty = intval($_POST['faculty'] ?? UNSELECTED_VALUE);
  $selected_depts = $_POST['depts'] ?? array();
  if (!is_array($selected_depts)) {
    $selected_depts = array($selected_depts);
  }
  // Backward compatibility for older clients posting a single "dept" field.
  $legacy_posted_dept = intval($_POST['dept'] ?? 0);
  if (count($selected_depts) === 0 && $legacy_posted_dept > 0) {
    $selected_depts = array($legacy_posted_dept);
  }
  $level = !empty($_POST['level']) ? intval($_POST['level']) : null;
  $title = trim($_POST['title'] ?? '');
  $course_code = trim($_POST['course_code'] ?? '');
  $price_input = trim($_POST['price'] ?? '');
  $semester = normalizeMaterialSemester($_POST['semester'] ?? 0);

  // Validate material ID
  if($material_id <= 0){
    $statusRes = 'error';
    $messageRes = 'Invalid material ID';
  }
  elseif(materialSemesterReady($conn) && $semester === 0){
    $statusRes = 'error';
    $messageRes = 'Select the semester this material is sold in';
  }
  // Validate required fields first
  elseif(empty($title) || empty($course_code) || $price_input === ''){
    $statusRes = 'error';
    $messageRes = 'All required fields must be filled';
  }
  // Then validate price is a non-negative integer
  elseif(!ctype_digit($price_input)){
    $statusRes = 'error';
    $messageRes = 'Price must be a valid non-negative integer';
  }
  else {
    $price = intval($price_input);
    
    // Validate school, host_faculty and faculty are selected
    if($school == UNSELECTED_VALUE || $host_faculty == UNSELECTED_VALUE || $faculty == UNSELECTED_VALUE){
      $statusRes = 'error';
      $messageRes = 'School, Faculty Host, and Faculty are required';
    } else {
      // Verify material exists and enforce scope-based authorization
      $check_stmt = mysqli_prepare($conn, "SELECT admin_id, school_id, faculty, dept, depts, coverage FROM manuals WHERE id = ?");
      mysqli_stmt_bind_param($check_stmt, 'i', $material_id);
      mysqli_stmt_execute($check_stmt);
      $result = mysqli_stmt_get_result($check_stmt);
      $existing_material = mysqli_fetch_assoc($result);
      mysqli_stmt_close($check_stmt);
      
      if(!$existing_material){
        $statusRes = 'error';
        $messageRes = 'Material not found';
      } elseif(intval($existing_material['admin_id']) <= 0){
        $statusRes = 'error';
        $messageRes = 'Unauthorized: Only admin-created materials can be edited';
      } else {
        // School admins (role 5) can only edit materials within their school/faculty scope.
        if($admin_role == 5){
          if(intval($existing_material['school_id']) !== intval($admin_school)){
            $statusRes = 'error';
            $messageRes = 'Unauthorized: Material is outside your school scope';
          } elseif(intval($admin_faculty) != UNSELECTED_VALUE){
            $existing_scope = resolveMaterialCoverage($conn, $existing_material);
            $existing_dept_ids = $existing_scope['dept_ids'];
            $normalized_existing_depts = array();
            foreach($existing_dept_ids as $existing_dept_id){
              $existing_dept_id = intval($existing_dept_id);
              if($existing_dept_id > 0){
                $normalized_existing_depts[] = $existing_dept_id;
              }
            }

            if(count($normalized_existing_depts) === 0){
              $statusRes = 'error';
              $messageRes = 'Unauthorized: Material has no valid department scope';
            } else {
              $dept_scope_sql = implode(',', $normalized_existing_depts);
              $admin_faculty_safe = intval($admin_faculty);
              $scope_query = mysqli_query($conn, "SELECT id FROM depts WHERE faculty_id = $admin_faculty_safe AND id IN ($dept_scope_sql) LIMIT 1");
              if(!$scope_query || mysqli_num_rows($scope_query) === 0){
                $statusRes = 'error';
                $messageRes = 'Unauthorized: Material is outside your faculty scope';
              }
            }
          }
        }

        // Validate admin permissions
        if((!isset($statusRes) || $statusRes !== 'error') && $admin_role == 5){
          if(intval($school) !== intval($admin_school)){
            $statusRes = 'error';
            $messageRes = 'Unauthorized: Invalid school';
          } elseif(intval($admin_faculty) != UNSELECTED_VALUE && (intval($host_faculty) !== intval($admin_faculty) || intval($faculty) !== intval($admin_faculty))){
            $statusRes = 'error';
            $messageRes = 'Unauthorized: Invalid faculty';
          }
        }
        
        if(!isset($statusRes) || $statusRes !== 'error'){
          $coverage_res = resolveCoverageFromPostedDepartments($conn, $school, $faculty, $selected_depts);
          if (!$coverage_res['success']) {
            $statusRes = 'error';
            $messageRes = $coverage_res['message'];
          } elseif ($admin_role == 5 && $admin_faculty != UNSELECTED_VALUE && $coverage_res['coverage'] === COVERAGE_SCHOOL) {
            $statusRes = 'error';
            $messageRes = 'Unauthorized: Faculty admins cannot set school-wide coverage';
          }
        }

        if(!isset($statusRes) || $statusRes !== 'error'){
          $dept_ids = $coverage_res['dept_ids'];
          $dept_csv = $coverage_res['dept_csv'];
          $coverage = $coverage_res['coverage'];
          $legacy_dept = ($coverage === COVERAGE_CUSTOM && count($dept_ids) === 1) ? intval($dept_ids[0]) : 0;
          $legacy_faculty = ($coverage === COVERAGE_SCHOOL) ? 0 : $faculty;

          // Update material using prepared statement (no due_date update needed)
          if($level !== null){
            $update_stmt = mysqli_prepare($conn, 
              "UPDATE manuals SET title = ?, course_code = ?, price = ?, dept = ?, depts = ?, coverage = ?, faculty = ?, host_faculty = ?, level = ?, school_id = ? WHERE id = ?");
            
            mysqli_stmt_bind_param($update_stmt, 'ssiissiiiii', 
              $title, $course_code, $price, 
              $legacy_dept, $dept_csv, $coverage, $legacy_faculty, $host_faculty, $level, $school, $material_id);
          } else {
            $update_stmt = mysqli_prepare($conn, 
              "UPDATE manuals SET title = ?, course_code = ?, price = ?, dept = ?, depts = ?, coverage = ?, faculty = ?, host_faculty = ?, school_id = ? WHERE id = ?");
            
            mysqli_stmt_bind_param($update_stmt, 'ssiissiiii', 
              $title, $course_code, $price, 
              $legacy_dept, $dept_csv, $coverage, $legacy_faculty, $host_faculty, $school, $material_id);
          }
          
          if(mysqli_stmt_execute($update_stmt)){
            $statusRes = 'success';
            $messageRes = 'Course material updated successfully';
            if (materialSemesterReady($conn)) {
              $session = materialPostedSession($conn, $school);
              $session_sql = materialSessionReady($conn) ? ", session = '" . mysqli_real_escape_string($conn, $session) . "'" : '';
              mysqli_query($conn, "UPDATE manuals SET semester = $semester$session_sql WHERE id = " . intval($material_id));
            }
            
            // Log the action
            if(function_exists('log_audit_event')){
              log_audit_event($conn, $admin_id, 'update', 'course_material', $material_id, [
                'title' => $title,
                'course_code' => $course_code
              ]);
            }
          } else {
            $statusRes = 'error';
            $messageRes = 'Failed to update material. Please try again.';
          }
          mysqli_stmt_close($update_stmt);
        }
      }
    }
  }
}

// Confirm a material for sale (awaiting confirmation or closed -> open) after checking price and semester
if(isset($_POST['confirm_material'])){
  if (!ensureValidMaterialAdminSession($admin_id, $admin_exists, $statusRes, $messageRes)) {
    goto materials_response;
  }
  if (!materialSemesterReady($conn)) {
    $statusRes = 'error';
    $messageRes = 'Semester tagging is not set up yet. Run nivasity/sql/add_semester_tagging.sql first.';
    goto materials_response;
  }

  $material_id = intval($_POST['material_id'] ?? 0);
  $semester = normalizeMaterialSemester($_POST['semester'] ?? 0);
  $price_input = trim((string) ($_POST['price'] ?? ''));

  $manual_res = $material_id > 0
    ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, status, price, semester, school_id, faculty, dept, depts, coverage, title, course_code FROM manuals WHERE id = $material_id"))
    : null;

  if (!$manual_res) {
    $statusRes = 'error';
    $messageRes = 'Material not found';
  } elseif (!adminCanManageMaterial($admin_role, $admin_school, $admin_faculty, $manual_res)) {
    $statusRes = 'error';
    $messageRes = 'Unauthorized';
  } elseif ($manual_res['status'] === 'open') {
    $statusRes = 'error';
    $messageRes = 'This material is already open';
  } elseif ($semester === 0) {
    $statusRes = 'error';
    $messageRes = 'Select the semester this material is sold in';
  } elseif ($price_input === '' || !ctype_digit($price_input)) {
    $statusRes = 'error';
    $messageRes = 'Price must be a valid non-negative integer';
  } else {
    $price = intval($price_input);
    $confirm_stmt = mysqli_prepare($conn, "UPDATE manuals SET price = ?, semester = ?, status = 'open', confirmed_at = NOW(), confirmed_by = ? WHERE id = ?");
    mysqli_stmt_bind_param($confirm_stmt, 'iiii', $price, $semester, $admin_id, $material_id);
    if (mysqli_stmt_execute($confirm_stmt)) {
      $school_id = intval($manual_res['school_id']);
      $session = materialPostedSession($conn, $school_id);
      if (materialSessionReady($conn)) {
        mysqli_query($conn, "UPDATE manuals SET session = '" . mysqli_real_escape_string($conn, $session) . "' WHERE id = $material_id");
      }
      $visible_now = ($semester === materialSchoolCurrentSemester($conn, $school_id))
        && (!materialSessionReady($conn) || $session === materialSchoolCurrentSession($conn, $school_id));
      $statusRes = 'success';
      $messageRes = $visible_now
        ? 'Material confirmed and open for sale'
        : 'Material confirmed. It will go live when ' . materialPeriodLabel($semester, $session) . ' starts.';

      log_audit_event($conn, $admin_id, 'confirm', 'course_material', $material_id, [
        'title' => $manual_res['title'],
        'course_code' => $manual_res['course_code'],
        'previous_status' => $manual_res['status'],
        'old_price' => intval($manual_res['price']),
        'new_price' => $price,
        'old_semester' => normalizeMaterialSemester($manual_res['semester']),
        'new_semester' => $semester
      ]);

      if ($visible_now) {
        $coverage_info = resolveMaterialCoverage($conn, $manual_res);
        require_once __DIR__ . '/notification_helpers.php';
        notifyCourseMaterialCreated(
          $conn,
          $admin_id,
          $material_id,
          $manual_res['title'],
          $manual_res['course_code'],
          $coverage_info['dept_ids'],
          intval($manual_res['faculty']),
          $school_id
        );
      }
    } else {
      $statusRes = 'error';
      $messageRes = 'Failed to confirm material. Please try again.';
    }
    mysqli_stmt_close($confirm_stmt);
  }
}

// Switch a school's current semester. Open materials of the outgoing semester (and legacy
// untagged ones) move to awaiting confirmation; open materials of the new semester go live.
if(isset($_POST['switch_semester'])){
  if (!ensureValidMaterialAdminSession($admin_id, $admin_exists, $statusRes, $messageRes)) {
    goto materials_response;
  }
  if (!materialSemesterReady($conn)) {
    $statusRes = 'error';
    $messageRes = 'Semester tagging is not set up yet. Run nivasity/sql/add_semester_tagging.sql first.';
    goto materials_response;
  }

  $school_id = intval($_POST['school'] ?? 0);
  $target = normalizeMaterialSemester($_POST['target_semester'] ?? 0);
  $current = materialSchoolCurrentSemester($conn, $school_id);
  $current_session = materialSchoolCurrentSession($conn, $school_id);
  list($target_session, $expected_target) = materialNextPeriod($current_session, $current);
  if (materialSessionReady($conn) && $target !== $expected_target) {
    $target = 0; // stale page: the period already moved on
  }

  if ($school_id <= 0 || $current === 0) {
    $statusRes = 'error';
    $messageRes = 'Select a school';
  } elseif (!adminCanSwitchSchoolSemester($admin_role, $admin_school, $admin_faculty, $school_id)) {
    $statusRes = 'error';
    $messageRes = 'Unauthorized: only school-wide admins can switch the semester';
  } elseif ($target === 0 || $target === $current) {
    $statusRes = 'error';
    $messageRes = 'The semester has already been switched. Refresh the page.';
  } else {
    // Guarded on the current value so a double submit cannot switch twice.
    $cs = mysqli_real_escape_string($conn, $current_session);
    $ts = mysqli_real_escape_string($conn, $target_session);
    if (materialSessionReady($conn)) {
      mysqli_query($conn, "UPDATE schools SET current_semester = $target, current_session = '$ts', current_semester_updated_at = NOW() WHERE id = $school_id AND current_semester = $current AND current_session = '$cs'");
    } else {
      mysqli_query($conn, "UPDATE schools SET current_semester = $target, current_semester_updated_at = NOW() WHERE id = $school_id AND current_semester = $current");
    }
    if (mysqli_affected_rows($conn) !== 1) {
      $statusRes = 'error';
      $messageRes = 'The semester has already been switched. Refresh the page.';
    } else {
      $awaiting = MATERIAL_STATUS_AWAITING;
      $not_target = materialSessionReady($conn)
        ? "NOT (semester <=> $target AND session <=> '$ts')"
        : "(semester = $current OR semester IS NULL)";
      $moved = mysqli_query($conn, "UPDATE manuals SET status = '$awaiting' WHERE school_id = $school_id AND status = 'open' AND $not_target");
      if (!$moved) {
        error_log('[cc_dashboard semester switch] ' . mysqli_error($conn));
        mysqli_query($conn, "UPDATE schools SET current_semester = $current" . (materialSessionReady($conn) ? ", current_session = '$cs'" : '') . " WHERE id = $school_id AND current_semester = $target");
        $statusRes = 'error';
        $messageRes = 'Failed to move materials to awaiting confirmation. The semester was not switched.';
      } else {
        $moved_count = mysqli_affected_rows($conn);
        $live_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM manuals WHERE school_id = $school_id AND status = 'open' AND semester = $target"
          . (materialSessionReady($conn) ? " AND session = '$ts'" : ''));
        if (materialSessionReady($conn)) {
          mysqli_query($conn, "INSERT INTO academic_periods (school_id, session, semester, started_at, started_by) VALUES ($school_id, '$ts', $target, NOW(), " . intval($admin_id) . ")");
        }
        $live_count = $live_res ? intval(mysqli_fetch_assoc($live_res)['c'] ?? 0) : 0;

        $statusRes = 'success';
        $messageRes = materialPeriodLabel($target, $target_session) . ' is now active. '
          . $moved_count . ' material(s) moved to awaiting confirmation; '
          . $live_count . ' material(s) are now live.';

        log_audit_event($conn, $admin_id, 'semester_switch', 'school', $school_id, [
          'from_semester' => $current,
          'to_semester' => $target,
          'from_session' => $current_session,
          'to_session' => $target_session,
          'moved_to_awaiting' => $moved_count,
          'now_live' => $live_count
        ]);
      }
    }
  }
}

// Handle material deletion
if(isset($_POST['delete_material'])){
  if (!ensureValidMaterialAdminSession($admin_id, $admin_exists, $statusRes, $messageRes)) {
    goto materials_response;
  }

  $material_id = intval($_POST['material_id'] ?? 0);
  
  // Validate material ID
  if($material_id <= 0){
    $statusRes = 'error';
    $messageRes = 'Invalid material ID';
  } else {
    // Verify the material exists, was created by this admin, is closed, and has no purchases
    $check_stmt = mysqli_prepare($conn, 
      "SELECT m.admin_id, m.status, m.title, m.course_code, COUNT(DISTINCT b.ref_id) AS purchase_count 
       FROM manuals m 
       LEFT JOIN manuals_bought b ON b.manual_id = m.id AND b.status = 'successful' 
       WHERE m.id = ? 
       GROUP BY m.id");
    mysqli_stmt_bind_param($check_stmt, 'i', $material_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $material = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);
    
    if(!$material){
      $statusRes = 'error';
      $messageRes = 'Material not found';
    } elseif($material['admin_id'] != $admin_id){
      $statusRes = 'error';
      $messageRes = 'Unauthorized: You can only delete materials you created';
    } elseif($material['status'] !== 'closed'){
      $statusRes = 'error';
      $messageRes = 'Only closed materials can be deleted. Please close the material first.';
    } elseif($material['purchase_count'] > 0){
      $statusRes = 'error';
      $messageRes = 'Cannot delete material with existing purchases (' . $material['purchase_count'] . ' purchase(s) found)';
    } else {
      // Log the action before deletion (for audit trail)
      if(function_exists('log_audit_event')){
        log_audit_event($conn, $admin_id, 'delete', 'course_material', $material_id, [
          'title' => $material['title'],
          'course_code' => $material['course_code']
        ]);
      }
      
      // Delete the material
      $delete_stmt = mysqli_prepare($conn, "DELETE FROM manuals WHERE id = ?");
      mysqli_stmt_bind_param($delete_stmt, 'i', $material_id);
      
      if(mysqli_stmt_execute($delete_stmt)){
        $statusRes = 'success';
        $messageRes = 'Course material deleted successfully';
      } else {
        $statusRes = 'error';
        $messageRes = 'Failed to delete material. Please try again.';
      }
      mysqli_stmt_close($delete_stmt);
    }
  }
}

materials_response:
$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'faculties' => $faculties,
  'departments' => $departments,
  'materials' => $materials,
  'stats' => $stats ?? null,
  'restrict_faculty' => $restrict_faculty,
  'semester_ready' => materialSemesterReady($conn)
);

header('Content-Type: application/json');
echo json_encode($responseData);
?>
