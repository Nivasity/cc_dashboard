<?php
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

if (isset($_POST['admin_manage'])) {
  $admin_id = intval($_POST['admin_id']);
  $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
  $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $gender = mysqli_real_escape_string($conn, $_POST['gender']);
  $role = intval($_POST['role']);
  $school = intval($_POST['school']);
  $faculty = intval($_POST['faculty']);
  if ($role == 5) {
    $school_sql = $school > 0 ? $school : 'NULL';
    $faculty_sql = $faculty > 0 ? $faculty : 'NULL';
  } else {
    $school_sql = 'NULL';
    $faculty_sql = 'NULL';
  }

  if ($admin_id == 0) {
    $password = md5($_POST['password']);
    $user_query = mysqli_query($conn, "SELECT id FROM admins WHERE email = '$email'");
    if (mysqli_num_rows($user_query) >= 1) {
      $statusRes = "denied";
      $messageRes = "A user has been associated with this email. <br> Please try again with another email!";
    } else {
      mysqli_query($conn, "INSERT INTO admins (first_name, last_name, email, phone, gender, role, school, faculty, password) VALUES ('$first_name', '$last_name', '$email', '$phone', '$gender', $role, $school_sql, $faculty_sql, '$password')");
      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "Admin successfully added!";
      } else {
        $statusRes = "error";
        $messageRes = "Internal Server Error. Please try again later!";
      }
    }
  } else {
    $password_sql = '';
    if (!empty($_POST['password'])) {
      $password = md5($_POST['password']);
      $password_sql = ", password = '$password'";
    }
    mysqli_query($conn, "UPDATE admins SET first_name = '$first_name', last_name = '$last_name', email = '$email', phone = '$phone', gender = '$gender', role = $role, school = $school_sql, faculty = $faculty_sql" . $password_sql . " WHERE id = $admin_id");
    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Admin successfully updated!";
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  }
}

if (isset($_POST['admin_toggle'])) {
  $admin_id = intval($_POST['admin_id']);
  $action = $_POST['action'] ?? '';
  if (!$admin_id || !in_array($action, ['activate', 'deactivate'], true)) {
    $statusRes = 'error';
    $messageRes = 'Invalid request. Please try again later!';
  } else {
    $target_status = $action === 'activate' ? 'active' : 'deactivated';
    mysqli_query($conn, "UPDATE admins SET status = '$target_status' WHERE id = $admin_id");
    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = 'success';
      $messageRes = $action === 'activate' ? 'Admin activated successfully!' : 'Admin deactivated successfully!';
    } else {
      $statusRes = 'error';
      $messageRes = 'Update failed or no changes made. Please try again later!';
    }
  }
}

if (isset($_POST['signup'])) {
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $password = md5($_POST['password']);

  $user_query = mysqli_query($conn, "SELECT * FROM admins WHERE email = '$email'");

  if (mysqli_num_rows($user_query) >= 1) {
    $statusRes = "denied";
    $messageRes = "A user has been associated with this email. <br> Please try again with another email!";
  } else {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $school = mysqli_real_escape_string($conn, $_POST['school']);

    mysqli_query($conn, "INSERT INTO admins (first_name, last_name, email, phone, password, role, school, gender) 
      VALUES ('$first_name', '$last_name', '$email', '$phone', '$password', '$role', $school, '$gender')");

    $user_id = mysqli_insert_id($conn);

    if (mysqli_affected_rows($conn) < 1) {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    } else {

      // Generate a unique verification code
      $verificationCode = generateVerificationCode(12);

      // Check if the code already exists, regenerate if needed
      while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
        $verificationCode = generateVerificationCode(12);
      }

      mysqli_query($conn, "INSERT INTO verification_code (user_id, code) VALUES ($user_id, '$verificationCode')");

      $subject = "Verify Your Account on NIVASITY";
      $body = "Hello $first_name,
      <br><br>
      Welcome to Nivasity! We're excited to have you on board. To ensure the security of your account and to provide you with the best experience, we kindly ask you to verify your email address.
      <br><br>
      Click on the following link to verify your account: <a href='https://nivasity.com/setup.html?verify=$verificationCode'>Verify Account</a>
      <br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://nivasity.com/setup.html?verify=$verificationCode
      <br><br>
      Thank you for choosing Nivasity. We look forward to serving you!
      <br><br>
      Best regards,
      <br>The Nivasity Team";

      // Call the sendMail function and capture the status
      $mailStatus = sendMail($subject, $body, $email);

      // Check the status
      if ($mailStatus === "success") {
        $statusRes = "success";
        $messageRes = "Great news! You're one step away from completing your signup.We've sent an account verification link to your email address. <br><br>Please check your inbox (and your spam folder, just in case) for an email from us. Click on the verification link to confirm your account and gain full access.";
      } else {
        $statusRes = "error";
        $messageRes = "Internal Server Error. Please try again later!";
      }
    }
  }
}

if (isset($_POST['edit_profile'])) {
  session_start();
  $user_id = $_SESSION['nivas_adminId'];
  $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
  $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $picture = $_FILES['upload']['name'];

  if ($picture !== 'user.jpg') {
    $tempname = $_FILES['upload']['tmp_name'];
    $extension = pathinfo($picture, PATHINFO_EXTENSION);
    $picture = "user" . time() . "." . $extension;
    $destination = "../assets/images/users/{$picture}";

    $last_picture = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM admins WHERE id = $user_id"))['profile_pic'];

    if ($last_picture !== 'user.jpg') {
      unlink("../assets/images/users/{$last_picture}");
    }
    move_uploaded_file($tempname, $destination);
  }

  mysqli_query($conn, "UPDATE admins SET first_name = '$firstname', last_name = '$lastname', profile_pic = '$picture', phone = '$phone' WHERE id = $user_id");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Profile successfully edited!.";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
}

if (isset($_POST['change_password'])) {
  session_start();
  $user_id = $_SESSION['nivas_adminId'];
  $curr_password = md5($_POST['curr_password']);
  $new_password = md5($_POST['new_password']);

  // Check if user data exists
  $user_query = mysqli_query($conn, "SELECT * FROM admins WHERE id = $user_id AND password = '$curr_password'");

  if (mysqli_num_rows($user_query) == 1) {
    mysqli_query($conn, "UPDATE admins SET password = '$new_password' WHERE id = $user_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Password successfully changed!.";
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Oops! your current password is incorrect.";
  }
}

if (isset($_POST['login'])) {
  $email = mysqli_real_escape_string($conn, $_POST['email']);

  if ($_POST['login'] !== 'g_signin') {
    $password = md5($_POST['password']);

    // Check if user data exists
    $user_query = mysqli_query($conn, "SELECT * FROM admins WHERE email = '$email' AND password = '$password'");
  } else {
    // Check if user data exists
    $user_query = mysqli_query($conn, "SELECT * FROM admins WHERE email = '$email'");
  } 
  if (mysqli_num_rows($user_query) == 1) {
    session_start();
    $user = mysqli_fetch_array($user_query);
    if ($user['status'] == 'deactivated') {
      $statusRes = "denied";
      $messageRes = "Your account is temporarily suspended. Contact our Admin for help.";
    } else {
      $_SESSION['nivas_adminId'] = $user['id'];
      $_SESSION['nivas_adminName'] = $user['first_name'];
      $_SESSION['nivas_adminRole'] = $user['role'];

      $statusRes = "success";
      $messageRes = "Logged in successfully!";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Email or Password incorrect!";
  }
}

if (isset($_POST['deactivate_acct'])) {
  session_start();
  $user_id = $_SESSION['nivas_adminId'];
  $password = md5($_POST['password']);

  // Check if user data exists
  $user_query = mysqli_query($conn, "SELECT * FROM admins WHERE id = $user_id AND password = '$password'");

  if (mysqli_num_rows($user_query) == 1) {
    mysqli_query($conn, "UPDATE admins SET status = 'deactivated' WHERE id = $user_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Account successfully deleted!.";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Password incorrect! Please try again.";
  }
}

if (isset($_POST['logout'])) {
  session_start();
  session_unset();
  session_destroy();

  $statusRes = "success";
  $messageRes = "You have successfully logged out!";
}

$responseData = array(
  "status" => "$statusRes",
  "message" => "$messageRes"
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>