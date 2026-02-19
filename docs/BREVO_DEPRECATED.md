# BREVO Integration - DEPRECATED

**⚠️ This documentation is DEPRECATED as of the latest update.**

## Change Summary

The application has been migrated from BREVO (formerly Sendinblue) to **PHPMailer SMTP** for all email sending operations.

### What Changed

**Old System (BREVO)**:
- Used BREVO REST API as primary email method
- Checked BREVO credits before sending
- Fell back to socket-based SMTP when credits low
- Required BREVO API key configuration

**New System (PHPMailer)**:
- Uses PHPMailer SMTP for ALL email sending
- No BREVO API dependency
- No credit checking required
- Direct SMTP connection via PHPMailer library

### Configuration

The email system now uses only `config/mail.php` with SMTP credentials:

```php
<?php
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

define('SMTP_HOST', 'mail.nivasity.com');
define('SMTP_USERNAME', 'admin@nivasity.com');
define('SMTP_PASSWORD', 'your_password');
define('SMTP_PORT', 465);
?>
```

### Deprecated Files

The following configuration is no longer used:
- `config/brevo.php` - No longer needed
- `config/brevo.example.php` - Reference only

### Deprecated Documentation

The following documentation files are outdated:
- `BREVO_EMAIL_CONFIRMATION.md` - Old BREVO implementation
- `BREVO_CONFIRMATION_SUMMARY.md` - Old BREVO summary
- `SMTP_FALLBACK_DOCUMENTATION.md` - Old fallback strategy

### Migration Notes

If you were using BREVO:
1. Remove `config/brevo.php` (if it exists)
2. Ensure `config/mail.php` has valid SMTP credentials
3. PHPMailer-master directory must be present in config/
4. All emails will now go through SMTP

### New Email Functions

The email system API remains the same:
- `sendMail($subject, $body, $to)` - Send single email
- `sendMailBatch($subject, $body, $recipients)` - Send to multiple recipients

Both functions now use PHPMailer exclusively.

For current documentation, see:
- `config/README.md` - Configuration guide
- `test_phpmailer.php` - Test script for PHPMailer setup
