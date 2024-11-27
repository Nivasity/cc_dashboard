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

  $subject = "Confirmation: Business Information Updated";

  $e_message = "
    Hi $first_name,
      <br><br>
    We're happy to let you know that your request to update your business information has been successfully completed. Your profile now reflects the updated details you provided.
      <br><br>
    If you notice any discrepancies or need further adjustments, please don't hesitate to reach out to us by replying to this email. We're here to ensure your information is accurate and up to date.
      <br><br>
    Thank you for trusting Nivasity to support your business goals.
      <br><br>
    Best regards, <br>
    Support Team <br>
    Nivasity
  ";

  $user_result = mysqli_query($conn, "UPDATE users SET first_name = '$first_name', last_name = '$last_name', phone = '$phone', role = '$user_role' WHERE email = '$email'");
  
  if (mysqli_affected_rows($conn) > 0 || $org_result && $user_result) {
    $mailStatus = sendMail($subject, $e_message, $email);

    $statusRes = "success";
    $messageRes = "Profile successfully edited!";
  } else {
    $statusRes = "error";
    $messageRes = "No changes made. Please try again later! $user_id";
  }
}


if (isset($_POST['user_email_'])) {
  $user_fname = mysqli_real_escape_string($conn, $_POST['user_fname']);
  $user_role = mysqli_real_escape_string($conn, $_POST['user_role']);
  $email = mysqli_real_escape_string($conn, $_POST['user_email_']);
  $user_status = mysqli_real_escape_string($conn, $_POST['user_status']);

  mysqli_query($conn, "UPDATE users SET status = '$user_status' WHERE email = '$email'");

  if ($user_status === 'verified') {
    $subject = "Congratulations on Your Nivasity Account";
    
    if ($user_role == 'org_admin') {
      $e_message = "
          Hello $user_fname 🎉,
            <br><br>
          We're thrilled to let you know that your Nivasity account has been successfully verified! You're all set to start posting materials or events if you are a business owner.
            <br><br>
          <b>To begin receiving payments, please add a settlement account within your profile. This will allow funds from your sales to be transferred directly to you.</b>
            <br><br>
          Additionally, if you ever wish to switch to visitors mode to purchase materials or event tickets, you'll find this option on the sidebar for easy access.
            <br><br>
          If you have any questions or need assistance, feel free to reach out. We're here to help!
            <br><br>
          Best regards, <br>
          Support Team <br>
          Nivasity
        ";
    } else {
      $e_message = "
          Hello $user_fname 🎉,
            <br><br>
          We're thrilled to let you know that your Nivasity account has been successfully verified! You're all set to explore the platform and take advantage of everything it has to offer.
            <br><br>
          Here's how you can make the most of your account:
          <ul>
            <li>Purchase materials or event tickets on the store page whenever you want to.</li>
            <li>Keep an eye on events and resources tailored for users like you.</li>
          </ul>
          If you have any questions or need assistance, feel free to reach out. We're always here to help!
            <br><br>
          Best regards, <br>
          Support Team <br>
          Nivasity
        ";
    }
  } else if ($user_status == 'inreview') {
    $subject = "Update regarding Your Nivasity Account";

    $e_message = "
      Hi $user_fname,
        <br><br>
      We wanted to let you know that your Nivasity account is currently under review. This is part of our process to ensure all accounts comply with our platform's guidelines and provide a secure experience for everyone.
        <br><br>
      During this review period, some features of your account may be temporarily restricted. We aim to complete the review as quickly as possible and will notify you as soon as it is resolved.
        <br><br>
      If you have any questions or need further clarification, feel free to reach out to us by replying this email. We're here to help!. Thank you for your patience and understanding.
        <br><br>
      Best regards, <br>
      Support Team <br>
      Nivasity
    ";
  } else {
    $subject = "Update regarding Your Nivasity Account";

    $e_message = "
      Hi $user_fname,
        <br><br>
      We're writing to let you know that your Nivasity account has been temporarily suspended. This action was taken due to any of this reasons, a violation of platform guidelines, suspicious activity, or reported by a couple of users.
        <br><br>
      During this suspension, you will not be able to access your account or its features. However, we're here to help you resolve this issue! If you believe this was a mistake or want to address the matter, please reply to this email with any relevant details.
        <br><br>
      Our goal is to ensure that all users have a secure and smooth experience on Nivasity. We appreciate your understanding and look forward to assisting you.
        <br><br>
      Best regards, <br>
      Support Team <br>
      Nivasity
    ";
  }

  if (mysqli_affected_rows($conn) >= 1) {
    $mailStatus = sendMail($subject, $e_message, $email);

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