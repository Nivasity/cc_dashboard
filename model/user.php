<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = $messageRes = 'failed';
$user_fn = $user_ln = $user_email = $user_phone = $user_status = $user_role = null;
$acct_no = $acct_name = $acct_bank = 'N/A';
$business_name = $business_address = $web_url = $work_email = $socials = 'N/A';

$admin_id = $_SESSION['nivas_adminId'];

if (isset($_POST['user_data'])) {
  $user_data = mysqli_real_escape_string($conn, $_POST['user_data']);

  $columns = array("email", "phone");

  $query = "SELECT * FROM users WHERE ";

  $conditions = array();
  foreach ($columns as $column) {
    $conditions[] = "$column = '$user_data' AND (role = 'org_admin' OR role = 'visitor')";
  }
  $query .= implode(" OR ", $conditions);

  // Limit the search to return only one row
  $query .= " LIMIT 1";

  $result = mysqli_query($conn, $query);

  if (mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_array($result);
    $statusRes = "success";
    $messageRes = "User Found!";
    $user_id = $user['id'];
    $user_fn = $user['first_name'];
    $user_ln = $user['last_name'];
    $user_email = $user['email'];
    $user_phone = $user['phone'];
    $user_status = $user['status'];
    $user_role = $user['role'];

    if ($user_role == 'org_admin') {
      $business_query = mysqli_query($conn, "SELECT * FROM organisation WHERE user_id = $user_id");

      if (mysqli_num_rows($business_query) > 0) {
        $business = mysqli_fetch_array($business_query);
  
        $business_name = $business['business_name'];
        $business_address = $business['business_address'];
        $web_url = $business['web_url'];
        $work_email = $business['work_email'];
        $socials = $business['socials'];
      } else {
        $business_name = $business_address = $web_url = $work_email = $socials = "NULL";
      }

      $settlement_query = mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $user_id");

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
    $messageRes = "User not found!";
  }
}

if (isset($_POST['edit_profile'])) {
  $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
  $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $user_role = mysqli_real_escape_string($conn, $_POST['role']);
  $business_name = mysqli_real_escape_string($conn, $_POST['business_name']);
  $business_address = mysqli_real_escape_string($conn, $_POST['business_address']);
  $web_url = mysqli_real_escape_string($conn, $_POST['web_url']);
  $work_email = mysqli_real_escape_string($conn, $_POST['work_email']);
  $socials = mysqli_real_escape_string($conn, $_POST['socials']);

  $user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
  $user_id = mysqli_fetch_array($user_query)['id'];

  $org_result = mysqli_query($conn, "UPDATE organisation 
                SET business_name = '$business_name', business_address = '$business_address', web_url = '$web_url', work_email = '$work_email', socials = '$socials' 
                WHERE user_id = $user_id");

  $user_result = mysqli_query($conn, "UPDATE users SET first_name = '$first_name', last_name = '$last_name', phone = '$phone', role = '$user_role' WHERE email = '$email'");
  
  if (mysqli_affected_rows($conn) > 0 || $org_result && $user_result) {
    $statusRes = "success";
    $messageRes = "Profile successfully edited!";
  } else {
    $statusRes = "error";
    $messageRes = "No changes made. Please try again later! $user_id";
  }
}


if (isset($_POST['user_email_'])) {
  $email = mysqli_real_escape_string($conn, $_POST['user_email_']);
  $user_status = mysqli_real_escape_string($conn, $_POST['user_status']);

  mysqli_query($conn, "UPDATE users SET status = '$user_status' WHERE email = '$email'");

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
  "user_fn" => "$user_fn",
  "user_ln" => "$user_ln",
  "user_email" => "$user_email",
  "user_phone" => "$user_phone",
  "user_status" => "$user_status",
  "user_role" => "$user_role",
  "business_name" => "$business_name",
  "business_address" => "$business_address",
  "web_url" => "$web_url",
  "work_email" => "$work_email",
  "socials" => "$socials",
  "acct_no" => "$acct_no",
  "acct_name" => "$acct_name",
  "acct_bank" => "$acct_bank",
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>