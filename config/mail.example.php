<?php
// SMTP Configuration Example for Email Fallback
// Copy this file to smtp.php and add your actual SMTP credentials
// This file is ignored by Git to keep your credentials secure

// SMTP Server Configuration
// These credentials are used when BREVO API credits are low or unavailable

require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

define('SMTP_HOST', 'mail.nivasity.com');
define('SMTP_USERNAME', 'admin@nivasity.com');
define('SMTP_PASSWORD', 'pass');
define('SMTP_PORT', 465);
?>
