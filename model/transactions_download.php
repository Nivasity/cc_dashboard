<?php
// Standalone endpoint to download transactions CSV
session_start();
require_once(__DIR__ . '/config.php');

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = $admin_faculty = 0;
if ($admin_role == 5 && $admin_id) {
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
  if ($info) {
    $admin_school = (int)$info['school'];
    $admin_faculty = (int)$info['faculty'];
  }
}

$school = intval($_GET['school'] ?? 0);
$faculty = intval($_GET['faculty'] ?? 0);
$dept = intval($_GET['dept'] ?? 0);
$material_id = intval($_GET['material_id'] ?? 0);
$date_range = $_GET['date_range'] ?? '7';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($admin_role == 5) {
  $school = $admin_school;
  if ($admin_faculty != 0) {
    $faculty = $admin_faculty;
  }
}

// Always compute the sum of material prices per transaction, but keep the
// original transaction amount so we can choose which one to export based
// on context (overall transactions list vs. single-material export).
$tran_sql = "SELECT t.ref_id, u.first_name, u.last_name, u.matric_no, u.adm_year, " .
  "COALESCE(s.name, '') AS school_name, COALESCE(f.name, '') AS faculty_name, COALESCE(d.name, '') AS dept_name, " .
  "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code, ' (', b.price, ')') SEPARATOR ' | ') AS materials, " .
  "SUM(b.price) AS material_amount, t.amount AS transaction_amount, t.status, t.created_at " .
  "FROM transactions t " .
  "JOIN users u ON t.user_id = u.id " .
  "JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
  "JOIN manuals m ON b.manual_id = m.id " .
  "LEFT JOIN depts d ON m.dept = d.id " .
  "LEFT JOIN faculties f ON m.faculty = f.id " .
  "LEFT JOIN schools s ON b.school_id = s.id " .
  "WHERE 1=1";
if ($school > 0) {
  $tran_sql .= " AND b.school_id = $school";
}
if ($faculty != 0) {
  $tran_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
}
if ($dept > 0) {
  $tran_sql .= " AND m.dept = $dept";
}
if ($material_id > 0) {
  $tran_sql .= " AND m.id = $material_id";
}

// Build the date filter
$date_filter = "";
if ($date_range === 'custom' && $start_date && $end_date) {
  $start_date = mysqli_real_escape_string($conn, $start_date);
  $end_date = mysqli_real_escape_string($conn, $end_date);
  $date_filter = " AND t.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
} elseif ($date_range !== 'all') {
  $days = intval($date_range);
  if ($days > 0) {
    $date_filter = " AND t.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
  }
}
$tran_sql .= $date_filter;

$tran_sql .= " GROUP BY t.id, t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no, u.adm_year, s.name, f.name, d.name ORDER BY t.created_at DESC";
$tran_query = mysqli_query($conn, $tran_sql);

header('Content-Type: text/csv; charset=utf-8');
$filename = 'transactions_' . date('Ymd_His') . '.csv';
if ($material_id > 0) {
  $code_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT code FROM manuals WHERE id = $material_id LIMIT 1"));
  $mcode = $code_res && !empty($code_res['code']) ? preg_replace('/[^A-Za-z0-9_-]/', '_', $code_res['code']) : 'material_' . $material_id;
  $filename = 'material_' . $mcode . '_transactions_' . date('Ymd_His') . '.csv';
}
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// Always treat "Total Paid" as the sum of material prices from manuals_bought,
// both for the course materials export and the main transactions table export.
fputcsv($out, ['Ref Id', 'Student Name', 'Matric No', 'Admission Year', 'School', 'Faculty/College', 'Department', 'Materials', 'Total Paid', 'Date', 'Time', 'Status']);
if ($tran_query) {
  while ($row = mysqli_fetch_assoc($tran_query)) {
    $dateStr = date('M j, Y', strtotime($row['created_at']));
    $timeStr = date('h:i a', strtotime($row['created_at']));
    $statusStr = $row['status'];
    // Use the summed material prices; fall back to transaction amount only if needed.
    $amountValue = isset($row['material_amount'])
      ? (int)$row['material_amount']
      : (int)($row['transaction_amount'] ?? 0);
    fputcsv($out, [
      $row['ref_id'],
      trim($row['first_name'] . ' ' . $row['last_name']),
      $row['matric_no'],
      $row['adm_year'],
      $row['school_name'],
      $row['faculty_name'],
      $row['dept_name'],
      $row['materials'],
      $amountValue,
      $dateStr,
      $timeStr,
      $statusStr
    ]);
  }
}
fclose($out);
exit;
?>
