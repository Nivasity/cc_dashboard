<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('config.php');
include('functions.php');
$statusRes = 'failed';
$schools = $depts = null;

if (isset($_GET['get_data'])) {
  $get_data = $_GET['get_data'];

  if ($get_data == 'schools') {
    $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE status = 'active'");

    if (mysqli_num_rows($school_query) >= 1) {
      $schools = array();

      while ($school = mysqli_fetch_array($school_query)) {
        $schools[] = array(
          'id' => $school['id'],
          'name' => $school['name']
        );
      }
      $statusRes = "success";
    } else {
      $statusRes = "not found";
    }
  }

  if ($get_data == 'school_dept') {
    $school_id = $_GET['school'];
    $dept = $_GET['dept'];
    $school_ = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM schools WHERE id = $school_id"));
    $dept_query = mysqli_query($conn, "SELECT * FROM depts_$school_id WHERE id = $dept");
    
    $depts = "NULL";
    if (mysqli_num_rows($dept_query) > 0) {
      $dept_ = mysqli_fetch_array($dept_query);
      $depts = $dept_['name'];
    }
    $schools = $school_['name'];
    $statusRes = "success";
  }
}

if (isset($_GET['get_data'])) {
  $get_data = $_GET['get_data'];

  if ($get_data == 'depts') {
    $school = $_GET['school'];
    $dept_query = mysqli_query($conn, "SELECT id, name FROM `depts_$school` WHERE status = 'active'");

    if (mysqli_num_rows($dept_query) >= 1) {
      $depts = array();

      while ($dept = mysqli_fetch_array($dept_query)) {
        $depts[] = array(
          'id' => $dept['id'],
          'name' => $dept['name']
        );
      }
      $statusRes = "success";
    } else {
      $statusRes = "not found";
    }
  }
}

$responseData = array(
  "status" => "$statusRes",
  "schools" => $schools,
  "departments" => $depts
);

// // Set the appropriate headers for JSON response
// header('Accept-Encoding: gzip, deflate');
// header('Cache-Control: no-store, no-cache, must-revalidate');
// header('Content-Type: application/json');

// Encode the data as JSON and send it
var_dump($responseData);
?>