<?php
session_start();
include('config.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = '';

$user_id = $_SESSION['nivas_adminId'] ?? null;
$admin_role = $_SESSION['nivas_adminRole'] ?? null;

// Only allow admin role 6 to access grant operations
if ($admin_role != 6) {
  $statusRes = 'error';
  $messageRes = 'Unauthorized access!';
  echo json_encode(["status" => $statusRes, "message" => $messageRes]);
  exit();
}

// Handle grant action
if (isset($_POST['grant_action']) && isset($_POST['grant_id'])) {
  $grant_id = mysqli_real_escape_string($conn, $_POST['grant_id']);
  
  // Get grant details
  $grant_query = mysqli_query($conn, "SELECT * FROM material_grants WHERE id = $grant_id AND status = 'pending'");
  
  if (mysqli_num_rows($grant_query) > 0) {
    $grant = mysqli_fetch_array($grant_query);
    $buyer_id = $grant['buyer_id'];
    
    // Update grant status
    date_default_timezone_set('Africa/Lagos');
    $granted_at = date('Y-m-d H:i:s');
    
    $update_query = "UPDATE material_grants SET 
      status = 'granted', 
      admin_id = $user_id, 
      last_student_id = $buyer_id,
      granted_at = '$granted_at'
      WHERE id = $grant_id";
    
    if (mysqli_query($conn, $update_query)) {
      $statusRes = 'success';
      $messageRes = 'Material grant approved successfully!';
      
      // Log audit event
      log_audit_event($conn, $user_id, 'grant', 'material_grant', $grant_id, [
        'ref_id' => $grant['manual_bought_ref_id'],
        'buyer_id' => $buyer_id,
        'manual_id' => $grant['manual_id']
      ]);
    } else {
      $statusRes = 'error';
      $messageRes = 'Failed to grant material. Please try again.';
    }
  } else {
    $statusRes = 'error';
    $messageRes = 'Grant record not found or already granted!';
  }
  
  echo json_encode(["status" => $statusRes, "message" => $messageRes]);
  exit();
}

// List material grants (default action)
if (isset($_GET['list']) || !isset($_GET['action'])) {
  $status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
  
  $where_clause = "WHERE 1=1";
  if ($status_filter != 'all') {
    $where_clause .= " AND mg.status = '$status_filter'";
  }
  
  $query = "SELECT 
    mg.id,
    mg.manual_bought_ref_id,
    mg.status,
    mg.granted_at,
    mg.created_at,
    m.title as material_title,
    m.course_code,
    m.price,
    u.first_name as buyer_first_name,
    u.last_name as buyer_last_name,
    u.matric_no as buyer_matric,
    u.email as buyer_email,
    s.name as school_name,
    d.name as dept_name,
    seller.first_name as seller_first_name,
    seller.last_name as seller_last_name,
    admin.first_name as granter_first_name,
    admin.last_name as granter_last_name
  FROM material_grants mg
  LEFT JOIN manuals m ON mg.manual_id = m.id
  LEFT JOIN users u ON mg.buyer_id = u.id
  LEFT JOIN users seller ON mg.seller_id = seller.id
  LEFT JOIN schools s ON mg.school_id = s.id
  LEFT JOIN depts d ON u.dept = d.id
  LEFT JOIN admins admin ON mg.admin_id = admin.id
  $where_clause
  ORDER BY mg.created_at DESC";
  
  $result = mysqli_query($conn, $query);
  $grants = [];
  
  while ($row = mysqli_fetch_assoc($result)) {
    $grants[] = $row;
  }
  
  header('Content-Type: application/json');
  echo json_encode([
    "status" => "success",
    "data" => $grants
  ]);
  exit();
}

// Get grant statistics
if (isset($_GET['stats'])) {
  $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM material_grants");
  $pending_query = mysqli_query($conn, "SELECT COUNT(*) as pending FROM material_grants WHERE status = 'pending'");
  $granted_query = mysqli_query($conn, "SELECT COUNT(*) as granted FROM material_grants WHERE status = 'granted'");
  
  $total = mysqli_fetch_array($total_query)['total'];
  $pending = mysqli_fetch_array($pending_query)['pending'];
  $granted = mysqli_fetch_array($granted_query)['granted'];
  
  header('Content-Type: application/json');
  echo json_encode([
    "status" => "success",
    "stats" => [
      "total" => $total,
      "pending" => $pending,
      "granted" => $granted
    ]
  ]);
  exit();
}
