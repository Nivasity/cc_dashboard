<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = 'failed';
$messageRes = '';
$tickets = null;
$ticket = null;

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = 0;
if ($admin_role == 5 && $admin_id) {
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school FROM admins WHERE id = $admin_id"));
  $admin_school = intval($info['school'] ?? 0);
}

if (isset($_GET['fetch'])) {
  $fetch = $_GET['fetch'];

  if ($fetch === 'tickets') {
    $status = mysqli_real_escape_string($conn, strtolower($_GET['status'] ?? 'open'));
    $sql = "SELECT st.id, st.code, st.subject, st.message, st.status, st.response, st.response_time, st.created_at, u.first_name, u.last_name, u.email, u.school FROM support_tickets st JOIN users u ON st.user_id = u.id WHERE 1=1";
    if ($status === 'open' || $status === 'closed') {
      $sql .= " AND st.status = '$status'";
    }
    if ($admin_role == 5 && $admin_school > 0) {
      $sql .= " AND u.school = $admin_school";
    }
    $sql .= " ORDER BY st.created_at DESC";
    $q = mysqli_query($conn, $sql);
    $tickets = array();
    while ($row = mysqli_fetch_assoc($q)) {
      $tickets[] = array(
        'code' => $row['code'],
        'subject' => $row['subject'],
        'message' => $row['message'],
        'status' => $row['status'],
        'date' => date('M j, Y', strtotime($row['created_at'])),
        'time' => date('h:i a', strtotime($row['created_at'])),
        'student' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'email' => $row['email'] ?? ''
      );
    }
    $statusRes = 'success';
  }

  if ($fetch === 'ticket') {
    $code = mysqli_real_escape_string($conn, $_GET['code'] ?? '');
    if ($code !== '') {
      $sql = "SELECT st.id, st.code, st.subject, st.message, st.status, st.response, st.response_time, st.created_at, u.first_name, u.last_name, u.email, u.school FROM support_tickets st JOIN users u ON st.user_id = u.id WHERE st.code = '$code'";
      if ($admin_role == 5 && $admin_school > 0) {
        $sql .= " AND u.school = $admin_school";
      }
      $q = mysqli_query($conn, $sql);
      if ($row = mysqli_fetch_assoc($q)) {
        $ticket = array(
          'code' => $row['code'],
          'subject' => $row['subject'],
          'message' => $row['message'],
          'status' => $row['status'],
          'response' => $row['response'] ?? '',
          'date' => date('M j, Y', strtotime($row['created_at'])),
          'time' => date('h:i a', strtotime($row['created_at'])),
          'student' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
          'email' => $row['email'] ?? ''
        );
        $statusRes = 'success';
      } else {
        $statusRes = 'error';
        $messageRes = 'Ticket not found or unauthorized';
      }
    } else {
      $statusRes = 'error';
      $messageRes = 'Invalid ticket code';
    }
  }
}

if (isset($_POST['respond_ticket'])) {
  $code = mysqli_real_escape_string($conn, $_POST['code'] ?? '');
  $response = mysqli_real_escape_string($conn, $_POST['response'] ?? '');
  $markClosed = isset($_POST['mark_closed']) && $_POST['mark_closed'] == '1';
  if ($code === '' || $response === '') {
    $statusRes = 'error';
    $messageRes = 'Ticket code and response are required';
  } else {
    // Fetch ticket + user details for email and authorization
    $ticketInfoSql = "SELECT st.subject, u.first_name, u.email, u.school FROM support_tickets st JOIN users u ON u.id = st.user_id WHERE st.code = '$code'";
    if ($admin_role == 5 && $admin_school > 0) {
      $ticketInfoSql .= " AND u.school = $admin_school";
    }
    $ticketInfoQ = mysqli_query($conn, $ticketInfoSql);
    if ($row = mysqli_fetch_assoc($ticketInfoQ)) {
      $new_status = $markClosed ? 'closed' : 'open';
      $authJoin = ($admin_role == 5 && $admin_school > 0) ? "JOIN users u ON u.id = st.user_id" : '';
      $authWhere = ($admin_role == 5 && $admin_school > 0) ? " AND u.school = $admin_school" : '';
      $sql = "UPDATE support_tickets st $authJoin SET st.response = '$response', st.status = '$new_status', st.response_time = NOW() WHERE st.code = '$code' $authWhere";
      mysqli_query($conn, $sql);
      if (mysqli_affected_rows($conn) > 0) {
        // Send email notification to the user
        $userEmail = $row['email'];
        $firstName = trim($row['first_name'] ?? '');
        $ticketTitle = $row['subject'];
        $userSubject = "Re: Support Ticket (#$code) - $ticketTitle";
        $userBody = "Hi $firstName,<br><br>" . nl2br($response) . "<br><br>Best regards,<br>Support Team<br>Nivasity";
        $mailStatus = sendMail($userSubject, $userBody, $userEmail);

        $statusRes = 'success';
        $messageRes = ($mailStatus === 'success') ? 'Response sent and user notified by email' : 'Response saved, but email notification failed';
      } else {
        $statusRes = 'error';
        $messageRes = 'Update failed or unauthorized';
      }
    } else {
      $statusRes = 'error';
      $messageRes = 'Ticket not found or unauthorized';
    }
  }
}

if (isset($_POST['reopen_ticket'])) {
  $code = mysqli_real_escape_string($conn, $_POST['code'] ?? '');
  if ($code === '') {
    $statusRes = 'error';
    $messageRes = 'Invalid ticket code';
  } else {
    // Fetch ticket + user details for email and authorization
    $ticketInfoSql = "SELECT st.subject, u.first_name, u.email, u.school FROM support_tickets st JOIN users u ON u.id = st.user_id WHERE st.code = '$code'";
    if ($admin_role == 5 && $admin_school > 0) {
      $ticketInfoSql .= " AND u.school = $admin_school";
    }
    $ticketInfoQ = mysqli_query($conn, $ticketInfoSql);
    if ($row = mysqli_fetch_assoc($ticketInfoQ)) {
      $authJoin = ($admin_role == 5 && $admin_school > 0) ? "JOIN users u ON u.id = st.user_id" : '';
      $authWhere = ($admin_role == 5 && $admin_school > 0) ? " AND u.school = $admin_school" : '';
      $sql = "UPDATE support_tickets st $authJoin SET st.status = 'open' WHERE st.code = '$code' $authWhere";
      mysqli_query($conn, $sql);
      if (mysqli_affected_rows($conn) > 0) {
        // Notify user that the ticket has been reopened
        $userEmail = $row['email'];
        $firstName = trim($row['first_name'] ?? '');
        $ticketTitle = $row['subject'];
        $userSubject = "Re: Support Ticket (#$code) - $ticketTitle";
        $userBody = "Hi $firstName,<br><br>Your support ticket has been reopened. Our team will follow up shortly.<br><br>Best regards,<br>Support Team<br>Nivasity";
        $mailStatus = sendMail($userSubject, $userBody, $userEmail);

        $statusRes = 'success';
        $messageRes = ($mailStatus === 'success') ? 'Ticket reopened and user notified by email' : 'Ticket reopened; email notification failed';
      } else {
        $statusRes = 'error';
        $messageRes = 'Update failed or unauthorized';
      }
    } else {
      $statusRes = 'error';
      $messageRes = 'Ticket not found or unauthorized';
    }
  }
}

if (isset($_POST['support_id'])) {
  // Collect form data
  $subject = mysqli_real_escape_string($conn, $_POST['subject']);
  $message = mysqli_real_escape_string($conn, $_POST['message']);

  $user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
  $first_name = $user['first_name'];
  $last_name = $user['last_name'];
  $userEmail = $user['email'];

  // Generate a unique code
  $uniqueCode = generateVerificationCode(8);

  // Check if the code already exists, regenerate if needed
  while (!isCodeUnique($uniqueCode, $conn, 'support_tickets')) {
    $uniqueCode = generateVerificationCode(8);
  }

  $picture = 'NULL';
  if (!empty($_FILES['attachment']['name'])) {
    $picture = $_FILES['attachment']['name'];
    $tempname = $_FILES['attachment']['tmp_name'];
    $extension = pathinfo($picture, PATHINFO_EXTENSION);
    $picture = "support_$user_id" . "_$uniqueCode." . $extension;
    $destination = "../assets/images/supports/{$picture}";

    move_uploaded_file($tempname, $destination);
  }

  // Send email to support
  $supportEmail = 'support@nivasity.com';
  // Standardized subject format: Re: Support Ticket (#ID) - title
  $supportSubject = "Re: Support Ticket (#$uniqueCode) - $subject";
  $e_message = str_replace('\r\n', '<br>', $message);
  $supportMessage = "User: $first_name (User id: $user_id)<br>Email: <a href='mailto:$userEmail'>$userEmail</a><br>Message: <br>$e_message<br><br>File attached: <a href='https://nivasity.com/assets/images/supports/$picture'>https://nivasity.com/assets/images/supports/$picture</a>";

  // Send confirmation email to the user
  $userSubject = "Re: Support Ticket (#$uniqueCode) - $subject";
  // Body pattern: Hi Firstname, {{Message}} ...
  $userMessage = "Hi $first_name,<br><br>" . nl2br($message) . "<br><br>Best regards,<br>Support Team<br>Nivasity";

  $mailStatus = sendMail($supportSubject, $supportMessage, $supportEmail);

  // Check the status
  if ($mailStatus === "success") {
    $mailStatus2 = sendMail($userSubject, $userMessage, $userEmail);

    // Check the status 2
    if ($mailStatus2 === "success") {
      // Get current time in the desired format
      $currentDateTime = date("Y-m-d H:i:s");

      mysqli_query($conn, "INSERT INTO support_tickets (subject, code,	user_id,	message, created_at) 
        VALUES ('$subject', '$uniqueCode',	$user_id,	'$message', '$currentDateTime')");

      $statusRes = "success";
      $messageRes = "Request successfully sent!";
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    $statusRes = "error";
    $messageRes = "Couldn't send email. Please try again later!";
  }
}

if (isset($_POST['email_customer'])) {
  // Collect form data
  $email = mysqli_real_escape_string($conn, $_POST['cus_email']);
  $subject = mysqli_real_escape_string($conn, $_POST['subject']);
  $message = mysqli_real_escape_string($conn, $_POST['message']);

  $e_message = str_replace('\r\n', '<br>', $message);
  
  $mailStatus = sendMail($subject, $e_message, $email);

  // Check the status
  if ($mailStatus === "success") {
    $statusRes = "success";
    $messageRes = "Request successfully sent!";
  } else {
    $statusRes = "error";
    $messageRes = "Couldn't send email. Please try again later!";
  }
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'tickets' => $tickets,
  'ticket' => $ticket
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>
