<?php
session_start();
require_once(__DIR__ . '/config.php');

$status = 'failed';
$message = '';
$faculties = [];
$restrict_faculty = false;

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

if ($admin_role == 5) {
  if ($admin_faculty != 0) {
    $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND id = $admin_faculty");
    $restrict_faculty = true;
  } else {
    $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
  }
} else {
  if ($school > 0) {
    $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $school ORDER BY name");
  } else {
    $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
  }
}

while ($row = mysqli_fetch_assoc($fac_query)) {
  $faculties[] = $row;
}
$status = 'success';

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'faculties' => $faculties,
  'restrict_faculty' => $restrict_faculty
]);
?>
