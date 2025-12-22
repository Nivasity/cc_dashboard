<?php
session_start();
include('config.php');
include('mail.php');  // Includes sendMail() and sendMailBatch() functions that use BREVO REST API
include('functions.php');  // Includes checkBrevoCredits() for BREVO API credit validation
include('Parsedown.php');  // Includes Parsedown for markdown to HTML conversion

// Configuration constants
define('MAX_EMAIL_MESSAGE_LENGTH', 100000); // 100KB maximum message length

// Include Brevo API configuration if it exists
// This provides BREVO_API_KEY constant for API authentication
// BREVO (formerly Sendinblue) is the email service provider used for all email sending
if (file_exists('../config/brevo.php')) {
  include('../config/brevo.php');
}

$statusRes = 'failed';
$messageRes = '';
$tickets = null;
$ticket = null;
$messages = array();

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
    if (in_array((int)$admin_role, [4, 5], true)) {
      $statusRes = 'error';
      $messageRes = 'Unauthorized to view user support tickets.';
    } else {
      $status = mysqli_real_escape_string($conn, strtolower($_GET['status'] ?? 'open'));
      $sql = "SELECT st.id, st.code, st.subject, st.category, st.status, st.created_at, st.last_message_at, u.first_name, u.last_name, u.email, u.school 
              FROM support_tickets_v2 st 
              JOIN users u ON st.user_id = u.id 
              WHERE 1=1";
      if ($status === 'open' || $status === 'closed') {
        $sql .= " AND st.status = '$status'";
      }
      if ($admin_role == 5 && $admin_school > 0) {
        $sql .= " AND u.school = $admin_school";
      }
      $sql .= " ORDER BY COALESCE(st.last_message_at, st.created_at) DESC";
      $q = mysqli_query($conn, $sql);
      $tickets = array();
      while ($row = mysqli_fetch_assoc($q)) {
        $createdAt = $row['created_at'] ?? $row['last_message_at'];
        $tickets[] = array(
          'code' => $row['code'],
          'subject' => $row['subject'],
          'category' => $row['category'] ?? '',
          'status' => $row['status'],
          'date' => $createdAt ? date('M j, Y', strtotime($createdAt)) : '',
          'time' => $createdAt ? date('h:i a', strtotime($createdAt)) : '',
          'student' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
          'email' => $row['email'] ?? ''
        );
      }
      $statusRes = 'success';
    }
  }

  if ($fetch === 'ticket') {
    $code = mysqli_real_escape_string($conn, $_GET['code'] ?? '');
    if ($code !== '') {
      if (in_array((int)$admin_role, [4, 5], true)) {
        $statusRes = 'error';
        $messageRes = 'Unauthorized to view user support ticket.';
      } else {
      $sql = "SELECT st.id, st.code, st.subject, st.status, st.priority, st.category, st.created_at, st.last_message_at,
                     u.id AS user_id, u.first_name, u.last_name, u.email, u.school 
              FROM support_tickets_v2 st 
              JOIN users u ON st.user_id = u.id 
              WHERE st.code = '$code'";
      if ($admin_role == 5 && $admin_school > 0) {
        $sql .= " AND u.school = $admin_school";
      }
        $q = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($q)) {
        $ticket = array(
          'id' => (int) $row['id'],
          'code' => $row['code'],
          'subject' => $row['subject'],
          'status' => $row['status'],
          'priority' => $row['priority'],
          'category' => $row['category'],
          'date' => $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : '',
          'time' => $row['created_at'] ? date('h:i a', strtotime($row['created_at'])) : '',
          'student' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
          'email' => $row['email'] ?? ''
        );

        $ticketId = (int) $row['id'];
        $messages = array();
        $msgSql = "SELECT m.id, m.ticket_id, m.sender_type, m.user_id, m.admin_id, m.body, m.is_internal, m.created_at,
                          u.first_name AS user_first_name, u.last_name AS user_last_name,
                          a.first_name AS admin_first_name, a.last_name AS admin_last_name
                   FROM support_ticket_messages m
                   LEFT JOIN users u ON m.user_id = u.id
                   LEFT JOIN admins a ON m.admin_id = a.id
                   WHERE m.ticket_id = $ticketId
                   ORDER BY m.created_at ASC, m.id ASC";
        $msgQ = mysqli_query($conn, $msgSql);
        while ($mrow = mysqli_fetch_assoc($msgQ)) {
          $mid = (int) $mrow['id'];
          $attachments = array();
          $attSql = "SELECT id, file_path, file_name, mime_type, file_size, created_at 
                     FROM support_ticket_attachments 
                     WHERE message_id = $mid";
          $attQ = mysqli_query($conn, $attSql);
          while ($arow = mysqli_fetch_assoc($attQ)) {
            $attachments[] = array(
              'id' => (int) $arow['id'],
              'file_path' => $arow['file_path'],
              'file_name' => $arow['file_name'],
              'mime_type' => $arow['mime_type'],
              'file_size' => isset($arow['file_size']) ? (int) $arow['file_size'] : null
            );
          }

          $messages[] = array(
            'id' => $mid,
            'sender_type' => $mrow['sender_type'],
            'body' => $mrow['body'],
            'is_internal' => $mrow['is_internal'],
            'created_at' => $mrow['created_at'],
            'created_at_formatted' => $mrow['created_at'] ? date('M j, Y h:i a', strtotime($mrow['created_at'])) : '',
            'user_name' => trim(($mrow['user_first_name'] ?? '') . ' ' . ($mrow['user_last_name'] ?? '')),
            'admin_name' => trim(($mrow['admin_first_name'] ?? '') . ' ' . ($mrow['admin_last_name'] ?? '')),
            'attachments' => $attachments
          );
        }

          $statusRes = 'success';
        } else {
          $statusRes = 'error';
          $messageRes = 'Ticket not found or unauthorized';
        }
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
    $ticketInfoSql = "SELECT st.id, st.subject, u.id AS user_id, u.first_name, u.email, u.school 
                      FROM support_tickets_v2 st 
                      JOIN users u ON u.id = st.user_id 
                      WHERE st.code = '$code'";
    if ($admin_role == 5 && $admin_school > 0) {
      $ticketInfoSql .= " AND u.school = $admin_school";
    }
    $ticketInfoQ = mysqli_query($conn, $ticketInfoSql);
    if ($row = mysqli_fetch_assoc($ticketInfoQ)) {
      $ticketId = (int) $row['id'];
      $userId = (int) $row['user_id'];
      $now = date("Y-m-d H:i:s");

      // Insert admin message into trail
      $adminIdVal = $admin_id ? (int) $admin_id : 0;
      mysqli_query(
        $conn,
        "INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at) 
         VALUES ($ticketId, 'admin', NULL, " . ($adminIdVal ?: "NULL") . ", '$response', 0, '$now')"
      );
      $messageId = mysqli_insert_id($conn);

      // Handle file attachments for this response
      if ($messageId && isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $uploadDir = "../assets/images/supports/";
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0777, true);
        }
        $names = $_FILES['attachments']['name'];
        $tmpNames = $_FILES['attachments']['tmp_name'];
        $types = $_FILES['attachments']['type'];
        $sizes = $_FILES['attachments']['size'];
        $errors = $_FILES['attachments']['error'];
        foreach ($names as $idx => $origName) {
          if (empty($origName)) {
            continue;
          }
          if (isset($errors[$idx]) && $errors[$idx] !== UPLOAD_ERR_OK) {
            continue;
          }
          $tmpName = $tmpNames[$idx];
          $extension = pathinfo($origName, PATHINFO_EXTENSION);
          $safeExt = $extension !== '' ? preg_replace('/[^a-zA-Z0-9]/', '', $extension) : '';
          $storedName = "support_reply_{$ticketId}_{$messageId}_" . ($idx + 1);
          if ($safeExt !== '') {
            $storedName .= "." . $safeExt;
          }
          $destPath = $uploadDir . $storedName;
          if (move_uploaded_file($tmpName, $destPath)) {
            $relativePath = "assets/images/supports/$storedName";
            $fileSize = isset($sizes[$idx]) ? (int) $sizes[$idx] : 0;
            $mimeType = $types[$idx] ?? '';
            mysqli_query(
              $conn,
              "INSERT INTO support_ticket_attachments (message_id, file_path, file_name, mime_type, file_size) 
               VALUES ($messageId, '" . mysqli_real_escape_string($conn, $relativePath) . "', '" . mysqli_real_escape_string($conn, $origName) . "', '" . mysqli_real_escape_string($conn, $mimeType) . "', $fileSize)"
            );
          }
        }
      }

      $new_status = $markClosed ? 'closed' : 'open';
      $authJoin = ($admin_role == 5 && $admin_school > 0) ? "JOIN users u ON u.id = st.user_id" : '';
      $authWhere = ($admin_role == 5 && $admin_school > 0) ? " AND u.school = $admin_school" : '';

      $updateFields = array(
        "st.status = '$new_status'",
        "st.last_message_at = '$now'"
      );
      if (!empty($admin_id)) {
        $updateFields[] = "st.assigned_admin_id = $admin_id";
      }
      if ($new_status === 'closed') {
        $updateFields[] = "st.closed_at = '$now'";
      } else {
        $updateFields[] = "st.closed_at = NULL";
      }

      $sql = "UPDATE support_tickets_v2 st $authJoin SET " . implode(', ', $updateFields) . " WHERE st.code = '$code' $authWhere";
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
        if (!empty($admin_id)) {
          $ticket_id = isset($row['id']) ? (int) $row['id'] : 0;
          log_audit_event($conn, $admin_id, 'respond', 'support_ticket', $ticket_id ?: null, [
            'ticket_code' => $code,
            'new_status' => $new_status,
            'closed' => $markClosed,
            'mail_status' => $mailStatus
          ]);
        }
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
    $ticketInfoSql = "SELECT st.id, st.subject, u.first_name, u.email, u.school 
                      FROM support_tickets_v2 st 
                      JOIN users u ON u.id = st.user_id 
                      WHERE st.code = '$code'";
    if ($admin_role == 5 && $admin_school > 0) {
      $ticketInfoSql .= " AND u.school = $admin_school";
    }
    $ticketInfoQ = mysqli_query($conn, $ticketInfoSql);
    if ($row = mysqli_fetch_assoc($ticketInfoQ)) {
      $authJoin = ($admin_role == 5 && $admin_school > 0) ? "JOIN users u ON u.id = st.user_id" : '';
      $authWhere = ($admin_role == 5 && $admin_school > 0) ? " AND u.school = $admin_school" : '';
      $sql = "UPDATE support_tickets_v2 st $authJoin SET st.status = 'open', st.closed_at = NULL WHERE st.code = '$code' $authWhere";
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
        if (!empty($admin_id)) {
          $ticket_id = isset($row['id']) ? (int) $row['id'] : 0;
          log_audit_event($conn, $admin_id, 'reopen', 'support_ticket', $ticket_id ?: null, [
            'ticket_code' => $code,
            'mail_status' => $mailStatus
          ]);
        }
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
  $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');

  $user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
  $first_name = $user['first_name'];
  $last_name = $user['last_name'];
  $userEmail = $user['email'];

  // Generate a unique code
  $uniqueCode = generateVerificationCode(8);

  // Check if the code already exists, regenerate if needed
  while (!isCodeUnique($uniqueCode, $conn, 'support_tickets_v2')) {
    $uniqueCode = generateVerificationCode(8);
  }

  $picture = 'NULL';
  if (!empty($_FILES['attachment']['name'])) {
    $picture = $_FILES['attachment']['name'];
    $tempname = $_FILES['attachment']['tmp_name'];
    $extension = pathinfo($picture, PATHINFO_EXTENSION);
    $picture = "support_$user_id" . "_$uniqueCode." . $extension;
    $uploadDir = "../assets/images/supports/";
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }
    $destination = $uploadDir . $picture;

    move_uploaded_file($tempname, $destination);
  }

  // Send email to support
  $supportEmail = 'support@nivasity.com';
  // Standardized subject format: Re: Support Ticket (#ID) - title
  $supportSubject = "Re: Support Ticket (#$uniqueCode) - $subject";
  $e_message = str_replace('\r\n', '<br>', $message);
  $supportMessage = "User: $first_name (User id: $user_id)<br>Email: <a href='mailto:$userEmail'>$userEmail</a><br>Message: <br>$e_message";
  if ($picture !== 'NULL') {
    $supportMessage .= "<br><br>File attached: <a href='https://nivasity.com/assets/images/supports/$picture'>https://nivasity.com/assets/images/supports/$picture</a>";
  }

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

      // Create ticket in v2 table
      $categorySql = $category !== '' ? "'$category'" : "NULL";
      mysqli_query($conn, "INSERT INTO support_tickets_v2 (code, subject, user_id, category, last_message_at, created_at) 
        VALUES ('$uniqueCode', '$subject', $user_id, $categorySql, '$currentDateTime', '$currentDateTime')");
      $ticketId = mysqli_insert_id($conn);

      if ($ticketId) {
        // First user message
        mysqli_query($conn, "INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, body, is_internal, created_at) 
          VALUES ($ticketId, 'user', $user_id, '$message', 0, '$currentDateTime')");
        $messageId = mysqli_insert_id($conn);

        // Store attachment metadata
        if ($messageId && $picture !== 'NULL') {
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
}

if (isset($_POST['get_student_count'])) {
  $recipient_type = mysqli_real_escape_string($conn, $_POST['recipient_type']);
  $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
  $faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;
  $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
  
  $count = 0;
  
  if ($recipient_type === 'all_students') {
    $query = "SELECT COUNT(*) AS count FROM users WHERE role = 'student' AND status = 'verified'";
    if ($admin_role == 5 && $admin_school > 0) {
      $admin_school_safe = intval($admin_school);
      $query .= " AND school = $admin_school_safe";
    }
    $result = mysqli_query($conn, $query);
    $count = mysqli_fetch_assoc($result)['count'];
  } elseif ($recipient_type === 'all_hoc') {
    $query = "SELECT COUNT(*) AS count FROM users WHERE role = 'hoc' AND status = 'verified'";
    if ($admin_role == 5 && $admin_school > 0) {
      $admin_school_safe = intval($admin_school);
      $query .= " AND school = $admin_school_safe";
    }
    $result = mysqli_query($conn, $query);
    $count = mysqli_fetch_assoc($result)['count'];
  } elseif ($recipient_type === 'all_students_hoc') {
    $query = "SELECT COUNT(*) AS count FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified'";
    if ($admin_role == 5 && $admin_school > 0) {
      $admin_school_safe = intval($admin_school);
      $query .= " AND school = $admin_school_safe";
    }
    $result = mysqli_query($conn, $query);
    $count = mysqli_fetch_assoc($result)['count'];
  } elseif ($recipient_type === 'school' && $school_id > 0) {
    // Check if school admin is trying to access a different school
    if ($admin_role == 5 && $admin_school > 0 && $school_id != $admin_school) {
      $count = 0;
    } else {
      $school_id_safe = intval($school_id);
      $query = "SELECT COUNT(*) AS count FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND school = $school_id_safe";
      $result = mysqli_query($conn, $query);
      $count = mysqli_fetch_assoc($result)['count'];
    }
  } elseif ($recipient_type === 'faculty' && $faculty_id > 0) {
    // Check if faculty belongs to admin's school
    if ($admin_role == 5 && $admin_school > 0) {
      $faculty_check = mysqli_query($conn, "SELECT id FROM faculties WHERE id = $faculty_id AND school_id = " . intval($admin_school) . " AND status = 'active'");
      if (mysqli_num_rows($faculty_check) == 0) {
        $count = 0;
      } else {
        $faculty_id_safe = intval($faculty_id);
        $query = "SELECT COUNT(*) AS count FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND dept IN (SELECT id FROM depts WHERE faculty_id = $faculty_id_safe)";
        $result = mysqli_query($conn, $query);
        $count = mysqli_fetch_assoc($result)['count'];
      }
    } else {
      $faculty_id_safe = intval($faculty_id);
      $query = "SELECT COUNT(*) AS count FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND dept IN (SELECT id FROM depts WHERE faculty_id = $faculty_id_safe)";
      $result = mysqli_query($conn, $query);
      $count = mysqli_fetch_assoc($result)['count'];
    }
  } elseif ($recipient_type === 'dept' && $dept_id > 0) {
    // Check if dept belongs to admin's school
    if ($admin_role == 5 && $admin_school > 0) {
      $dept_check = mysqli_query($conn, "SELECT id FROM depts WHERE id = $dept_id AND school_id = " . intval($admin_school) . " AND status = 'active'");
      if (mysqli_num_rows($dept_check) == 0) {
        $count = 0;
      } else {
        $dept_id_safe = intval($dept_id);
        $query = "SELECT COUNT(*) AS count FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND dept = $dept_id_safe";
        $result = mysqli_query($conn, $query);
        $count = mysqli_fetch_assoc($result)['count'];
      }
    } else {
      $dept_id_safe = intval($dept_id);
      $query = "SELECT COUNT(*) AS count FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND dept = $dept_id_safe";
      $result = mysqli_query($conn, $query);
      $count = mysqli_fetch_assoc($result)['count'];
    }
  }
  
  $responseData = array(
    'status' => 'success',
    'count' => $count
  );
  
  header('Content-Type: application/json');
  echo json_encode($responseData);
  exit;
}

if (isset($_POST['email_customer'])) {
  // Collect form data
  $recipient_type = mysqli_real_escape_string($conn, $_POST['recipient_type'] ?? 'single');
  $subject = mysqli_real_escape_string($conn, $_POST['subject']);
  $message = $_POST['message']; // Don't escape - will be converted to HTML via markdown
  
  // Basic validation: limit message length to prevent abuse
  if (strlen($message) > MAX_EMAIL_MESSAGE_LENGTH) {
    $statusRes = "error";
    $messageRes = "Message is too long. Please reduce the message size.";
  } else {
    // Convert markdown to HTML
    // Using Parsedown with safe mode to prevent XSS attacks
    // Safe mode escapes HTML tags and sanitizes URLs
    // Enable line breaks so single newlines are preserved (user-friendly for emails)
    $parsedown = Parsedown::instance();
    $parsedown->setSafeMode(true);
    $parsedown->setBreaksEnabled(true); // Preserve line breaks without requiring 2 spaces
    $e_message = $parsedown->text($message);
  
  // Get list of recipients based on type
  $recipients = array();
  
  if ($recipient_type === 'single') {
    $email = mysqli_real_escape_string($conn, $_POST['cus_email']);
    $recipients[] = $email;
  } elseif ($recipient_type === 'all_students') {
    $query = "SELECT email FROM users WHERE role = 'student' AND status = 'verified'";
    if ($admin_role == 5 && $admin_school > 0) {
      $admin_school_safe = intval($admin_school);
      $query .= " AND school = $admin_school_safe";
    }
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
      $recipients[] = $row['email'];
    }
  } elseif ($recipient_type === 'all_hoc') {
    $query = "SELECT email FROM users WHERE role = 'hoc' AND status = 'verified'";
    if ($admin_role == 5 && $admin_school > 0) {
      $admin_school_safe = intval($admin_school);
      $query .= " AND school = $admin_school_safe";
    }
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
      $recipients[] = $row['email'];
    }
  } elseif ($recipient_type === 'all_students_hoc') {
    $query = "SELECT email FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified'";
    if ($admin_role == 5 && $admin_school > 0) {
      $admin_school_safe = intval($admin_school);
      $query .= " AND school = $admin_school_safe";
    }
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
      $recipients[] = $row['email'];
    }
  } elseif ($recipient_type === 'school') {
    $school_id = intval($_POST['email_school']);
    // Check if school admin is trying to access a different school
    if ($admin_role == 5 && $admin_school > 0 && $school_id != $admin_school) {
      $statusRes = "error";
      $messageRes = "You can only send emails to students in your own school!";
    } else {
      $school_id_safe = intval($school_id);
      $query = "SELECT email FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND school = $school_id_safe";
      $result = mysqli_query($conn, $query);
      while ($row = mysqli_fetch_assoc($result)) {
        $recipients[] = $row['email'];
      }
    }
  } elseif ($recipient_type === 'faculty') {
    $faculty_id = intval($_POST['email_faculty']);
    // Check if faculty belongs to admin's school
    if ($admin_role == 5 && $admin_school > 0) {
      $faculty_check = mysqli_query($conn, "SELECT id FROM faculties WHERE id = $faculty_id AND school_id = " . intval($admin_school) . " AND status = 'active'");
      if (mysqli_num_rows($faculty_check) == 0) {
        $statusRes = "error";
        $messageRes = "You can only send emails to students in your own school!";
      }
    }
    if ($statusRes != 'error') {
      $faculty_id_safe = intval($faculty_id);
      $query = "SELECT email FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND dept IN (SELECT id FROM depts WHERE faculty_id = $faculty_id_safe)";
      $result = mysqli_query($conn, $query);
      while ($row = mysqli_fetch_assoc($result)) {
        $recipients[] = $row['email'];
      }
    }
  } elseif ($recipient_type === 'dept') {
    $dept_id = intval($_POST['email_dept']);
    // Check if dept belongs to admin's school
    if ($admin_role == 5 && $admin_school > 0) {
      $dept_check = mysqli_query($conn, "SELECT id FROM depts WHERE id = $dept_id AND school_id = " . intval($admin_school) . " AND status = 'active'");
      if (mysqli_num_rows($dept_check) == 0) {
        $statusRes = "error";
        $messageRes = "You can only send emails to students in your own school!";
      }
    }
    if ($statusRes != 'error') {
      $dept_id_safe = intval($dept_id);
      $query = "SELECT email FROM users WHERE (role = 'student' OR role = 'hoc') AND status = 'verified' AND dept = $dept_id_safe";
      $result = mysqli_query($conn, $query);
      while ($row = mysqli_fetch_assoc($result)) {
        $recipients[] = $row['email'];
      }
    }
  }
  
  // Check if we have recipients
  if (empty($recipients)) {
    $statusRes = "error";
    $messageRes = "No recipients found for the selected criteria!";
  } else {
    $recipientCount = count($recipients);
    
    // BREVO Email System: Check Brevo API credits before sending bulk emails
    // This prevents sending emails when the BREVO account has insufficient credits
    // BREVO (formerly Sendinblue) is our email service provider
    if ($recipientCount > 1) {
      // Get Brevo API key from config/brevo.php
      $brevoApiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
      
      if (empty($brevoApiKey)) {
        $statusRes = "error";
        $messageRes = "Brevo API key not configured. Please contact administrator.";
      } else {
        // Check credits using BREVO API v3 (/v3/account endpoint)
        // Ensures we have enough credits (required + 1500 buffer) before sending
        $creditCheck = checkBrevoCredits($brevoApiKey, $recipientCount);
        
        if (!$creditCheck['success']) {
          $statusRes = "error";
          $messageRes = $creditCheck['message'];
        } else {
          // Proceed with sending emails via BREVO REST API
          // Use batch sending for efficiency (max 1000 emails per API call)
          $result = sendMailBatch($subject, $e_message, $recipients);
          $successCount = $result['success_count'];
          $failCount = $result['fail_count'];
          
          if ($successCount > 0) {
            $statusRes = "success";
            $messageRes = "Email sent successfully to $successCount recipient(s)";
            if ($failCount > 0) {
              $messageRes .= ". Failed to send to $failCount recipient(s).";
            }
            if (!empty($admin_id)) {
              log_audit_event($conn, $admin_id, 'bulk_email_customer', 'support_ticket', null, [
                'recipient_type' => $recipient_type,
                'subject' => $subject,
                'recipient_count' => $recipientCount,
                'success_count' => $successCount,
                'fail_count' => $failCount
              ]);
            }
          } else {
            $statusRes = "error";
            $messageRes = "Failed to send emails. Please try again later!";
          }
        }
      }
    } else {
      // Single email - no BREVO credit check needed (minimal impact on credits)
      // Uses BREVO REST API for sending via sendMail() function
      $mailStatus = sendMail($subject, $e_message, $recipients[0]);
      
      if ($mailStatus === "success") {
        $statusRes = "success";
        $messageRes = "Email sent successfully!";
        if (!empty($admin_id)) {
          log_audit_event($conn, $admin_id, 'email_customer', 'support_ticket', null, [
            'email' => $recipients[0],
            'subject' => $subject
          ]);
        }
      } else {
        $statusRes = "error";
        $messageRes = "Couldn't send email. Please try again later!";
      }
    }
  }
  } // End of message validation and email processing
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'tickets' => $tickets,
  'ticket' => $ticket,
  'messages' => $messages
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>
