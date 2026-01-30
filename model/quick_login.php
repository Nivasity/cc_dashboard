<?php
session_start();
include('config.php');

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
  $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
  
  if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    return;
  }
  
  $query = "SELECT id, first_name, last_name, email, phone, matric_no, school, dept 
            FROM users 
            WHERE email = '$email' AND role = 'student' 
            LIMIT 1";
  
  $result = mysqli_query($conn, $query);
  
  if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    return;
  }
  
  $student = mysqli_fetch_assoc($result);
  
  if ($student) {
    // Get school name
    $school_id = $student['school'];
    $school_query = mysqli_query($conn, "SELECT name FROM schools WHERE id = $school_id");
    $school = mysqli_fetch_assoc($school_query);
    $student['school_name'] = $school['name'] ?? 'N/A';
    
    // Get department name
    $dept_id = $student['dept'];
    $dept_query = mysqli_query($conn, "SELECT name FROM depts WHERE id = $dept_id");
    $dept = mysqli_fetch_assoc($dept_query);
    $student['dept_name'] = $dept['name'] ?? 'N/A';
    
    echo json_encode(['success' => true, 'student' => $student]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
  }
}

// Create a new quick login link
function createQuickLoginLink($conn, $admin_id) {
  $student_id = (int)($_POST['student_id'] ?? 0);
  
  if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    return;
  }
  
  // Verify student exists
  $student_query = mysqli_query($conn, "SELECT id FROM users WHERE id = $student_id AND role = 'student'");
  if (mysqli_num_rows($student_query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    return;
  }
  
  // Generate unique code
  $code = bin2hex(random_bytes(32));
  
  // Set expiry to 24 hours from now
  date_default_timezone_set('Africa/Lagos');
  $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
  
  // Insert into database
  $query = "INSERT INTO quick_login_codes (student_id, code, expiry_datetime, created_by) 
            VALUES ($student_id, '$code', '$expiry', $admin_id)";
  
  if (mysqli_query($conn, $query)) {
    $link = "https://nivasity.com/demo.php?code=$code";
    
    echo json_encode([
      'success' => true,
      'message' => 'Login link created successfully',
      'link' => $link,
      'code' => $code,
      'expiry' => $expiry
    ]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Failed to create login link']);
  }
}

// List all quick login codes with student details
function listQuickLoginCodes($conn) {
  // Update expired codes
  date_default_timezone_set('Africa/Lagos');
  $now = date('Y-m-d H:i:s');
  mysqli_query($conn, "UPDATE quick_login_codes SET status = 'expired' WHERE expiry_datetime < '$now' AND status = 'active'");
  
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
    $row['link'] = "https://nivasity.com/demo.php?code=" . $row['code'];
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
  
  // Mark as deleted instead of actually deleting
  $query = "UPDATE quick_login_codes SET status = 'deleted' WHERE id = $code_id";
  
  if (mysqli_query($conn, $query)) {
    // Log the action
    include('audit.php');
    logAudit($conn, $admin_id, 'delete', 'quick_login_code', $code_id, 'Deleted quick login code');
    
    echo json_encode(['success' => true, 'message' => 'Login code deleted successfully']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete login code']);
  }
}

?>
