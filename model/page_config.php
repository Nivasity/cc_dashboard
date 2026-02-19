<?php
if (!isset($_SESSION['nivas_adminId'])) {
  // Capture current page for redirect after login
  $current_page = $_SERVER['REQUEST_URI'];
  $redirect_param = '?redirect=' . urlencode($current_page);
  header('Location: signin.html' . $redirect_param);
  exit();
}

$url = substr($_SERVER["SCRIPT_NAME"], strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);

$admin_id = $_SESSION['nivas_adminId'];
$session_admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;

if ($session_admin_role === 6 && $url !== 'material_grants.php') {
  header('Location: material_grants.php');
  exit();
}

$admin_ = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM admins WHERE id = $admin_id"));

$admin_image = $admin_['profile_pic'];
$admin_email = $admin_['email'];
$admin_phone = $admin_['phone'];
$admin_status = $admin_['status'];
$f_name = $admin_['first_name'];
$l_name = $admin_['last_name'];
$admin_name = $f_name .' '. $l_name;

$admin_mgt_menu = $customer_mgt_menu = $sch_mgt_menu = $student_mgt_menu = $public_mgt_menu = $support_mgt_menu = $finance_mgt_menu = $resource_mgt_menu = $grant_mgt_menu = False;

$date = date('Y-m-d');
$_day = date('w');
$day = date('l', strtotime("last sunday +$_day days"));
$short_day = date('D', strtotime("last sunday +$_day days"));

if (isset($_GET['loggedin'])) {
  date_default_timezone_set('Africa/Lagos');
  $current_login = date('Y-m-d H:i:s');
  $last_login = mysqli_fetch_array(mysqli_query($conn, "SELECT last_login FROM admins WHERE id = $admin_id"))[0];
  $last_login = new DateTime($last_login);

  mysqli_query($conn, "UPDATE admins SET last_login = '$current_login' WHERE id ='$admin_id'");
}

if ($_SESSION['nivas_adminRole'] == 1) {
  $admin_mgt_menu = True;
  $customer_mgt_menu = True;
  $sch_mgt_menu = True;
  $public_mgt_menu = True;
  $student_mgt_menu = True;
  $support_mgt_menu = True;
  $finance_mgt_menu = True;
  $resource_mgt_menu = True;
} else if ($_SESSION['nivas_adminRole'] == 2) {
  $customer_mgt_menu = True;
  $sch_mgt_menu = True;
  $public_mgt_menu = True;
  $finance_mgt_menu = True;
  $support_mgt_menu = True;
  $resource_mgt_menu = True;
} else if ($_SESSION['nivas_adminRole'] == 3) {
  $student_mgt_menu = True;
  $customer_mgt_menu = True;
  $sch_mgt_menu = True;
  $public_mgt_menu = True;
  $support_mgt_menu = True;
  $resource_mgt_menu = True;
  $finance_mgt_menu = True;
} else if ($_SESSION['nivas_adminRole'] == 4) {
  $finance_mgt_menu = True;
  $support_mgt_menu = True;
} else if ($_SESSION['nivas_adminRole'] == 5) {
  $customer_mgt_menu = True;
  $student_mgt_menu = True;
  $finance_mgt_menu = True;
  $support_mgt_menu = True;
  $resource_mgt_menu = True;
} else if ($_SESSION['nivas_adminRole'] == 6) {
  $grant_mgt_menu = True;
} else {
  $customer_mgt_menu = True;
  $student_mgt_menu = True;
  $public_mgt_menu = True;
  $support_mgt_menu = True;
  $resource_mgt_menu = True;
}

?>
