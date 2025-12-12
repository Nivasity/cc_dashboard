# Configuration Files

This directory contains configuration files that are not tracked by Git for security reasons.

## BREVO Email Service Configuration

**IMPORTANT**: This application uses **BREVO** (formerly Sendinblue) as the email service provider for all email functionality, including the students.php email system.

### What is BREVO?
BREVO is a comprehensive email marketing and transactional email platform that provides:
- SMTP relay service for sending emails
- RESTful API for account management and credit checking
- Credit-based email sending system
- Reliable email delivery infrastructure

### Required Configuration Files

#### 1. BREVO API Configuration (`brevo.php`)

To enable the email functionality with BREVO API credit checking:

1. Copy `brevo.example.php` to `brevo.php`
2. Edit `brevo.php` and replace `YOUR_BREVO_API_KEY_HERE` with your actual Brevo API key
3. Get your API key from: https://app.brevo.com/settings/keys/api

**Example:**

```php
<?php
// BREVO API Key for credit checking and account management
define('BREVO_API_KEY', 'xkeysib-your-actual-api-key-here');
?>
```

**Note:** The `brevo.php` file is ignored by Git to keep your API key secure.

#### 2. SMTP Configuration (`mail.php`)

This file contains BREVO SMTP credentials for sending emails via PHPMailer.

Create `/config/mail.php` with your BREVO SMTP credentials:

```php
<?php
// BREVO SMTP Configuration
// Get these credentials from: https://app.brevo.com/settings/keys/smtp

// BREVO SMTP server address
define('SMTP_HOST', 'smtp-relay.brevo.com');

// Your BREVO SMTP login (usually your BREVO account email)
define('SMTP_USERNAME', 'your-brevo-email@example.com');

// Your BREVO SMTP password (found in SMTP settings, NOT your account password)
define('SMTP_PASSWORD', 'your-brevo-smtp-password');

// BREVO SMTP port (465 for SSL, 587 for TLS)
define('SMTP_PORT', 465);
?>
```

**Note:** The `mail.php` file is also ignored by Git to keep your credentials secure.

### How BREVO is Used in the Application

1. **students.php Email System**: 
   - Uses BREVO SMTP to send emails to students
   - Checks BREVO API credits before bulk email operations
   - Supports single and bulk email sending

2. **Credit Validation**:
   - For bulk emails (>1 recipient), the system checks BREVO credits via API
   - Ensures sufficient credits (required + 1500 buffer) before sending
   - Prevents sending if credits are insufficient

3. **Email Delivery**:
   - All emails are sent through BREVO's SMTP relay
   - Uses PHPMailer library with BREVO SMTP configuration
   - Supports HTML email templates with Nivasity branding

### Getting Started with BREVO

1. Create a BREVO account at https://www.brevo.com
2. Navigate to SMTP & API settings
3. Generate an API key for credit checking
4. Copy your SMTP credentials
5. Configure both `brevo.php` and `mail.php` files

### Troubleshooting

- **"Brevo API key not configured"**: Ensure `config/brevo.php` exists with valid API key
- **"Insufficient Brevo credits"**: Your BREVO account needs more email credits
- **Email not sending**: Verify SMTP credentials in `config/mail.php`
- **API connection failed**: Check that your BREVO API key is valid and active

### Additional Resources

- BREVO Website: https://www.brevo.com
- BREVO API Documentation: https://developers.brevo.com
- SMTP Settings: https://app.brevo.com/settings/keys/smtp
- API Keys: https://app.brevo.com/settings/keys/api

---

**Security Note**: Never commit actual API keys or SMTP passwords to version control. These files are protected by `.gitignore`.
