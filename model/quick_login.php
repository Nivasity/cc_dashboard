<?php
session_start();
include('config.php');
include('functions.php');

header('Content-Type: application/json');

$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_role = $_SESSION['nivas_adminRole'] ?? null;

// Check authorization - only admin roles 1, 2, 3 can access
if (!$admin_id || !in_array((int)$admin_role, [1, 2, 3], true)) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
  exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
  switch ($action) {
    case 'search_student':
      searchStudent($conn);
      break;
    
    case 'create_link':
      createQuickLoginLink($conn, $admin_id);
      break;
    
    case 'list_codes':
      listQuickLoginCodes($conn);
      break;
    
    case 'delete_code':
      deleteQuickLoginCode($conn, $admin_id);
      break;
    
    default:
      echo json_encode(['success' => false, 'message' => 'Invalid action']);
      break;
  }
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Search for student by email
function searchStudent($conn) {
  $email = $_POST['email'] ?? '';
  
  if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    return;
  }
  
  // Use prepared statement to prevent SQL injection
  $stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, email, phone, matric_no, school, dept 
            FROM users 
            WHERE email = ?
            LIMIT 1");
  
  if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
  }
  
  mysqli_stmt_bind_param($stmt, "s", $email);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  
  if (!$result) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
  }
  
  $student = mysqli_fetch_assoc($result);
  mysqli_stmt_close($stmt);
  
  if ($student) {
    // Get school name
    $school_id = (int)$student['school'];
    $stmt = mysqli_prepare($conn, "SELECT name FROM schools WHERE id = ?");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, "i", $school_id);
      mysqli_stmt_execute($stmt);
      $school_result = mysqli_stmt_get_result($stmt);
      $school = mysqli_fetch_assoc($school_result);
      $student['school_name'] = $school['name'] ?? 'N/A';
      mysqli_stmt_close($stmt);
    }
    
    // Get department name
    $dept_id = (int)$student['dept'];
    $stmt = mysqli_prepare($conn, "SELECT name FROM depts WHERE id = ?");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, "i", $dept_id);
      mysqli_stmt_execute($stmt);
      $dept_result = mysqli_stmt_get_result($stmt);
      $dept = mysqli_fetch_assoc($dept_result);
      $student['dept_name'] = $dept['name'] ?? 'N/A';
      mysqli_stmt_close($stmt);
    }
    
    echo json_encode(['success' => true, 'student' => $student]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
  }
}

// Create a new quick login link
function createQuickLoginLink($conn, $admin_id) {
  $student_id = (int)($_POST['student_id'] ?? 0);
  $admin_id = (int)$admin_id;
  
  if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    return;
  }
  
  if ($admin_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
    return;
  }
  
  // Verify student exists and get school ID using prepared statement
  $stmt = mysqli_prepare($conn, "SELECT id, school FROM users WHERE id = ?");
  if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
  }
  
  mysqli_stmt_bind_param($stmt, "i", $student_id);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  
  if (mysqli_num_rows($result) == 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    return;
  }
  
  $student = mysqli_fetch_assoc($result);
  $school_id = (int)$student['school'];
  mysqli_stmt_close($stmt);
  
  // Generate unique code
  $code = bin2hex(random_bytes(32));
  
  // Set expiry to 24 hours from now
  date_default_timezone_set('Africa/Lagos');
  $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
  
  // Insert into database using prepared statement
  $stmt = mysqli_prepare($conn, "INSERT INTO quick_login_codes (student_id, code, expiry_datetime, created_by) 
            VALUES (?, ?, ?, ?)");
  
  if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to create login link']);
    return;
  }
  
  mysqli_stmt_bind_param($stmt, "issi", $student_id, $code, $expiry, $admin_id);
  
  if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    
    // Use different domain based on school ID
    $domain = ($school_id === 1) ? "https://funaab.nivasity.com/" : "https://nivasity.com/";
    $link = $domain . "demo.php?code=$code";
    
    echo json_encode([
      'success' => true,
      'message' => 'Login link created successfully',
      'link' => $link,
      'code' => $code,
      'expiry' => $expiry
    ]);
  } else {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Failed to create login link']);
  }
}

// List all quick login codes with student details
function listQuickLoginCodes($conn) {
  // Update expired codes using prepared statement
  date_default_timezone_set('Africa/Lagos');
  $now = date('Y-m-d H:i:s');
  
  $stmt = mysqli_prepare($conn, "UPDATE quick_login_codes SET status = 'expired' WHERE expiry_datetime < ? AND status = 'active'");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
  
  $query = "SELECT 
              qlc.id,
              qlc.code,
              qlc.expiry_datetime,
              qlc.status,
              qlc.created_at,
              u.id as student_id,
              u.first_name,
              u.last_name,
              u.email,
              u.phone,
              u.matric_no,
              u.school as school_id,
              s.name as school_name,
              d.name as dept_name
            FROM quick_login_codes qlc
            JOIN users u ON qlc.student_id = u.id
            LEFT JOIN schools s ON u.school = s.id
            LEFT JOIN depts d ON u.dept = d.id
            ORDER BY qlc.created_at DESC";
  
  $result = mysqli_query($conn, $query);
  
  if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
  }
  
  $codes = [];
  while ($row = mysqli_fetch_assoc($result)) {
    // Use different domain based on school ID
    $school_id = (int)$row['school_id'];
    $domain = ($school_id === 1) ? "https://funaab.nivasity.com/" : "https://nivasity.com/";
    $row['link'] = $domain . "demo.php?code=" . $row['code'];
    $codes[] = $row;
  }
  
  echo json_encode(['success' => true, 'codes' => $codes]);
}

// Delete a quick login code
function deleteQuickLoginCode($conn, $admin_id) {
  $code_id = (int)($_POST['code_id'] ?? 0);
  
  if ($code_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid code ID']);
    return;
  }
  
  // Get code details for audit log before deletion
  $stmt = mysqli_prepare($conn, "SELECT student_id, code, expiry_datetime FROM quick_login_codes WHERE id = ?");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $code_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $code_details = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
  }
  
  // Mark as deleted instead of actually deleting using prepared statement
  $stmt = mysqli_prepare($conn, "UPDATE quick_login_codes SET status = 'deleted' WHERE id = ?");
  
  if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
  }
  
  mysqli_stmt_bind_param($stmt, "i", $code_id);
  
  if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    
    // Log the action with detailed information
    $details = "Deleted quick login code ID: $code_id";
    if (isset($code_details)) {
      $details .= ", Student ID: " . $code_details['student_id'] . ", Code: " . substr($code_details['code'], 0, 10) . "..., Expiry: " . $code_details['expiry_datetime'];
    }
    log_audit_event($conn, $admin_id, 'delete', 'quick_login_code', $code_id, $details);
    
    echo json_encode(['success' => true, 'message' => 'Login code deleted successfully']);
  } else {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Failed to delete login code']);
  }
}

?>
