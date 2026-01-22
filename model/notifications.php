<?php
session_start();
include('config.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = '';
$data = null;

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;

// Restrict access to admin roles 1, 2, and 3 only
if (!in_array($admin_role, [1, 2, 3])) {
  $messageRes = 'Access denied. Only admin roles 1-3 can manage notifications.';
  $responseData = array(
    'status' => $statusRes,
    'message' => $messageRes,
    'data' => $data
  );
  header('Content-Type: application/json');
  echo json_encode($responseData);
  exit;
}

/**
 * Send notification via API
 */
function sendNotificationViaAPI($adminEmail, $adminPassword, $title, $body, $type, $targetData) {
  // API endpoint from documentation
  $apiUrl = 'https://api.nivasity.com/notifications/admin/send.php';
  
  $payload = array(
    'email' => $adminEmail,
    'password' => $adminPassword,
    'title' => $title,
    'body' => $body,
    'type' => $type
  );
  
  // Merge target data (user_id, user_ids, school_id, or broadcast)
  $payload = array_merge($payload, $targetData);
  
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
  
  // Prepare request body for debugging (mask password)
  $requestBodyDebug = $payload;
  $requestBodyDebug['password'] = '***masked***';
  
  if ($curlError) {
    return array(
      'success' => false, 
      'message' => 'Connection error: ' . $curlError,
      'request_body' => $requestBodyDebug,
      'response_body' => null
    );
  }
  
  $responseData = json_decode($response, true);
  
  if ($httpCode === 200 && isset($responseData['status']) && $responseData['status'] === 'success') {
    return array(
      'success' => true, 
      'message' => $responseData['message'] ?? 'Notification sent successfully', 
      'data' => $responseData['data'] ?? null,
      'request_body' => $requestBodyDebug,
      'response_body' => $responseData
    );
  } else {
    return array(
      'success' => false, 
      'message' => $responseData['message'] ?? 'Failed to send notification',
      'request_body' => $requestBodyDebug,
      'response_body' => $responseData
    );
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  
  if ($action === 'send_notification') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $type = trim($_POST['type'] ?? 'general');
    $targetType = $_POST['target_type'] ?? 'broadcast';
    $schoolId = intval($_POST['school_id'] ?? 0);
    $userEmail = trim($_POST['user_email'] ?? '');
    
    if (empty($title)) {
      $messageRes = 'Notification title is required.';
    } elseif (empty($body)) {
      $messageRes = 'Notification message is required.';
    } else {
      // Get admin credentials from session
      $admin_stmt = $conn->prepare('SELECT email, password FROM admins WHERE id = ? LIMIT 1');
      $admin_stmt->bind_param('i', $admin_id);
      $admin_stmt->execute();
      $admin_result = $admin_stmt->get_result();
      $admin_data = $admin_result ? $admin_result->fetch_assoc() : null;
      $admin_stmt->close();
      
      if (!$admin_data) {
        $messageRes = 'Admin credentials not found.';
      } else {
        $adminEmail = $admin_data['email'];
        $adminPassword = $admin_data['password']; // Already MD5 hashed in database
        
        // Build target data based on target type
        $targetData = array();
        
        if ($targetType === 'broadcast') {
          $targetData['broadcast'] = true;
        } elseif ($targetType === 'school') {
          if ($schoolId <= 0) {
            $messageRes = 'Please select a school.';
          } else {
            $targetData['school_id'] = $schoolId;
          }
        } elseif ($targetType === 'user') {
          if (empty($userEmail)) {
            $messageRes = 'Please provide a user email address.';
          } else {
            // Look up user_id from email
            $user_stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $user_stmt->bind_param('s', $userEmail);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result ? $user_result->fetch_assoc() : null;
            $user_stmt->close();
            
            if (!$user_data) {
              $messageRes = 'User not found with the provided email address.';
            } else {
              $targetData['user_id'] = (int)$user_data['id'];
            }
          }
        } else {
          $messageRes = 'Invalid target type.';
        }
        
        // Send notification if no errors
        if (empty($messageRes)) {
          $result = sendNotificationViaAPI($adminEmail, $adminPassword, $title, $body, $type, $targetData);
          
          if ($result['success']) {
            $statusRes = 'success';
            $messageRes = $result['message'];
            $data = array(
              'notification_data' => $result['data'],
              'request_body' => $result['request_body'],
              'response_body' => $result['response_body']
            );
            
            // Log the action
            log_audit_event($conn, $admin_id, 'create', 'notification', null, [
              'title' => $title,
              'type' => $type,
              'target_type' => $targetType,
              'target_data' => $targetData
            ]);
          } else {
            $messageRes = $result['message'];
            $data = array(
              'request_body' => $result['request_body'],
              'response_body' => $result['response_body']
            );
          }
        }
      }
    }
  } else {
    $messageRes = 'Invalid action.';
  }
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'data' => $data
);

header('Content-Type: application/json');
echo json_encode($responseData);
?>
