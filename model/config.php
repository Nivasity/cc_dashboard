<?php
require_once __DIR__ . '/../config/db.php';
$conn = mysqli_connect("localhost", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

// Ensure the database connection correctly processes 4-byte Unicode characters (emojis)
mysqli_set_charset($conn, "utf8mb4");

// Set the timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

?>
