<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = 'Unable to process request.';
$responseData = [];

$user_id = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;
$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;

if ($user_id <= 0 || $admin_role <= 0) {
  $messageRes = 'Unauthorized request.';
  respondDepartmentJson($statusRes, $messageRes, $responseData);
}

$admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $user_id LIMIT 1"));
$admin_school = isset($admin_data['school']) ? (int) $admin_data['school'] : 0;
$admin_faculty = isset($admin_data['faculty']) ? (int) $admin_data['faculty'] : 0;

if (isset($_POST['reassign_students'])) {
  handleDepartmentReassignment($conn, $user_id, $admin_role, $admin_school, $admin_faculty, $statusRes, $messageRes, $responseData);
  respondDepartmentJson($statusRes, $messageRes, $responseData);
}

if (isset($_POST['dept_edit'])) {
  handleDepartmentStatusChange($conn, $user_id, $admin_role, $admin_school, $statusRes, $messageRes);
  respondDepartmentJson($statusRes, $messageRes, $responseData);
}

handleDepartmentSave($conn, $user_id, $admin_role, $admin_school, $admin_faculty, $statusRes, $messageRes);
respondDepartmentJson($statusRes, $messageRes, $responseData);

function respondDepartmentJson(string $status, string $message, array $extra = []): void
{
  $payload = array_merge([
    'status' => $status,
    'message' => $message,
  ], $extra);

  header('Content-Type: application/json');
  echo json_encode($payload);
  exit;
}

function handleDepartmentStatusChange(mysqli $conn, int $user_id, int $admin_role, int $admin_school, string &$statusRes, string &$messageRes): void
{
  $dept_id = isset($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
  $status = trim((string) ($_POST['status'] ?? ''));

  if ($dept_id <= 0 || $status === '') {
    $messageRes = 'Invalid department update request.';
    return;
  }

  $allowedStatuses = ['active', 'deactivated'];
  if (!in_array($status, $allowedStatuses, true)) {
    $messageRes = 'Invalid department status supplied.';
    return;
  }

  $school_condition = ($admin_role == 5) ? " AND school_id = $admin_school" : '';
  mysqli_query($conn, "UPDATE depts SET status = '" . mysqli_real_escape_string($conn, $status) . "' WHERE id = $dept_id$school_condition");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = 'success';
    $messageRes = 'Status changed successfully!';
    log_audit_event($conn, $user_id, 'status_change', 'department', $dept_id, [
      'new_status' => $status,
      'restricted_school' => $admin_role == 5 ? $admin_school : null
    ]);
    return;
  }

  $statusRes = 'error';
  $messageRes = 'Internal Server Error. Please try again later!';
}

function handleDepartmentSave(mysqli $conn, int $user_id, int $admin_role, int $admin_school, int $admin_faculty, string &$statusRes, string &$messageRes): void
{
  $school_id = isset($_POST['school_id']) ? (int) $_POST['school_id'] : 0;
  if ($admin_role == 5) {
    $school_id = $admin_school;
  }

  $dept_id = isset($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
  $name = trim((string) ($_POST['name'] ?? ''));
  $faculty_id = isset($_POST['faculty']) ? (int) $_POST['faculty'] : 0;

  if ($school_id <= 0 || $faculty_id <= 0 || $name === '') {
    $messageRes = 'Department name, school and faculty are required.';
    return;
  }

  if ($admin_role == 5 && $admin_faculty > 0 && $faculty_id !== $admin_faculty) {
    $messageRes = 'You are not allowed to assign this department outside your faculty.';
    return;
  }

  $faculty_scope_sql = '';
  if ($admin_role == 5 && $admin_faculty > 0) {
    $faculty_scope_sql = " AND id = $admin_faculty";
  }
  $faculty_check = mysqli_query($conn, "SELECT id FROM faculties WHERE id = $faculty_id AND school_id = $school_id$faculty_scope_sql LIMIT 1");
  if (!$faculty_check || mysqli_num_rows($faculty_check) === 0) {
    $messageRes = 'Selected faculty is invalid for this school.';
    return;
  }

  $escaped_name = mysqli_real_escape_string($conn, $name);
  $duplicate_condition = ($dept_id == 0)
    ? " AND school_id = $school_id AND faculty_id = $faculty_id"
    : " AND id != $dept_id AND school_id = $school_id AND faculty_id = $faculty_id";
  $dept_query = mysqli_query($conn, "SELECT id FROM depts WHERE name = '$escaped_name'$duplicate_condition");

  if ($dept_query && mysqli_num_rows($dept_query) >= 1) {
    $messageRes = "A dept already exist with this name - $name";
    return;
  }

  if ($dept_id == 0) {
    mysqli_query($conn, "INSERT INTO depts (name, school_id, faculty_id) VALUES ('$escaped_name', $school_id, $faculty_id)");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = 'success';
      $messageRes = 'dept successfully added!';
      $insert_id = mysqli_insert_id($conn);
      log_audit_event($conn, $user_id, 'create', 'department', $insert_id, [
        'name' => $name,
        'school_id' => $school_id,
        'faculty_id' => $faculty_id
      ]);
      return;
    }

    $statusRes = 'error';
    $messageRes = 'Internal Server Error. Please try again later!';
    return;
  }

  $dept_scope_sql = ($admin_role == 5) ? " AND school_id = $admin_school" : '';
  if ($admin_role == 5 && $admin_faculty > 0) {
    $dept_scope_sql .= " AND faculty_id = $admin_faculty";
  }

  mysqli_query($conn, "UPDATE depts SET name = '$escaped_name', school_id = '$school_id', faculty_id = '$faculty_id' WHERE id = $dept_id$dept_scope_sql");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = 'success';
    $messageRes = 'dept successfully edited!';
    log_audit_event($conn, $user_id, 'update', 'department', $dept_id, [
      'name' => $name,
      'school_id' => $school_id,
      'faculty_id' => $faculty_id
    ]);
    return;
  }

  $statusRes = 'error';
  $messageRes = 'No changes made!';
}

function handleDepartmentReassignment(mysqli $conn, int $user_id, int $admin_role, int $admin_school, int $admin_faculty, string &$statusRes, string &$messageRes, array &$responseData): void
{
  $source_dept_id = isset($_POST['source_dept_id']) ? (int) $_POST['source_dept_id'] : 0;
  $target_dept_id = isset($_POST['target_dept_id']) ? (int) $_POST['target_dept_id'] : 0;

  if ($source_dept_id <= 0 || $target_dept_id <= 0) {
    $messageRes = 'Source and target departments are required.';
    return;
  }

  if ($source_dept_id === $target_dept_id) {
    $messageRes = 'Source and target departments must be different.';
    return;
  }

  $sourceDept = fetchDepartmentForScope($conn, $source_dept_id, $admin_role, $admin_school, $admin_faculty);
  if ($sourceDept === null) {
    $messageRes = 'Source department not found or outside your scope.';
    return;
  }

  $targetDept = fetchDepartmentForScope($conn, $target_dept_id, $admin_role, $admin_school, $admin_faculty);
  if ($targetDept === null) {
    $messageRes = 'Target department not found or outside your scope.';
    return;
  }

  $source_school_id = (int) ($sourceDept['school_id'] ?? 0);
  $target_school_id = (int) ($targetDept['school_id'] ?? 0);
  if ($source_school_id <= 0 || $target_school_id <= 0 || $source_school_id !== $target_school_id) {
    $messageRes = 'Departments must belong to the same school.';
    return;
  }

  if (strtolower((string) ($targetDept['status'] ?? '')) !== 'active') {
    $messageRes = 'Students can only be reassigned into an active department.';
    return;
  }

  if ($admin_role == 5 && $admin_faculty > 0) {
    $source_faculty_id = (int) ($sourceDept['faculty_id'] ?? 0);
    $target_faculty_id = (int) ($targetDept['faculty_id'] ?? 0);
    if ($source_faculty_id !== $admin_faculty || $target_faculty_id !== $admin_faculty) {
      $messageRes = 'You can only reassign students within your faculty.';
      return;
    }
  }

  $student_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_students FROM users WHERE dept = $source_dept_id");
  $student_count_row = $student_count_query ? mysqli_fetch_assoc($student_count_query) : null;
  $total_students = isset($student_count_row['total_students']) ? (int) $student_count_row['total_students'] : 0;

  if ($total_students <= 0) {
    $statusRes = 'success';
    $messageRes = 'No users are currently assigned to this department.';
    $responseData['moved_students'] = 0;
    return;
  }

  mysqli_begin_transaction($conn);
  try {
    $update_query = mysqli_query($conn, "UPDATE users SET dept = $target_dept_id WHERE dept = $source_dept_id");
    if (!$update_query) {
      throw new Exception('Failed to update student department assignments.');
    }

    $moved_students = mysqli_affected_rows($conn);
    mysqli_commit($conn);

    $statusRes = 'success';
    $messageRes = $moved_students > 0
      ? 'Students reassigned successfully.'
      : 'No students were reassigned.';
    $responseData['moved_students'] = max(0, $moved_students);
    $responseData['source_department'] = $sourceDept['name'] ?? '';
    $responseData['target_department'] = $targetDept['name'] ?? '';

    log_audit_event($conn, $user_id, 'reassign_students', 'department', (string) $source_dept_id, [
      'source_department_id' => $source_dept_id,
      'source_department_name' => $sourceDept['name'] ?? '',
      'target_department_id' => $target_dept_id,
      'target_department_name' => $targetDept['name'] ?? '',
      'moved_students' => max(0, $moved_students),
      'school_id' => $source_school_id,
      'faculty_scope' => $admin_role == 5 ? $admin_faculty : null
    ]);
  } catch (Throwable $e) {
    mysqli_rollback($conn);
    $statusRes = 'error';
    $messageRes = $e->getMessage();
  }
}

function fetchDepartmentForScope(mysqli $conn, int $dept_id, int $admin_role, int $admin_school, int $admin_faculty): ?array
{
  if ($dept_id <= 0) {
    return null;
  }

  $scope_sql = '';
  if ($admin_role == 5) {
    $scope_sql .= " AND school_id = $admin_school";
    if ($admin_faculty > 0) {
      $scope_sql .= " AND faculty_id = $admin_faculty";
    }
  }

  $query = mysqli_query($conn, "SELECT id, name, school_id, faculty_id, status FROM depts WHERE id = $dept_id$scope_sql LIMIT 1");
  if (!$query || mysqli_num_rows($query) === 0) {
    return null;
  }

  return mysqli_fetch_assoc($query) ?: null;
}
