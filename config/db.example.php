<?php
// Database Configuration Example
// Copy this file to db.php and add your actual credentials
// This file is ignored by Git to keep your credentials secure

// Optional DB host/name overrides
define('DB_HOST', 'localhost');
define('DB_NAME', 'niverpay_db');

// Database Credentials
define('DB_USERNAME', 'your_db_username');
define('DB_PASSWORD', 'your_db_password');

// API Bearer Token (use a long random string in production)
define('API_BEARER_TOKEN', 'replace_with_secure_random_bearer_token');

// Admin ID used by API for admin-origin actions (support responses, notifications)
define('API_ADMIN_ID', 1);

?>
