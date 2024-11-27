<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = $messageRes = 'failed';
$student_fn = $student_ln = $student_email = $student_phone = $student_status = null;
$student_sch = $student_dept = $student_matric = $student_role = null;
$acct_no = $acct_name = $acct_bank = 'N/A';

$user_id = $_SESSION['nivas_adminId'];

if (isset($_POST['student_data'])) {
  $student_data = mysqli_real_escape_string($conn, $_POST['student_data']);

  $columns = array("email", "phone", "matric_no");

  $query = "SELECT * FROM users WHERE ";

  $conditions = array();
  foreach ($columns as $column) {
    $conditions[] = "$column = '$student_data' AND role != 'org_admin'";
  }
  $query .= implode(" OR ", $conditions);

  // Limit the search to return only one row
  $query .= " LIMIT 1";

  $result = mysqli_query($conn, $query);

  if (mysqli_num_rows($result) > 0) {
    $student = mysqli_fetch_array($result);
    $statusRes = "success";
    $messageRes = "Student Found!";
    $student_id = $student['id'];
    $student_fn = $student['first_name'];
    $student_ln = $student['last_name'];
    $student_email = $student['email'];
    $student_phone = $student['phone'];
    $student_status = $student['status'];
    $student_sch = $student['school'];
    $student_dept = $student['dept'] == '' ? 0 : $student['dept'];
    $student_matric = $student['matric_no'];
    $student_role = $student['role'];

    if ($student_role == 'hoc') {
      $settlement_query = mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $student_id");

      if (mysqli_num_rows($settlement_query) > 0) {
        $settlement = mysqli_fetch_array($settlement_query);
  
        $acct_no = $settlement['acct_number'];
        $acct_name = $settlement['acct_name'];
        $acct_bank = $settlement['bank'];
      } else {
        $acct_no = $acct_name = $acct_bank = "NULL";
      }
    }
  } else {
    $statusRes = "error";
    $messageRes = "Student not found!";
  }
}

if (isset($_POST['edit_profile'])) {
  $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
  $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $school = mysqli_real_escape_string($conn, $_POST['school']);
  $dept = mysqli_real_escape_string($conn, $_POST['dept']);
  $matric_no = mysqli_real_escape_string($conn, $_POST['matric_no']);
  $role = mysqli_real_escape_string($conn, $_POST['role']);

  mysqli_query($conn, "UPDATE users SET first_name = '$first_name', last_name = '$last_name', phone = '$phone', school = $school, dept = $dept, matric_no = '$matric_no', role = '$role' WHERE email = '$email'");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Profile successfully edited!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
}


if (isset($_POST['student_email_'])) {
  $email = mysqli_real_escape_string($conn, $_POST['student_email_']);
  $student_status = mysqli_real_escape_string($conn, $_POST['student_status']);

  mysqli_query($conn, "UPDATE users SET status = '$student_status' WHERE email = '$email'");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Status changed successfully!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
}

$responseData = array(
  "status" => "$statusRes",
  "message" => "$messageRes",
  "student_fn" => "$student_fn",
  "student_ln" => "$student_ln",
  "student_email" => "$student_email",
  "student_phone" => "$student_phone",
  "student_status" => "$student_status",
  "student_sch" => "$student_sch",
  "student_dept" => "$student_dept",
  "student_matric" => "$student_matric",
  "student_role" => "$student_role",
  "acct_no" => "$acct_no",
  "acct_name" => "$acct_name",
  "acct_bank" => "$acct_bank",
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>