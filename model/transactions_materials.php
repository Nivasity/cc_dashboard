<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$materials = [];

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
$user_school = intval($_GET['user_school'] ?? 0);

$material_sql = "SELECT m.id, m.title, m.course_code, m.code, m.price FROM manuals m LEFT JOIN depts d ON m.dept = d.id WHERE m.status = 'open'";
if ($user_school > 0) {
  $material_sql .= " AND m.school_id = $user_school";
} elseif ($admin_role == 5) {
  $material_sql .= " AND m.school_id = $admin_school";
  if ($admin_faculty != 0) {
    $material_sql .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
} else {
  if ($school > 0) {
    $material_sql .= " AND m.school_id = $school";
  }
  if ($faculty != 0) {
    $material_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
  }
}
if ($dept > 0 && $user_school == 0) {
  $material_sql .= " AND m.dept = $dept";
}
$material_sql .= " ORDER BY m.title ASC";

$material_query = mysqli_query($conn, $material_sql);
while ($row = mysqli_fetch_assoc($material_query)) {
  $materials[] = [
    'id' => (int)$row['id'],
    'title' => $row['title'],
    'course_code' => $row['course_code'],
    'code' => $row['code'],
    'price' => (int)$row['price']
  ];
}
$status = 'success';
if (count($materials) === 0) {
  $message = 'No course materials match the selected filters.';
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'materials' => $materials
]);
?>
