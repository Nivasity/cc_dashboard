<?php
// Database Configuration Example
// Copy this file to db.php and add your actual credentials
// This file is ignored by Git to keep your credentials secure

// Database Credentials
define('DB_USERNAME', 'your_db_username');
define('DB_PASSWORD', 'your_db_password');

// SMTP Configuration for Email Fallback
// These credentials are used when BREVO API credits are low or unavailable
// Configure your SMTP server details below

define('SMTP_HOST', 'your.smtp.server.com');     // SMTP server hostname (e.g., smtp.gmail.com, mail.yourdomain.com)
define('SMTP_PORT', 587);                         // SMTP port (587 for TLS, 465 for SSL, 25 for non-encrypted)
define('SMTP_USERNAME', 'your_email@domain.com'); // SMTP username (usually your email address)
define('SMTP_PASSWORD', 'your_smtp_password');    // SMTP password or app-specific password
define('SMTP_FROM_EMAIL', 'contact@nivasity.com'); // From email address for outgoing emails
define('SMTP_FROM_NAME', 'Nivasity');             // From name for outgoing emails

?>