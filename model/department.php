<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_adminId'];
$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = mysqli_fetch_array(mysqli_query($conn, "SELECT school FROM admins WHERE id = $user_id"))[0];

if (isset($_POST['dept_edit'])) {
  $dept_id = $_POST['dept_id'];
  $status = $_POST['status'];

  if ($status !== 'delete') {
    $school_condition = ($admin_role == 5) ? " AND school_id = $admin_school" : '';
    mysqli_query($conn, "UPDATE depts SET status = '$status' WHERE id = $dept_id$school_condition");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Status changed successfully!";
      log_audit_event($conn, $user_id, 'status_change', 'department', $dept_id, [
        'new_status' => $status,
        'restricted_school' => $admin_role == 5 ? $admin_school : null
      ]);
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    $school_condition = ($admin_role == 5) ? " AND school_id = $admin_school" : '';
    mysqli_query($conn, "DELETE FROM depts WHERE id = $dept_id$school_condition");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "dept deleted successfully!";
      log_audit_event($conn, $user_id, 'delete', 'department', $dept_id, [
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
  $dept_id = mysqli_real_escape_string($conn, $_POST['dept_id']);
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $faculty_id = mysqli_real_escape_string($conn, $_POST['faculty']);

  $added = ($dept_id == 0) ? " AND school_id = $school_id AND faculty_id = $faculty_id" : " AND id != $dept_id AND school_id = $school_id AND faculty_id = $faculty_id";
  $dept_query = mysqli_query($conn, "SELECT * FROM depts WHERE name = '$name'$added");

  if (mysqli_num_rows($dept_query) >= 1) {
    $messageRes = "A dept already exist with this name - $name";
  } else {
    if ($dept_id == 0) {
      mysqli_query($conn, "INSERT INTO depts (name, school_id, faculty_id) VALUES ('$name', $school_id, $faculty_id)");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "dept successfully added!";
        $insert_id = mysqli_insert_id($conn);
        log_audit_event($conn, $user_id, 'create', 'department', $insert_id, [
          'name' => $name,
          'school_id' => $school_id,
          'faculty_id' => $faculty_id
        ]);
      } else {
        $statusRes = "error";
        $messageRes = "Internal Server Error. Please try again later!";
      }
    } else {
      mysqli_query($conn, "UPDATE depts SET name = '$name', school_id = '$school_id', faculty_id = '$faculty_id' WHERE id = $dept_id");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "dept successfully edited!";
        log_audit_event($conn, $user_id, 'update', 'department', $dept_id, [
          'name' => $name,
          'school_id' => $school_id,
          'faculty_id' => $faculty_id
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

// Enschool_id the data as JSON and send it
echo json_encode($responseData);
