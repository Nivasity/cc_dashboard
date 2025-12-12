<?php
// BREVO SMTP Configuration Example
// Copy this file to mail.php and add your actual BREVO SMTP credentials
// Get your SMTP credentials from: https://app.brevo.com/settings/keys/smtp

// BREVO SMTP server address (typically smtp-relay.brevo.com or smtp-relay.sendinblue.com)
define('SMTP_HOST', 'smtp-relay.brevo.com');

// Your BREVO SMTP login - usually your BREVO account email
define('SMTP_USERNAME', 'your-brevo-email@example.com');

// Your BREVO SMTP password (found in SMTP & API settings, NOT your account password)
// This is different from your BREVO login password
define('SMTP_PASSWORD', 'YOUR_BREVO_SMTP_PASSWORD_HERE');

// BREVO SMTP port
// Use 465 for SSL (SMTP_SECURE = ENCRYPTION_SMTPS)
// Use 587 for TLS (SMTP_SECURE = ENCRYPTION_STARTTLS)
define('SMTP_PORT', 465);

?>
