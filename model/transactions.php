<?php
session_start();
include('config.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = '';
$faculties = $departments = $transactions = null;
$restrict_faculty = false;

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = $admin_faculty = 0;
if ($admin_role == 5 && $admin_id) {
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
  $admin_school = $info['school'];
  $admin_faculty = $info['faculty'];
}

// Handle CSV download for filtered transactions
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  if ($admin_role == 5) {
    $school = $admin_school;
    if ($admin_faculty != 0) {
      $faculty = $admin_faculty;
    }
  }

  $tran_sql = "SELECT t.ref_id, u.first_name, u.last_name, u.matric_no, " .
    "COALESCE(s.name, '') AS school_name, COALESCE(f.name, '') AS faculty_name, COALESCE(d.name, '') AS dept_name, " .
    "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code, ' (', b.price, ')') SEPARATOR ' | ') AS materials, " .
    "t.amount, t.status, t.created_at " .
    "FROM transactions t " .
    "JOIN users u ON t.user_id = u.id " .
    "JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
    "JOIN manuals m ON b.manual_id = m.id " .
    "LEFT JOIN depts d ON m.dept = d.id " .
    "LEFT JOIN faculties f ON d.faculty_id = f.id " .
    "LEFT JOIN schools s ON b.school_id = s.id " .
    "WHERE 1=1";
  if ($school > 0) {
    $tran_sql .= " AND b.school_id = $school";
  }
  if ($faculty != 0) {
    $tran_sql .= " AND d.faculty_id = $faculty";
  }
  if ($dept > 0) {
    $tran_sql .= " AND m.dept = $dept";
  }
  $tran_sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
  $tran_query = mysqli_query($conn, $tran_sql);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="transactions_' . date('Ymd_His') . '.csv"');

  $out = fopen('php://output', 'w');
  // CSV Header
  fputcsv($out, ['Ref Id', 'Student Name', 'Matric No', 'School', 'Faculty/College', 'Department', 'Materials', 'Total Paid', 'Date', 'Time', 'Status']);
  while ($row = mysqli_fetch_assoc($tran_query)) {
    $dateStr = date('M j, Y', strtotime($row['created_at']));
    $timeStr = date('h:i a', strtotime($row['created_at']));
    $statusStr = $row['status'];
    fputcsv($out, [
      $row['ref_id'],
      trim($row['first_name'] . ' ' . $row['last_name']),
      $row['matric_no'],
      $row['school_name'],
      $row['faculty_name'],
      $row['dept_name'],
      $row['materials'],
      $row['amount'],
      $dateStr,
      $timeStr,
      $statusStr
    ]);
  }
  fclose($out);
  exit;
}

if (isset($_GET['fetch'])) {
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

  if ($fetch == 'faculties') {
    if ($admin_role == 5) {
      if ($admin_faculty != 0) {
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
    while ($row = mysqli_fetch_assoc($fac_query)) {
      $faculties[] = $row;
    }
    $statusRes = 'success';
  }

  if ($fetch == 'departments') {
    if ($admin_role == 5) {
      if ($admin_faculty != 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $admin_faculty ORDER BY name");
      } elseif ($faculty != 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $faculty AND school_id = $admin_school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
      }
    } else {
      if ($faculty != 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $faculty ORDER BY name");
      } elseif ($school > 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
      }
    }
    $departments = array();
    while ($row = mysqli_fetch_assoc($dept_query)) {
      $departments[] = $row;
    }
    $statusRes = 'success';
  }

  if ($fetch == 'transactions') {
    $tran_sql = "SELECT t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no, " .
      "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code, ' (₦ ', b.price, ')') SEPARATOR '<br>') AS materials " .
      "FROM transactions t " .
      "JOIN users u ON t.user_id = u.id " .
      "JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
      "JOIN manuals m ON b.manual_id = m.id " .
      "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
    if ($school > 0) {
      $tran_sql .= " AND b.school_id = $school";
    }
    if ($faculty != 0) {
      $tran_sql .= " AND d.faculty_id = $faculty";
    }
    if ($dept > 0) {
      $tran_sql .= " AND m.dept = $dept";
    }
    $tran_sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
    $tran_query = mysqli_query($conn, $tran_sql);
    $transactions = array();
    while ($row = mysqli_fetch_assoc($tran_query)) {
      $transactions[] = array(
        'ref_id' => $row['ref_id'],
        'student' => $row['first_name'] . ' ' . $row['last_name'],
        'matric' => $row['matric_no'],
        'materials' => $row['materials'] ?? '',
        'amount' => $row['amount'],
        'date' => date('M j, Y', strtotime($row['created_at'])),
        'time' => date('h:i a', strtotime($row['created_at'])),
        'status' => $row['status']
      );
    }
    $statusRes = 'success';
  }
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'faculties' => $faculties,
  'departments' => $departments,
  'transactions' => $transactions,
  'restrict_faculty' => $restrict_faculty
);

header('Content-Type: application/json');
echo json_encode($responseData);
?>
