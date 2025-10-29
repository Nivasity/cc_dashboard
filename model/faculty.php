<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_adminId'];
$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = mysqli_fetch_array(mysqli_query($conn, "SELECT school FROM admins WHERE id = $user_id"))[0];

if (isset($_POST['faculty_edit'])) {
  $faculty_id = $_POST['faculty_id'];
  $status = $_POST['status'];

  if ($status !== 'delete') {
    $school_condition = ($admin_role == 5) ? " AND school_id = $admin_school" : '';
    mysqli_query($conn, "UPDATE faculties SET status = '$status' WHERE id = $faculty_id$school_condition");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Status changed successfully!";
      log_audit_event($conn, $user_id, 'status_change', 'faculty', $faculty_id, [
        'new_status' => $status,
        'restricted_school' => $admin_role == 5 ? $admin_school : null
      ]);
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    $school_condition = ($admin_role == 5) ? " AND school_id = $admin_school" : '';
    mysqli_query($conn, "DELETE FROM faculties WHERE id = $faculty_id$school_condition");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "faculty deleted successfully!";
      log_audit_event($conn, $user_id, 'delete', 'faculty', $faculty_id, [
        'restricted_school' => $admin_role == 5 ? $admin_school : null
      ]);
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  }
} else {
  $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
  if ($admin_role == 5) {
    $school_id = $admin_school;
  }
  $faculty_id = mysqli_real_escape_string($conn, $_POST['faculty_id']);
  $name = mysqli_real_escape_string($conn, $_POST['name']);

  $added = ($faculty_id == 0) ? " AND school_id = $school_id" : " AND id != $faculty_id AND school_id = $school_id";
  $fac_query = mysqli_query($conn, "SELECT * FROM faculties WHERE name = '$name'$added");

  if (mysqli_num_rows($fac_query) >= 1) {
    $messageRes = "A faculty already exist with this name - $name";
  } else {
    if ($faculty_id == 0) {
      mysqli_query($conn, "INSERT INTO faculties (name, school_id) VALUES ('$name', $school_id)");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "faculty successfully added!";
        $insert_id = mysqli_insert_id($conn);
        log_audit_event($conn, $user_id, 'create', 'faculty', $insert_id, [
          'name' => $name,
          'school_id' => $school_id
        ]);
      } else {
        $statusRes = "error";
        $messageRes = "Internal Server Error. Please try again later!";
      }
    } else {
      mysqli_query($conn, "UPDATE faculties SET name = '$name', school_id = '$school_id' WHERE id = $faculty_id");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "faculty successfully edited!";
        log_audit_event($conn, $user_id, 'update', 'faculty', $faculty_id, [
          'name' => $name,
          'school_id' => $school_id
        ]);
      } else {
        $statusRes = "error";
        $messageRes = "No changes made!";
      }
    }
  }
}

$responseData = array(
  "status" => "$statusRes",
  "message" => "$messageRes",
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

echo json_encode($responseData);
