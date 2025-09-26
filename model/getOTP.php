<?php
session_start();
require_once 'config.php';
require_once 'mail.php';

header('Content-Type: application/json');

function generate_otp($length = 6) {
  $min = (int) str_pad('1', $length, '0');
  $max = (int) str_pad('', $length, '9');
  return (string) rand($min, $max);
}

if (isset($_POST['getOtp'])) {
  $action = $_POST['getOtp'];

  if ($action === 'get') {
    $email = trim($_POST['email'] ?? '');

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Please enter a valid email.',
        'reference' => null,
        'otp' => null,
      ]);
      exit();
    }

    // Check if the user exists in the database
    $email_safe = mysqli_real_escape_string($conn, $email);
    $result = mysqli_query($conn, "SELECT first_name, last_name FROM admins WHERE email = '{$email_safe}' LIMIT 1");
    $userData = mysqli_fetch_assoc($result);

    if (!$userData) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Email not found!',
        'reference' => null,
        'otp' => null,
      ]);
      exit();
    }

    // Generate a unique OTP not currently active
    $otp = generate_otp(6);
    $otp_safe = mysqli_real_escape_string($conn, $otp);

    // Ensure uniqueness among active keys (low probability, but check once)
    $check = mysqli_query($conn, "SELECT 1 FROM admin_keys WHERE _key = '{$otp_safe}' AND status = 'active' AND exp_date >= NOW() LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
      $otp = generate_otp(6);
      $otp_safe = mysqli_real_escape_string($conn, $otp);
    }

    // Insert OTP into admin_keys with 10 minutes expiry
    $exp = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $exp_safe = mysqli_real_escape_string($conn, $exp);
    mysqli_query($conn, "INSERT INTO admin_keys (_key, exp_date, status) VALUES ('{$otp_safe}', '{$exp_safe}', 'active')");

    // Send email
    $fullName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
    $subject = 'Your Nivasity OTP Code';
    $body = '<p>Hi ' . htmlspecialchars($fullName) . ',</p>' .
            '<p>Your one-time password (OTP) is:</p>' .
            '<h2 style="letter-spacing:3px;margin:8px 0">' . htmlspecialchars($otp) . '</h2>' .
            '<p>This code expires in 10 minutes. If you did not request this, please ignore this email.</p>';

    $mailStatus = sendMail($subject, $body, $email);

    if ($mailStatus === 'success') {
      // Persist email in session for verification step
      $_SESSION['reset_email'] = $email;
      echo json_encode([
        'status' => 'success',
        'message' => 'OTP sent to your email.',
        'reference' => '',
        'otp' => null,
      ]);
    } else {
      // Clean up the OTP we just inserted if email failed
      mysqli_query($conn, "UPDATE admin_keys SET status = 'inactive' WHERE _key = '{$otp_safe}'");
      echo json_encode([
        'status' => 'error',
        'message' => 'Failed to send OTP email. Please try again.',
        'reference' => null,
        'otp' => null,
      ]);
    }

    exit();
  }

  if ($action === 'verify') {
    $password_raw = $_POST['password'] ?? '';
    $otp_raw = $_POST['otp'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';

    if (empty($email)) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Session expired. Please restart reset process.',
        'reference' => null,
        'otp' => null,
      ]);
      exit();
    }

    if (empty($password_raw) || empty($otp_raw)) {
      echo json_encode([
        'status' => 'error',
        'message' => 'OTP and new password are required.',
        'reference' => null,
        'otp' => null,
      ]);
      exit();
    }

    $otp_safe = mysqli_real_escape_string($conn, trim($otp_raw));
    $email_safe = mysqli_real_escape_string($conn, $email);
    $password_md5 = md5($password_raw);

    // Verify OTP against admin_keys table
    $otpQuery = mysqli_query($conn, "SELECT _key FROM admin_keys WHERE _key = '{$otp_safe}' AND status = 'active' AND exp_date >= NOW() LIMIT 1");
    if (mysqli_num_rows($otpQuery) === 0) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired OTP.',
        'reference' => null,
        'otp' => null,
      ]);
      exit();
    }

    // Update password
    mysqli_query($conn, "UPDATE admins SET password = '{$password_md5}' WHERE email = '{$email_safe}' LIMIT 1");
    // Invalidate OTP so it cannot be reused
    mysqli_query($conn, "UPDATE admin_keys SET status = 'used' WHERE _key = '{$otp_safe}' LIMIT 1");

    // Clear session email
    unset($_SESSION['reset_email']);

    echo json_encode([
      'status' => 'success',
      'message' => 'Password reset successfully.',
      'reference' => null,
      'otp' => null,
    ]);

    exit();
  }
}
?>
