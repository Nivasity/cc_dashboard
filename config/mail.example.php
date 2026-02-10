<?php
// SMTP Configuration for PHPMailer
// Copy this file to mail.php and add your actual SMTP credentials
// This file is ignored by Git to keep your credentials secure

// SMTP Server Configuration
// PHPMailer library is loaded from PHPMailer-master directory

require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

define('SMTP_HOST', 'mail.nivasity.com');
define('SMTP_USERNAME', 'admin@nivasity.com');
define('SMTP_PASSWORD', 'pass');
define('SMTP_PORT', 465);

// Optional: Override default from address
// define('SMTP_FROM_EMAIL', 'contact@nivasity.com');
// define('SMTP_FROM_NAME', 'Nivasity');
?>
