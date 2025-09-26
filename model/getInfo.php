<?php
session_start();
include('config.php');
include('functions.php');
$statusRes = 'failed';
$schools = $depts = $faculties = null;
$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = $admin_faculty = null;
if ($admin_role == 5 && $admin_id) {
  $admin_data = mysqli_fetch_array(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
  $admin_school = $admin_data['school'];
  $admin_faculty = $admin_data['faculty'];
}

// CSV downloads for schools/faculties/departments
if (isset($_GET['download'])) {
  $type = $_GET['download'];
  if ($type === 'schools') {
    $where = ($admin_role == 5 && $admin_school) ? "WHERE id = $admin_school" : "";
    $q = mysqli_query($conn, "SELECT id, name, code, status, created_at FROM schools $where ORDER BY name");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="schools_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Short Name', 'Departments', 'Students', 'Status', 'Created At']);
    while ($s = mysqli_fetch_assoc($q)) {
      $dept_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM depts WHERE school_id = {$s['id']}"))['c'] ?? 0;
      $stud_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE (role='student' OR role='hoc') AND school = {$s['id']}"))['c'] ?? 0;
      fputcsv($out, [
        $s['name'],
        $s['code'],
        $dept_cnt,
        $stud_cnt,
        $s['status'],
        date('M d, Y', strtotime($s['created_at']))
      ]);
    }
    fclose($out);
    exit;
  }
  if ($type === 'faculties') {
    $school = intval($_GET['school'] ?? 0);
    if ($admin_role == 5 && $admin_school) { $school = $admin_school; }
    $fac_cond = ($admin_role == 5 && $admin_faculty) ? " AND id = $admin_faculty" : "";
    $q = mysqli_query($conn, "SELECT id, name, status, created_at FROM faculties WHERE school_id = $school$fac_cond ORDER BY name");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="faculties_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Students', 'HOCs', 'Departments', 'Status', 'Created At']);
    while ($f = mysqli_fetch_assoc($q)) {
      $dept_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM depts WHERE faculty_id = {$f['id']}"))['c'] ?? 0;
      $stud_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='student' AND dept IN (SELECT id FROM depts WHERE faculty_id = {$f['id']})"))['c'] ?? 0;
      $hoc_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='hoc' AND dept IN (SELECT id FROM depts WHERE faculty_id = {$f['id']})"))['c'] ?? 0;
      fputcsv($out, [
        $f['name'],
        $stud_cnt,
        $hoc_cnt,
        $dept_cnt,
        $f['status'],
        date('M d, Y', strtotime($f['created_at']))
      ]);
    }
    fclose($out);
    exit;
  }
  if ($type === 'depts') {
    $school = intval($_GET['school'] ?? 0);
    if ($admin_role == 5 && $admin_school) { $school = $admin_school; }
    $dept_cond = ($admin_role == 5 && $admin_faculty) ? " AND faculty_id = $admin_faculty" : "";
    $q = mysqli_query($conn, "SELECT id, name, faculty_id, status, created_at FROM depts WHERE school_id = $school$dept_cond ORDER BY name");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="departments_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Students', 'HOCs', 'Status', 'Created At']);
    while ($d = mysqli_fetch_assoc($q)) {
      $stud_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='student' AND dept = {$d['id']}"))['c'] ?? 0;
      $hoc_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='hoc' AND dept = {$d['id']}"))['c'] ?? 0;
      fputcsv($out, [
        $d['name'],
        $stud_cnt,
        $hoc_cnt,
        $d['status'],
        date('M d, Y', strtotime($d['created_at']))
      ]);
    }
    fclose($out);
    exit;
  }
}

if (isset($_GET['get_data'])) {
  $get_data = $_GET['get_data'];

  if ($get_data == 'schools') {
    if ($admin_role == 5) {
      $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE id = $admin_school");
    } else {
      $school_query = mysqli_query($conn, "SELECT * FROM schools");
    }

    if (mysqli_num_rows($school_query) >= 1) {
      $schools = array();

      while ($school = mysqli_fetch_array($school_query)) {
        // Count total students (students + HOCs)
        $student_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_students FROM users WHERE (role = 'student' OR role = 'hoc') AND school = '{$school['id']}'");
        $student_count_result = mysqli_fetch_assoc($student_count_query);
        $total_students = $student_count_result['total_students'];

        // Count departments
        $department_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_departments FROM depts WHERE school_id = '{$school['id']}'");
        $department_count_result = mysqli_fetch_assoc($department_count_query);
        $total_departments = $department_count_result['total_departments'];

        // Add data to array
        $schools[] = array(
          'id' => $school['id'],
          'name' => $school['name'],
          'code' => $school['code'],
          'status' => $school['status'],
          'total_students' => $total_students,
          'total_departments' => $total_departments
        );
      }
      $statusRes = "success";
    } else {
      $statusRes = "not found";
    }
  }

  if ($get_data == 'school_dept') {
    $school_id = ($admin_role == 5) ? $admin_school : $_GET['school'];
    $dept = $_GET['dept'];
    $school_ = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM schools WHERE id = $school_id"));
    $dept_query = mysqli_query($conn, "SELECT * FROM depts WHERE id = $dept AND school_id = $school_id");

    $depts = "NULL";
    if (mysqli_num_rows($dept_query) > 0) {
      $dept_ = mysqli_fetch_array($dept_query);
      $depts = $dept_['name'];
    }
    $schools = $school_['name'];
    $statusRes = "success";
  }
}

if (isset($_POST['get_data'])) {
  $get_data = $_POST['get_data'];

  if ($get_data == 'depts') {
    $school = ($_SESSION['nivas_adminRole'] == 5) ? $admin_school : $_POST['school'];
    if ($admin_role == 5 && $admin_faculty) {
      $dept_query = mysqli_query($conn, "SELECT * FROM `depts` WHERE school_id = $school AND faculty_id = $admin_faculty");
    } else {
      $dept_query = mysqli_query($conn, "SELECT * FROM `depts` WHERE school_id = $school");
    }

    if (mysqli_num_rows($dept_query) >= 1) {
      $depts = array();

      while ($dept = mysqli_fetch_array($dept_query)) {
        // Count total students in the department (students + HOCs)
        $student_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_students FROM users WHERE role = 'student' AND dept = '{$dept['id']}'");
        $student_count_result = mysqli_fetch_assoc($student_count_query);
        $total_students = $student_count_result['total_students'];

        // Count HOCs specifically in the department
        $hoc_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_hocs FROM users WHERE role = 'hoc' AND dept = '{$dept['id']}'");
        $hoc_count_result = mysqli_fetch_assoc($hoc_count_query);
        $total_hocs = $hoc_count_result['total_hocs'];

        // Add data to array
        $depts[] = array(
          'id' => $dept['id'],
          'name' => $dept['name'],
          'faculty_id' => $dept['faculty_id'],
          'total_students' => $total_students,
          'total_hocs' => $total_hocs,
          'status' => $dept['status']
        );
      }
      $statusRes = "success";
    } else {
      $statusRes = "not found";
    }
  }

  if ($get_data == 'faculties') {
    $school = ($_SESSION['nivas_adminRole'] == 5) ? $admin_school : $_POST['school'];
    $fac_condition = ($admin_role == 5 && $admin_faculty) ? " AND id = $admin_faculty" : "";
    $fac_query = mysqli_query($conn, "SELECT * FROM `faculties` WHERE school_id = $school" . $fac_condition);

    if (mysqli_num_rows($fac_query) >= 1) {
      $faculties = array();

      while ($fac = mysqli_fetch_array($fac_query)) {
        // Count departments for each faculty
        $dept_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_departments FROM depts WHERE faculty_id = '{$fac['id']}'");
        $dept_count_result = mysqli_fetch_assoc($dept_count_query);
        $total_departments = $dept_count_result['total_departments'];

        // Count total students in the faculty
        $student_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_students FROM users WHERE role = 'student' AND dept IN (SELECT id FROM depts WHERE faculty_id = '{$fac['id']}')");
        $student_count_result = mysqli_fetch_assoc($student_count_query);
        $total_students = $student_count_result['total_students'];

        // Count HOCs in the faculty
        $hoc_count_query = mysqli_query($conn, "SELECT COUNT(*) AS total_hocs FROM users WHERE role = 'hoc' AND dept IN (SELECT id FROM depts WHERE faculty_id = '{$fac['id']}')");
        $hoc_count_result = mysqli_fetch_assoc($hoc_count_query);
        $total_hocs = $hoc_count_result['total_hocs'];

        // Add data to array
        $faculties[] = array(
          'id' => $fac['id'],
          'name' => $fac['name'],
          'total_departments' => $total_departments,
          'total_students' => $total_students,
          'total_hocs' => $total_hocs,
          'status' => $fac['status']
        );
      }
      $statusRes = "success";
    } else {
      $statusRes = "not found";
    }
  }
}

$responseData = array(
  "status" => "$statusRes",
  "schools" => $schools,
  "departments" => $depts,
  "faculties" => $faculties
);

// Set the appropriate headers for JSON response
header('Accept-Encoding: gzip, deflate');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
