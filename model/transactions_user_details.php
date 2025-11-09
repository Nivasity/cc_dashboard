<?php
// Standalone endpoint and helper for fetching user details for manual transaction context
session_start();
require_once(__DIR__ . '/config.php');

function get_transaction_user_details($conn, $email)
{
  $status = 'failed';
  $message = '';
  $user = null;

  $email = trim($email ?? '');
  if ($email === '') {
    $message = 'Email address is required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = 'Please provide a valid email address.';
  } else {
    $sql = 'SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.matric_no, u.status, u.school, u.dept, s.name AS school_name, d.name AS dept_name, f.name AS faculty_name FROM users u LEFT JOIN schools s ON u.school = s.id LEFT JOIN depts d ON u.dept = d.id LEFT JOIN faculties f ON d.faculty_id = f.id WHERE u.email = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
      $user = $result->fetch_assoc();
      $status = 'success';
    } else {
      $message = 'No user found with the provided email address.';
    }
    $stmt->close();
  }

  return [
    'status' => $status,
    'message' => $message,
    'user' => $user
  ];
}

// If accessed directly, respond with JSON
if (isset($_GET['email'])) {
  $res = get_transaction_user_details($conn, $_GET['email']);
  header('Content-Type: application/json');
  echo json_encode($res);
  exit;
}

?>
