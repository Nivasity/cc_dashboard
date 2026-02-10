<?php
/**
 * Notification Helper Functions
 * 
 * This file contains helper functions for sending notifications from various
 * integration points in the admin panel (manual transactions, support tickets, etc.)
 */

/**
 * Send notification via API
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin ID sending the notification
 * @param int|array $user_ids Single user ID or array of user IDs
 * @param string $title Notification title
 * @param string $body Notification body/message
 * @param string $type Notification type (general, payment, support, etc.)
 * @param array $data Additional data to include in notification
 * @return array Result array with 'success' boolean and 'message' string
 */
function sendNotification($conn, $admin_id, $user_ids, $title, $body, $type = 'general', $data = array()) {
  // Get admin credentials
  $admin_stmt = $conn->prepare('SELECT email, password FROM admins WHERE id = ? LIMIT 1');
  $admin_stmt->bind_param('i', $admin_id);
  $admin_stmt->execute();
  $admin_result = $admin_stmt->get_result();
  $admin_data = $admin_result ? $admin_result->fetch_assoc() : null;
  $admin_stmt->close();
  
  if (!$admin_data) {
    return array('success' => false, 'message' => 'Admin credentials not found');
  }
  
  $adminEmail = $admin_data['email'];
  $adminPassword = $admin_data['password']; // Already MD5 hashed
  
  // Build API payload
  $payload = array(
    'email' => $adminEmail,
    'password' => $adminPassword,
    'title' => $title,
    'body' => $body,
    'type' => $type
  );
  
  // Add user targeting based on API specification:
  // - user_id (singular): Single user
  // - user_ids (plural): Multiple specific users
  // - school_id: All users in a school
  // - broadcast: All active users
  if (is_array($user_ids)) {
    $payload['user_ids'] = $user_ids;
  } else {
    $payload['user_id'] = (int)$user_ids;
  }
  
  // Add optional data
  if (!empty($data)) {
    $payload['data'] = $data;
  }
  
  // Send to API
  $apiUrl = 'https://api.nivasity.com/notifications/admin/send.php';
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $apiUrl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);
  
  if ($curlError) {
    error_log('Notification API error: ' . $curlError);
    return array('success' => false, 'message' => 'Connection error: ' . $curlError);
  }
  
  $responseData = json_decode($response, true);
  
  if ($httpCode === 200 && isset($responseData['status']) && $responseData['status'] === 'success') {
    return array('success' => true, 'message' => $responseData['message'] ?? 'Notification sent successfully');
  } else {
    error_log('Notification API failed: ' . ($responseData['message'] ?? 'Unknown error'));
    return array('success' => false, 'message' => $responseData['message'] ?? 'Failed to send notification');
  }
}

/**
 * Notify user about manual transaction confirmation
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin who created the transaction
 * @param int $user_id User who received the transaction
 * @param string $transaction_ref Transaction reference
 * @param int $amount Transaction amount
 * @param array $material_titles Array of material titles purchased
 */
function notifyManualTransactionConfirmation($conn, $admin_id, $user_id, $transaction_ref, $amount, $material_titles = array()) {
  $materialsText = '';
  if (!empty($material_titles)) {
    $materialsText = ' for ' . implode(', ', $material_titles);
  }
  
  $title = 'Payment Confirmed';
  $body = "Your payment of ₦" . number_format($amount) . " has been manually confirmed by the admin" . $materialsText . ". Transaction reference: " . $transaction_ref;
  
  return sendNotification($conn, $admin_id, $user_id, $title, $body, 'payment', array(
    'action' => 'order_receipt',
    'tx_ref' => $transaction_ref,
    'amount' => $amount,
    'status' => 'success'
  ));
}

/**
 * Notify user about support ticket response
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin who responded
 * @param int $user_id User who owns the ticket
 * @param int $ticket_id Ticket ID
 * @param string $ticket_code Ticket code
 * @param string $subject Ticket subject
 */
function notifySupportTicketResponse($conn, $admin_id, $user_id, $ticket_id, $ticket_code, $subject) {
  $title = 'Support Ticket Update';
  $body = "You have a new response on your support ticket #" . $ticket_code . ": " . $subject;
  
  return sendNotification($conn, $admin_id, $user_id, $title, $body, 'support', array(
    'action' => 'support_ticket',
    'ticket_id' => $ticket_id,
    'ticket_code' => $ticket_code,
    'subject' => $subject,
    'replier' => 'Support Team'
  ));
}

/**
 * Notify user about support ticket closure
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin who closed the ticket
 * @param int $user_id User who owns the ticket
 * @param int $ticket_id Ticket ID
 * @param string $ticket_code Ticket code
 * @param string $subject Ticket subject
 */
function notifySupportTicketClosed($conn, $admin_id, $user_id, $ticket_id, $ticket_code, $subject) {
  $title = 'Support Ticket Closed';
  $body = "Your support ticket #" . $ticket_code . " (" . $subject . ") has been resolved and closed.";
  
  return sendNotification($conn, $admin_id, $user_id, $title, $body, 'support', array(
    'action' => 'support_ticket',
    'ticket_id' => $ticket_id,
    'ticket_code' => $ticket_code,
    'subject' => $subject,
    'status' => 'closed'
  ));
}

/**
 * Notify user about profile update
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin who made the update
 * @param int $user_id User whose profile was updated
 * @param string $update_type Type of update (status, info, verification)
 */
function notifyStudentProfileUpdate($conn, $admin_id, $user_id, $update_type = 'info') {
  $title = 'Profile Updated';
  
  if ($update_type === 'status') {
    $body = "Your account status has been updated by an administrator. Please check your profile for details.";
  } elseif ($update_type === 'verification') {
    $body = "Your account has been verified by an administrator. You can now access all features.";
  } else {
    $body = "Your profile information has been updated by an administrator. Please review the changes.";
  }
  
  return sendNotification($conn, $admin_id, $user_id, $title, $body, 'general', array(
    'update_type' => $update_type
  ));
}

/**
 * Notify students when a new course material is posted/created
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin who created the material
 * @param int $manual_id Manual/material ID
 * @param string $title Material title
 * @param string $course_code Course code
 * @param int $dept_id Department ID (0 for all departments in faculty)
 * @param int $faculty_id Faculty ID
 * @param int $school_id School ID
 */
function notifyCourseMaterialCreated($conn, $admin_id, $manual_id, $title, $course_code, $dept_id, $faculty_id, $school_id) {
  // Get students based on department setting
  $student_ids = array();
  
  if ($dept_id == 0) {
    // Material is for all departments in the faculty
    // Get all students in the same faculty and school
    $students_stmt = $conn->prepare('
      SELECT DISTINCT u.id 
      FROM users u 
      INNER JOIN depts d ON u.dept = d.id 
      WHERE d.faculty_id = ? 
        AND u.school = ? 
        AND u.status = "active" 
        AND u.role = "student"
    ');
    $students_stmt->bind_param('ii', $faculty_id, $school_id);
  } else {
    // Material is for a specific department
    // Get all students in the same department and school
    $students_stmt = $conn->prepare('
      SELECT id 
      FROM users 
      WHERE dept = ? 
        AND school = ? 
        AND status = "active" 
        AND role = "student"
    ');
    $students_stmt->bind_param('ii', $dept_id, $school_id);
  }
  
  $students_stmt->execute();
  $students_result = $students_stmt->get_result();
  
  while ($row = $students_result->fetch_assoc()) {
    $student_ids[] = (int)$row['id'];
  }
  $students_stmt->close();
  
  if (empty($student_ids)) {
    return array('success' => false, 'message' => 'No students found to notify');
  }
  
  $notif_title = 'New Course Material Available';
  $notif_body = "A new course material \"" . $title . "\" (" . $course_code . ") is now available for purchase.";
  
  return sendNotification($conn, $admin_id, $student_ids, $notif_title, $notif_body, 'material', array(
    'action' => 'material_details',
    'manual_id' => $manual_id,
    'title' => $title,
    'course_code' => $course_code,
    'status' => 'open'
  ));
}

/**
 * Notify students when course material for their department is closed
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin who closed the material
 * @param int $manual_id Manual/material ID
 * @param string $title Material title
 * @param string $course_code Course code
 * @param int $dept_id Department ID
 * @param int $school_id School ID
 */
function notifyCourseMaterialClosed($conn, $admin_id, $manual_id, $title, $course_code, $dept_id, $school_id) {
  // Get all students in the same department and school
  $students_stmt = $conn->prepare('SELECT id FROM users WHERE dept = ? AND school = ? AND status = "active" AND role = "student"');
  $students_stmt->bind_param('ii', $dept_id, $school_id);
  $students_stmt->execute();
  $students_result = $students_stmt->get_result();
  
  $student_ids = array();
  while ($row = $students_result->fetch_assoc()) {
    $student_ids[] = (int)$row['id'];
  }
  $students_stmt->close();
  
  if (empty($student_ids)) {
    return array('success' => false, 'message' => 'No students found in department');
  }
  
  $notif_title = 'Course Material Closed';
  $notif_body = "The course material \"" . $title . "\" (" . $course_code . ") is no longer available for purchase.";
  
  return sendNotification($conn, $admin_id, $student_ids, $notif_title, $notif_body, 'material', array(
    'action' => 'material_details',
    'manual_id' => $manual_id,
    'title' => $title,
    'course_code' => $course_code,
    'status' => 'closed'
  ));
}

/**
 * Send broadcast notification to all students
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin sending the notification
 * @param string $title Notification title
 * @param string $body Notification body
 * @param string $type Notification type
 * @param int|null $school_id Optional school ID to limit broadcast
 */
function sendBroadcastNotification($conn, $admin_id, $title, $body, $type = 'announcement', $school_id = null) {
  // Get admin credentials
  $admin_stmt = $conn->prepare('SELECT email, password FROM admins WHERE id = ? LIMIT 1');
  $admin_stmt->bind_param('i', $admin_id);
  $admin_stmt->execute();
  $admin_result = $admin_stmt->get_result();
  $admin_data = $admin_result ? $admin_result->fetch_assoc() : null;
  $admin_stmt->close();
  
  if (!$admin_data) {
    return array('success' => false, 'message' => 'Admin credentials not found');
  }
  
  $adminEmail = $admin_data['email'];
  $adminPassword = $admin_data['password'];
  
  $payload = array(
    'email' => $adminEmail,
    'password' => $adminPassword,
    'title' => $title,
    'body' => $body,
    'type' => $type
  );
  
  if ($school_id !== null) {
    $payload['school_id'] = (int)$school_id;
  } else {
    $payload['broadcast'] = true;
  }
  
  $apiUrl = 'https://api.nivasity.com/notifications/admin/send.php';
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $apiUrl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);
  
  if ($curlError) {
    return array('success' => false, 'message' => 'Connection error: ' . $curlError);
  }
  
  $responseData = json_decode($response, true);
  
  if ($httpCode === 200 && isset($responseData['status']) && $responseData['status'] === 'success') {
    return array('success' => true, 'message' => $responseData['message'] ?? 'Broadcast sent successfully');
  } else {
    return array('success' => false, 'message' => $responseData['message'] ?? 'Failed to send broadcast');
  }
}
?>
