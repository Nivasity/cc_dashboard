<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_userId'];
$role = $_SESSION['nivas_userRole'];

// Collect form data
$new_institution = $_POST['new_institution'];
$new_adm_year = $_POST['new_adm_year'];
$new_department = $_POST['new_department'];
$new_matric_no = $_POST['new_matric_no'];
$message = "School: $new_institution \n\nAdmission year: $new_adm_year \n\nDepartment: $new_department \n\nMatric number: $new_matric_no \n\n" . $_POST['message'];

$user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
$first_name = $user['first_name'];
$last_name = $user['last_name'];
$userEmail = $user['email'];

// Generate a unique ticket code for v2
$uniqueCode = generateVerificationCode(8);
while (!isCodeUnique($uniqueCode, $conn, 'support_tickets_v2')) {
  $uniqueCode = generateVerificationCode(8);
}

$picture = $_FILES['attachment']['name'];
$tempname = $_FILES['attachment']['tmp_name'];
$extension = pathinfo($picture, PATHINFO_EXTENSION);
$picture = "support_$user_id" . "_$uniqueCode." . $extension;
$uploadDir = "../assets/images/supports/";
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}
$destination = $uploadDir . $picture;

if (move_uploaded_file($tempname, $destination)) {
  // Send email to support
  $supportEmail = 'support@nivasity.com';
  $ticketTitle = 'Academic Info Change Request';
  $subject = "Re: Support Ticket (#$uniqueCode) - $ticketTitle";
  $supportMessage = "User: $first_name (User id: $user_id)<br>Email: $userEmail<br>Message: $message";

  // Send confirmation email to the user
  $userSubject = "Re: Support Ticket (#$uniqueCode) - $ticketTitle";
  $userMessage = "Hi $first_name,<br><br>" . nl2br($message) . "<br><br>Best regards,<br>Support Team<br>Nivasity";

  $mailStatus = sendMail($subject, $supportMessage, $supportEmail);

  // Check the status
  if ($mailStatus === "success") {
    $mailStatus2 = sendMail($userSubject, $userMessage, $userEmail);

    // Check the status 2
    if ($mailStatus2 === "success") {
      // Get current time in the desired format
      $currentDateTime = date("Y-m-d H:i:s");

      // Create ticket in v2 table
      mysqli_query($conn, "INSERT INTO support_tickets_v2 (code, subject, user_id, last_message_at, created_at) 
        VALUES ('$uniqueCode', '$ticketTitle', $user_id, '$currentDateTime', '$currentDateTime')");
      $ticketId = mysqli_insert_id($conn);

      if ($ticketId) {
        // First user message
        mysqli_query($conn, "INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, body, is_internal, created_at) 
          VALUES ($ticketId, 'user', $user_id, '" . mysqli_real_escape_string($conn, $message) . "', 0, '$currentDateTime')");
        $messageId = mysqli_insert_id($conn);

        // Store attachment metadata
        if ($messageId && !empty($picture)) {
          $relativePath = "assets/images/supports/$picture";
          $origName = mysqli_real_escape_string($conn, $_FILES['attachment']['name']);
          $mimeType = mysqli_real_escape_string($conn, $_FILES['attachment']['type'] ?? '');
          $fileSize = isset($_FILES['attachment']['size']) ? (int) $_FILES['attachment']['size'] : 0;
          mysqli_query($conn, "INSERT INTO support_ticket_attachments (message_id, file_path, file_name, mime_type, file_size) 
            VALUES ($messageId, '" . mysqli_real_escape_string($conn, $relativePath) . "', '$origName', '$mimeType', $fileSize)");
        }
      }

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


} else {
  $statusRes = "error";
  $messageRes = "Couldn't upload file. Please try again later!";
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
