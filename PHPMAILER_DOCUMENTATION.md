# PHPMailer Email System Documentation

## Overview

The Command Center Dashboard uses **PHPMailer** for all email sending operations. This provides a reliable, standards-compliant SMTP email solution without dependencies on third-party email APIs.

## Architecture

### Email System Components

1. **PHPMailer Library**: Located in `config/PHPMailer-master/src/`
2. **Configuration**: `config/mail.php` - SMTP credentials and PHPMailer includes
3. **Email Functions**: `model/mail.php` - Core email sending functions
4. **Email Template**: HTML template with Nivasity branding

### Email Flow

```
Application Code
    ↓
sendMail() or sendMailBatch()
    ↓
createPHPMailer() - Load config, create instance
    ↓
buildEmailTemplate() - Apply Nivasity branding
    ↓
PHPMailer - Connect via SMTP
    ↓
Email Delivered
```

## Configuration

### Setup Steps

1. **Ensure PHPMailer library exists**:
   ```
   config/PHPMailer-master/src/
   ├── PHPMailer.php
   ├── SMTP.php
   └── Exception.php
   ```

2. **Create config/mail.php** from example:
   ```bash
   cp config/mail.example.php config/mail.php
   ```

3. **Edit config/mail.php** with your SMTP credentials:
   ```php
   <?php
   require 'PHPMailer-master/src/PHPMailer.php';
   require 'PHPMailer-master/src/SMTP.php';
   require 'PHPMailer-master/src/Exception.php';

   define('SMTP_HOST', 'mail.yourdomain.com');
   define('SMTP_USERNAME', 'your_email@domain.com');
   define('SMTP_PASSWORD', 'your_password');
   define('SMTP_PORT', 465);

   // Optional overrides
   define('SMTP_FROM_EMAIL', 'contact@nivasity.com');
   define('SMTP_FROM_NAME', 'Nivasity');
   ?>
   ```

### SMTP Port Configuration

| Port | Protocol | Encryption | Use Case |
|------|----------|------------|----------|
| 465 | SMTPS | SSL/TLS (implicit) | Most mail servers, recommended |
| 587 | SMTP | STARTTLS (explicit) | Gmail, Yahoo, Office 365 |
| 25 | SMTP | None | Not recommended (insecure) |

**Port 465** uses immediate SSL/TLS encryption from connection start.
**Port 587** starts unencrypted and upgrades to TLS via STARTTLS command.

## API Usage

### Send Single Email

```php
// Include mail functions
require_once('model/mail.php');

// Send email
$result = sendMail(
    'Email Subject',
    'Email body content (HTML supported)',
    'recipient@example.com'
);

if ($result === "success") {
    echo "Email sent successfully";
} else {
    echo "Email failed to send";
}
```

### Send Batch Emails

```php
// Include mail functions
require_once('model/mail.php');

// Array of recipients
$recipients = [
    'student1@example.com',
    'student2@example.com',
    'student3@example.com'
];

// Send to all recipients
$result = sendMailBatch(
    'Email Subject',
    'Email body content (HTML supported)',
    $recipients
);

echo "Sent: {$result['success_count']}\n";
echo "Failed: {$result['fail_count']}\n";
```

### HTML Email Bodies

The system automatically wraps your content in a branded HTML template:

```php
$emailBody = '
    <h2>Welcome to Nivasity</h2>
    <p>Thank you for joining our platform.</p>
    <p><a href="https://nivasity.com" class="btn">Visit Dashboard</a></p>
';

sendMail('Welcome Email', $emailBody, 'user@example.com');
```

## Functions Reference

### getSMTPConfig()

Retrieves SMTP configuration from config/mail.php.

**Returns**: Array with SMTP settings or null if not configured.

```php
$config = getSMTPConfig();
// Returns: ['host', 'port', 'username', 'password', 'from_email', 'from_name']
```

### createPHPMailer()

Creates and configures a PHPMailer instance.

**Returns**: PHPMailer object or null on error.

```php
$mail = createPHPMailer();
if ($mail) {
    // PHPMailer instance ready to use
}
```

### sendMail($subject, $body, $to)

Sends a single email via PHPMailer SMTP.

**Parameters**:
- `$subject` (string): Email subject line
- `$body` (string): Email body (HTML supported)
- `$to` (string): Recipient email address

**Returns**: "success" or "error"

### sendMailBatch($subject, $body, $recipients)

Sends emails to multiple recipients via PHPMailer SMTP.

**Parameters**:
- `$subject` (string): Email subject line
- `$body` (string): Email body (HTML supported)
- `$recipients` (array): Array of recipient email addresses

**Returns**: Array with 'success_count' and 'fail_count'

### buildEmailTemplate($body)

Wraps content in Nivasity-branded HTML email template.

**Parameters**:
- `$body` (string): Email body content

**Returns**: Complete HTML email

## Testing

### Test Configuration

Run the test script to verify your setup:

```bash
php test_phpmailer.php
```

Expected output:
```
✓ SMTP Configuration loaded successfully
  Host: mail.nivasity.com
  Port: 465
  Connection Type: SSL/TLS (implicit SSL from start)

✓ PHPMailer instance created successfully
  SMTP Host: mail.nivasity.com
  SMTP Port: 465
```

### Test Actual Email Sending

```php
<?php
require_once('model/mail.php');

// Test single email
$result = sendMail(
    'Test Email',
    '<p>This is a test email from PHPMailer.</p>',
    'your-email@example.com'
);

echo $result === "success" ? "✓ Email sent\n" : "✗ Email failed\n";
?>
```

## Troubleshooting

### Connection Errors

**Error**: "SMTP Socket Error"
- **Cause**: Cannot connect to SMTP server
- **Fix**: Check firewall, verify host/port, ensure server allows outbound SMTP

**Error**: "SMTP Error: Could not authenticate"
- **Cause**: Invalid username/password
- **Fix**: Verify SMTP_USERNAME and SMTP_PASSWORD in config/mail.php

### SSL/TLS Errors

**Error**: "SSL operation failed"
- **Cause**: Port/encryption mismatch or outdated OpenSSL
- **Fix**: 
  - Port 465 requires SSL/TLS (no STARTTLS)
  - Port 587 requires STARTTLS
  - Update OpenSSL if needed

**Test SSL connection**:
```bash
# Test SSL (port 465)
openssl s_client -connect mail.nivasity.com:465

# Test STARTTLS (port 587)
openssl s_client -connect mail.nivasity.com:587 -starttls smtp
```

### Gmail Configuration

For Gmail SMTP:
1. Enable 2-Factor Authentication
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use App Password (not your Google password)

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password'); // 16-character app password
define('SMTP_PORT', 587); // Gmail uses STARTTLS
```

### Debug Mode

Enable PHPMailer debug output:

```php
$mail = createPHPMailer();
$mail->SMTPDebug = 2; // Enable verbose debug output
$mail->Debugoutput = 'error_log'; // Send debug to error log
```

Check PHP error log for detailed SMTP conversation.

## Security Best Practices

1. **Never commit config/mail.php** - It's in .gitignore
2. **Use strong passwords** - At least 16 characters
3. **Enable SSL/TLS** - Use port 465 or 587, never port 25
4. **Limit SMTP access** - Use dedicated email account for application
5. **Monitor logs** - Check for unusual sending patterns
6. **Rate limiting** - Implement sending limits to prevent abuse

## Performance

### Batch Sending

For multiple recipients, use `sendMailBatch()`:
- Sends to each recipient individually
- More reliable than BCC for large lists
- Provides per-recipient success/failure tracking

### Recommended Limits

- **Single batch**: Max 100 recipients
- **Delay between batches**: 1-2 seconds
- **Daily limit**: Check your SMTP provider's limits

## Migration from BREVO

If migrating from BREVO:

1. **Remove BREVO config**: Delete or rename `config/brevo.php`
2. **Configure SMTP**: Ensure `config/mail.php` exists with valid credentials
3. **No code changes needed**: Email API remains the same
4. **Test thoroughly**: Run `test_phpmailer.php`

The functions `sendMail()` and `sendMailBatch()` work identically - only the backend changed from BREVO API to PHPMailer SMTP.

## Support

For issues or questions:
- Check error logs: `tail -f /var/log/php-errors.log`
- Run test script: `php test_phpmailer.php`
- Verify SMTP settings with your email provider
- Review PHPMailer documentation: https://github.com/PHPMailer/PHPMailer

---

**Last Updated**: February 2026
**Email System**: PHPMailer SMTP (BREVO removed)
