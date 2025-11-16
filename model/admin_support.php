<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = '';
$tickets = null;
$ticket = null;
$messages = array();

$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_role = $_SESSION['nivas_adminRole'] ?? null;

if (!$admin_id) {
  $responseData = array(
    'status' => 'error',
    'message' => 'Unauthorized',
    'tickets' => null,
    'ticket' => null,
    'messages' => array()
  );
  header('Content-Type: application/json');
  echo json_encode($responseData);
  exit;
}

// Fetch lists
if (isset($_GET['fetch'])) {
  $fetch = $_GET['fetch'];

  if ($fetch === 'tickets') {
    $status = mysqli_real_escape_string($conn, strtolower($_GET['status'] ?? 'open'));
    $assignment = mysqli_real_escape_string($conn, strtolower($_GET['assignment'] ?? 'all'));

    $sql = "SELECT t.id, t.code, t.subject, t.status, t.priority, t.category,
                   t.created_at, t.last_message_at,
                   ca.first_name AS created_first_name, ca.last_name AS created_last_name,
                   aa.first_name AS assigned_first_name, aa.last_name AS assigned_last_name
            FROM admin_support_tickets t
            LEFT JOIN admins ca ON t.created_by_admin_id = ca.id
            LEFT JOIN admins aa ON t.assigned_admin_id = aa.id
            WHERE 1=1";

    if ($status === 'open' || $status === 'closed' || $status === 'pending' || $status === 'resolved') {
      $sql .= " AND t.status = '$status'";
    }

    // Restrict to tickets created by this admin, assigned to this admin, or to this admin's role
    $aid = (int)$admin_id;
    $roleId = (int)$admin_role;
    $scope = "(t.created_by_admin_id = $aid OR t.assigned_admin_id = $aid OR t.assigned_role_id = $roleId)";
    $sql .= " AND $scope";

    $sql .= " ORDER BY COALESCE(t.last_message_at, t.created_at) DESC";
    $q = mysqli_query($conn, $sql);
    $tickets = array();
    while ($row = mysqli_fetch_assoc($q)) {
      $createdAt = $row['created_at'] ?? $row['last_message_at'];
      $tickets[] = array(
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'subject' => $row['subject'],
        'status' => $row['status'],
        'priority' => $row['priority'],
        'category' => $row['category'],
        'date' => $createdAt ? date('M j, Y', strtotime($createdAt)) : '',
        'time' => $createdAt ? date('h:i a', strtotime($createdAt)) : '',
        'created_by' => trim(($row['created_first_name'] ?? '') . ' ' . ($row['created_last_name'] ?? '')),
        'assigned_to' => trim(($row['assigned_first_name'] ?? '') . ' ' . ($row['assigned_last_name'] ?? ''))
      );
    }
    $statusRes = 'success';
  }

  if ($fetch === 'ticket') {
    $code = mysqli_real_escape_string($conn, $_GET['code'] ?? '');
    if ($code !== '') {
      $sql = "SELECT t.id, t.code, t.subject, t.status, t.priority, t.category,
                     t.created_at, t.last_message_at, t.related_ticket_id,
                     ca.id AS created_by_admin_id,
                     ca.first_name AS created_first_name, ca.last_name AS created_last_name, ca.email AS created_email,
                     aa.id AS assigned_admin_id,
                     aa.first_name AS assigned_first_name, aa.last_name AS assigned_last_name, aa.email AS assigned_email
              FROM admin_support_tickets t
              LEFT JOIN admins ca ON t.created_by_admin_id = ca.id
              LEFT JOIN admins aa ON t.assigned_admin_id = aa.id
              WHERE t.code = '$code'";
      $q = mysqli_query($conn, $sql);
      if ($row = mysqli_fetch_assoc($q)) {
        $aid = (int)$admin_id;
        $roleId = (int)$admin_role;
        if (!(
          (int)$row['created_by_admin_id'] === $aid ||
          (int)$row['assigned_admin_id'] === $aid ||
          (int)$row['assigned_role_id'] === $roleId
        )) {
          $statusRes = 'error';
          $messageRes = 'Unauthorized to view this internal ticket.';
        } else {
        $ticket = array(
          'id' => (int) $row['id'],
          'code' => $row['code'],
          'subject' => $row['subject'],
          'status' => $row['status'],
          'priority' => $row['priority'],
          'category' => $row['category'],
          'date' => $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : '',
          'time' => $row['created_at'] ? date('h:i a', strtotime($row['created_at'])) : '',
          'created_by' => trim(($row['created_first_name'] ?? '') . ' ' . ($row['created_last_name'] ?? '')),
          'created_email' => $row['created_email'] ?? '',
          'assigned_to' => trim(($row['assigned_first_name'] ?? '') . ' ' . ($row['assigned_last_name'] ?? '')),
          'assigned_email' => $row['assigned_email'] ?? '',
          'related_ticket_id' => $row['related_ticket_id'] !== null ? (int) $row['related_ticket_id'] : null
        );

        $ticketId = (int) $row['id'];
        $messages = array();
        $msgSql = "SELECT m.id, m.ticket_id, m.sender_type, m.user_id, m.admin_id, m.body, m.is_internal, m.created_at,
                          a.first_name AS admin_first_name, a.last_name AS admin_last_name
                   FROM admin_support_ticket_messages m
                   LEFT JOIN admins a ON m.admin_id = a.id
                   WHERE m.ticket_id = $ticketId
                   ORDER BY m.created_at ASC, m.id ASC";
        $msgQ = mysqli_query($conn, $msgSql);
        while ($mrow = mysqli_fetch_assoc($msgQ)) {
          $mid = (int) $mrow['id'];
          $attachments = array();
          $attSql = "SELECT id, file_path, file_name, mime_type, file_size, created_at
                     FROM admin_support_ticket_attachments
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
            'admin_id' => $mrow['admin_id'] !== null ? (int)$mrow['admin_id'] : null,
            'body' => $mrow['body'],
            'is_internal' => $mrow['is_internal'],
            'created_at' => $mrow['created_at'],
            'created_at_formatted' => $mrow['created_at'] ? date('M j, Y h:i a', strtotime($mrow['created_at'])) : '',
            'admin_name' => trim(($mrow['admin_first_name'] ?? '') . ' ' . ($mrow['admin_last_name'] ?? '')),
            'attachments' => $attachments
          );
        }

        $statusRes = 'success';
        }
      } else {
        $statusRes = 'error';
        $messageRes = 'Ticket not found';
      }
    } else {
      $statusRes = 'error';
      $messageRes = 'Invalid ticket code';
    }
  }
}

// Create new admin ticket
if (isset($_POST['create_ticket'])) {
  $subject = mysqli_real_escape_string($conn, $_POST['subject'] ?? '');
  $body = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
  $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'medium');
  $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
  $assigned_admin_id = isset($_POST['assigned_admin_id']) && $_POST['assigned_admin_id'] !== '' ? (int) $_POST['assigned_admin_id'] : null;
  $assigned_role_id = isset($_POST['assigned_role_id']) && $_POST['assigned_role_id'] !== '' ? (int) $_POST['assigned_role_id'] : null;
  $related_ticket_code = mysqli_real_escape_string($conn, $_POST['related_ticket_code'] ?? '');

  if ($subject === '' || $body === '') {
    $statusRes = 'error';
    $messageRes = 'Subject and message are required';
  } else {
    $related_ticket_id = null;
    if ($related_ticket_code !== '') {
      $rtRes = mysqli_query($conn, "SELECT id FROM support_tickets_v2 WHERE code = '$related_ticket_code' LIMIT 1");
      if ($rtRow = mysqli_fetch_assoc($rtRes)) {
        $related_ticket_id = (int) $rtRow['id'];
      }
    }

    // Generate unique code for admin tickets
    $uniqueCode = generateVerificationCode(8);
    while (!isCodeUnique($uniqueCode, $conn, 'admin_support_tickets')) {
      $uniqueCode = generateVerificationCode(8);
    }

    $now = date("Y-m-d H:i:s");
    $createdBy = (int) $admin_id;
    $assignedAdminSql = $assigned_admin_id !== null ? (int) $assigned_admin_id : 'NULL';
    $assignedRoleSql = $assigned_role_id !== null ? (int) $assigned_role_id : 'NULL';
    $relatedTicketSql = $related_ticket_id !== null ? (int) $related_ticket_id : 'NULL';

    mysqli_query(
      $conn,
      "INSERT INTO admin_support_tickets
        (code, subject, created_by_admin_id, status, priority, category,
         assigned_admin_id, assigned_role_id, related_ticket_id,
         last_message_at, created_at)
       VALUES
        ('$uniqueCode', '$subject', $createdBy, 'open', '$priority', " .
        ($category !== '' ? "'$category'" : "NULL") . ",
         $assignedAdminSql, $assignedRoleSql, $relatedTicketSql,
         '$now', '$now')"
    );

    if (mysqli_affected_rows($conn) > 0) {
      $ticketId = mysqli_insert_id($conn);

      // First message
      mysqli_query(
        $conn,
        "INSERT INTO admin_support_ticket_messages
          (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at)
         VALUES
          ($ticketId, 'admin', NULL, $createdBy, '$body', 0, '$now')"
      );
      $messageId = mysqli_insert_id($conn);

      // Attachments (optional)
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
          $storedName = "admin_support_{$ticketId}_{$messageId}_" . ($idx + 1);
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
              "INSERT INTO admin_support_ticket_attachments
                 (message_id, file_path, file_name, mime_type, file_size)
               VALUES
                 ($messageId, '" . mysqli_real_escape_string($conn, $relativePath) . "', '" . mysqli_real_escape_string($conn, $origName) . "', '" . mysqli_real_escape_string($conn, $mimeType) . "', $fileSize)"
            );
          }
        }
      }

      // Email notifications
      $subjectLine = "New Internal Ticket (#$uniqueCode) - $subject";
      $bodyHtml = nl2br($body);

      // Assigned admin
      if ($assigned_admin_id) {
        $aRes = mysqli_query($conn, "SELECT email, first_name FROM admins WHERE id = $assigned_admin_id LIMIT 1");
        if ($aRow = mysqli_fetch_assoc($aRes)) {
          $toEmail = $aRow['email'];
          $firstName = trim($aRow['first_name'] ?? '');
          $mailBody = "Hi $firstName,<br><br>"
            . "You have been assigned a new internal support ticket.<br><br>"
            . "<strong>Code:</strong> #$uniqueCode<br>"
            . "<strong>Subject:</strong> $subject<br><br>"
            . "<strong>Message:</strong><br>$bodyHtml<br><br>"
            . "This ticket is internal to the Command Center and may be linked to a user support ticket for context.";
          sendMail($subjectLine, $mailBody, $toEmail);
        }
      }

      // Assigned role group (email all admins in that role)
      if ($assigned_role_id) {
        $rId = (int) $assigned_role_id;
        $rAdmins = mysqli_query($conn, "SELECT email, first_name FROM admins WHERE role = $rId");
        while ($ra = mysqli_fetch_assoc($rAdmins)) {
          $toEmail = $ra['email'];
          $firstName = trim($ra['first_name'] ?? '');
          if ($toEmail === '') continue;
          $mailBody = "Hi $firstName,<br><br>"
            . "A new internal support ticket has been opened for your role group.<br><br>"
            . "<strong>Code:</strong> #$uniqueCode<br>"
            . "<strong>Subject:</strong> $subject<br><br>"
            . "<strong>Message:</strong><br>$bodyHtml<br><br>"
            . "This ticket is internal to the Command Center and may be linked to a user support ticket for context.";
          sendMail($subjectLine, $mailBody, $toEmail);
        }
      }

      $statusRes = 'success';
      $messageRes = 'Internal ticket created successfully';
    } else {
      $statusRes = 'error';
      $messageRes = 'Failed to create ticket';
    }
  }
}

// Respond to admin ticket
if (isset($_POST['respond_ticket'])) {
  $code = mysqli_real_escape_string($conn, $_POST['code'] ?? '');
  $response = mysqli_real_escape_string($conn, $_POST['response'] ?? '');
  $markClosed = isset($_POST['mark_closed']) && $_POST['mark_closed'] == '1';

  if ($code === '' || $response === '') {
    $statusRes = 'error';
    $messageRes = 'Ticket code and response are required';
  } else {
    $ticketInfoSql = "SELECT t.id, t.subject, t.status,
                             ca.id AS created_by_admin_id, ca.email AS created_email, ca.first_name AS created_first_name,
                             aa.id AS assigned_admin_id, aa.email AS assigned_email, aa.first_name AS assigned_first_name
                      FROM admin_support_tickets t
                      LEFT JOIN admins ca ON t.created_by_admin_id = ca.id
                      LEFT JOIN admins aa ON t.assigned_admin_id = aa.id
                      WHERE t.code = '$code'";
    $ticketInfoQ = mysqli_query($conn, $ticketInfoSql);
    if ($row = mysqli_fetch_assoc($ticketInfoQ)) {
      $ticketId = (int) $row['id'];
      $now = date("Y-m-d H:i:s");
      $adminIdVal = (int) $admin_id;

      // Insert admin message
      mysqli_query(
        $conn,
        "INSERT INTO admin_support_ticket_messages
          (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at)
         VALUES
          ($ticketId, 'admin', NULL, $adminIdVal, '$response', 0, '$now')"
      );
      $messageId = mysqli_insert_id($conn);

      // Attachments
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
          $storedName = "admin_support_reply_{$ticketId}_{$messageId}_" . ($idx + 1);
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
              "INSERT INTO admin_support_ticket_attachments
                 (message_id, file_path, file_name, mime_type, file_size)
               VALUES
                 ($messageId, '" . mysqli_real_escape_string($conn, $relativePath) . "', '" . mysqli_real_escape_string($conn, $origName) . "', '" . mysqli_real_escape_string($conn, $mimeType) . "', $fileSize)"
            );
          }
        }
      }

      $new_status = $markClosed ? 'closed' : 'open';

      $updateFields = array(
        "status = '$new_status'",
        "last_message_at = '$now'"
      );
      if ($new_status === 'closed') {
        $updateFields[] = "closed_at = '$now'";
      } else {
        $updateFields[] = "closed_at = NULL";
      }

      $sql = "UPDATE admin_support_tickets SET " . implode(', ', $updateFields) . " WHERE id = $ticketId";
      mysqli_query($conn, $sql);

      if (mysqli_affected_rows($conn) > 0) {
        // Email notifications (do not email any end-users; only admins)
        $ticketTitle = $row['subject'];
        $responseHtml = nl2br($response);
        $subjectLine = "Update: Internal Ticket (#$code) - $ticketTitle";

        // Notify assigned admin if not the sender
        $assignedAdminId = $row['assigned_admin_id'] ? (int) $row['assigned_admin_id'] : null;
        if ($assignedAdminId && $assignedAdminId !== $adminIdVal) {
          $assignedEmail = $row['assigned_email'];
          $assignedFirstName = trim($row['assigned_first_name'] ?? '');
          if ($assignedEmail) {
            $mailBody = "Hi $assignedFirstName,<br><br>"
              . "There's a new update on internal support ticket <strong>#$code</strong>.<br><br>"
              . "<strong>Message:</strong><br>$responseHtml<br><br>"
              . "Status: " . ucfirst($new_status) . ".";
            sendMail($subjectLine, $mailBody, $assignedEmail);
          }
        }

        // Notify creator if not the sender
        $createdById = $row['created_by_admin_id'] ? (int) $row['created_by_admin_id'] : null;
        if ($createdById && $createdById !== $adminIdVal) {
          $creatorEmail = $row['created_email'];
          $creatorFirstName = trim($row['created_first_name'] ?? '');
          if ($creatorEmail) {
            $mailBody = "Hi $creatorFirstName,<br><br>"
              . "There's a new update on internal support ticket <strong>#$code</strong>.<br><br>"
              . "<strong>Message:</strong><br>$responseHtml<br><br>"
              . "Status: " . ucfirst($new_status) . ".";
            sendMail($subjectLine, $mailBody, $creatorEmail);
          }
        }

        $statusRes = 'success';
        $messageRes = 'Response saved';
      } else {
        $statusRes = 'error';
        $messageRes = 'Update failed';
      }
    } else {
      $statusRes = 'error';
      $messageRes = 'Ticket not found';
    }
  }
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'tickets' => $tickets,
  'ticket' => $ticket,
  'messages' => $messages
);

header('Content-Type: application/json');
echo json_encode($responseData);

?>
