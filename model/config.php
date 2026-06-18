<?php
require("../config/db.php");
$conn = mysqli_connect("localhost", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

mysqli_set_charset($conn, "utf8mb4");

// Set the timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

?>
