<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = $mailStatus = $messageRes = 'failed';
$student_fn = $student_ln = $student_email = $student_phone = $student_status = null;
$student_sch = $student_dept = $student_matric = $student_role = null;
$student_adm_year = null;
$acct_no = $acct_name = $acct_bank = 'N/A';

$user_id = $_SESSION['nivas_adminId'];
$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_school = $admin_faculty = null;
if ($admin_role == 5) {
  $admin_info = mysqli_fetch_array(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $user_id"));
  $admin_school = $admin_info['school'];
  $admin_faculty = $admin_info['faculty'];
}

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
    $student_school = $student['school'];
    $student_dept_id = $student['dept'] == '' ? 0 : $student['dept'];

    if ($admin_role == 5) {
      if ($student_school != $admin_school) {
        $statusRes = "error";
        $messageRes = "Student not found in your school!";
      } elseif ($admin_faculty && $admin_faculty != 0) {
        $dept_check = mysqli_query($conn, "SELECT id FROM depts WHERE id = $student_dept_id AND school_id = $admin_school AND faculty_id = $admin_faculty");
        if ($student_dept_id == 0 || mysqli_num_rows($dept_check) == 0) {
          $statusRes = "error";
          $messageRes = "Student not associated with your faculty!";
        }
      }
    }

    if ($statusRes != 'error') {
      $statusRes = "success";
      $messageRes = "Student Found!";
      $student_id = $student['id'];
      $student_fn = $student['first_name'];
      $student_ln = $student['last_name'];
      $student_email = $student['email'];
      $student_phone = $student['phone'];
      $student_status = $student['status'];
      $student_matric = $student['matric_no'];
      $student_role = $student['role'];
      $student_sch = $student_school;
      $student_dept = $student_dept_id;
      $student_adm_year = $student['adm_year'];

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
  $adm_year = isset($_POST['adm_year']) ? mysqli_real_escape_string($conn, $_POST['adm_year']) : '';
  $role = mysqli_real_escape_string($conn, $_POST['role']);
  $student_lookup = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");
  $student_target = $student_lookup ? mysqli_fetch_assoc($student_lookup) : null;
  $target_student_id = $student_target ? (int) $student_target['id'] : 0;

  $subject = "Confirmation: Academic Information Update Successful";

  $e_message = "
    Hi $first_name,
      <br><br>
    We're pleased to inform you that your request to update your academic information has been successfully processed. Your account now reflects the updated details as requested.
      <br><br>
    If you notice any discrepancies or need further adjustments, please don't hesitate to reach out to us by replying to this email. We're here to ensure your information is accurate and up to date.
      <br><br>
    Thank you for choosing Nivasity to support your academic journey!
      <br><br>
    Best regards, <br>
    Support Team <br>
    Nivasity
  ";

  mysqli_query($conn, "UPDATE users SET first_name = '$first_name', last_name = '$last_name', phone = '$phone', school = $school, dept = $dept, matric_no = '$matric_no', adm_year = '$adm_year', role = '$role' WHERE email = '$email'");

  if (mysqli_affected_rows($conn) >= 1) {
    $mailStatus = sendMail($subject, $e_message, $email);

    $statusRes = "success";
    $messageRes = "Profile successfully edited!";
    if (!empty($user_id)) {
      log_audit_event($conn, $user_id, 'update', 'student', $target_student_id ?: null, [
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'school' => $school,
        'dept' => $dept,
        'matric_no' => $matric_no,
        'adm_year' => $adm_year,
        'role' => $role
      ]);
    }
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
}


if (isset($_POST['student_email_'])) {
  $student_fname = mysqli_real_escape_string($conn, $_POST['student_fname']);
  $student_role = mysqli_real_escape_string($conn, $_POST['student_role']);
  $email = mysqli_real_escape_string($conn, $_POST['student_email_']);
  $student_status = mysqli_real_escape_string($conn, $_POST['student_status']);
  $student_lookup = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");
  $student_target = $student_lookup ? mysqli_fetch_assoc($student_lookup) : null;
  $target_student_id = $student_target ? (int) $student_target['id'] : 0;

  mysqli_query($conn, "UPDATE users SET status = '$student_status' WHERE email = '$email'");

  
  if ($student_status === 'verified') {
    $subject = "Congratulations on Your Nivasity Account";
    
    if ($student_role == 'hoc') {
      $e_message = "
          Hello $student_fname 🎉,
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
          Hello $student_fname 🎉,
            <br><br>
          We're thrilled to let you know that your Nivasity account has been successfully verified! You're all set to explore the platform and take advantage of everything it has to offer.
            <br><br>
          Here's how you can make the most of your account:
          <ul>
            <li>Purchase materials or event tickets on the store page whenever you want to.</li>
            <li>Keep an eye on events and resources tailored for students like you.</li>
          </ul>
          If you have any questions or need assistance, feel free to reach out. We're always here to help!
            <br><br>
          Best regards, <br>
          Support Team <br>
          Nivasity
        ";
    }
  } else if ($student_status == 'inreview') {
    $subject = "Update regarding Your Nivasity Account";

    $e_message = "
      Hi $student_fname,
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
      Hi $student_fname,
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
    if (!empty($user_id)) {
      log_audit_event($conn, $user_id, 'status_change', 'student', $target_student_id ?: null, [
        'email' => $email,
        'new_status' => $student_status,
        'role' => $student_role,
        'mail_status' => $mailStatus
      ]);
    }
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
  "student_adm_year" => "$student_adm_year",
  "acct_no" => "$acct_no",
  "acct_name" => "$acct_name",
  "acct_bank" => "$acct_bank",
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
