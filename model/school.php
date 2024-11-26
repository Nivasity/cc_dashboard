<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_adminId'];

if (isset($_POST['school_edit'])) {
  $school_id = $_POST['school_id'];
  $status = $_POST['status'];

  if ($status !== 'delete') {
    mysqli_query($conn, "UPDATE schools SET status = '$status' WHERE id = $school_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Status changed successfully!";
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    mysqli_query($conn, "DELETE FROM schools WHERE id = $school_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "School deleted successfully!";
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  }

} else {
  $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $code = mysqli_real_escape_string($conn, $_POST['code']);

  $added = ($school_id == 0) ? '' : " AND id != $school_id";
  $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE code = '$code'$added");

  if (mysqli_num_rows($school_query) >= 1) {
    $messageRes = "A School already exist with this code - $code";
  } else {
    if ($school_id == 0) {
      mysqli_query($conn, "INSERT INTO schools (name, code) VALUES ('$name', '$code')");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "School successfully added!";
      } else {
        $statusRes = "error";
        $messageRes = "Internal Server Error. Please try again later!";
      }
    } else {
      mysqli_query($conn, "UPDATE schools SET name = '$name', code = '$code' WHERE id = $school_id");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "School successfully edited!";
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

// Encode the data as JSON and send it
echo json_encode($responseData);
