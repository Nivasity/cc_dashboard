<?php
require_once __DIR__ . '/../config/db.php';
$conn = mysqli_connect("127.0.0.1", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

// Set the timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

?>
