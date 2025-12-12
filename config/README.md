# Configuration Files

This directory contains configuration files that are not tracked by Git for security reasons.

## Required Configuration Files

### 1. Payment Gateway Configuration (`fw.php`)

This file contains API keys for Flutterwave and Paystack payment gateways used throughout the application.

To enable payment gateway functionality:

1. Copy `fw.example.php` to `fw.php`
2. Edit `fw.php` and replace placeholder values with your actual API keys
3. Get Flutterwave API keys from: https://dashboard.flutterwave.com/settings/apis
4. Get Paystack API keys from: https://dashboard.paystack.com/#/settings/developers

**Example:**

```php
<?php
// Flutterwave API Keys
define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-your-actual-key-here');
define('FLW_SECRET_KEY', 'FLWSECK_TEST-your-actual-key-here');

// Paystack API Keys
define('PAYSTACK_PUBLIC_KEY', 'pk_test_your-actual-key-here');
define('PAYSTACK_SECRET_KEY', 'sk_test_your-actual-key-here');
?>
```

**Note:** The `fw.php` file is ignored by Git to keep your API keys secure.

### 2. BREVO Email Service Configuration (`brevo.php`)

**IMPORTANT**: This application uses **BREVO** (formerly Sendinblue) REST API as the email service provider for all email functionality, including the students.php email system.

#### What is BREVO?
BREVO is a comprehensive email marketing and transactional email platform that provides:
- REST API for sending transactional emails
- Batch email sending (up to 1000 emails per API call)
- RESTful API for account management and credit checking
- Credit-based email sending system
- Reliable email delivery infrastructure

#### BREVO API Configuration (`brevo.php`)

To enable the email functionality with BREVO API:

1. Copy `brevo.example.php` to `brevo.php`
2. Edit `brevo.php` and replace `YOUR_BREVO_API_KEY_HERE` with your actual Brevo API key
3. Get your API key from: https://app.brevo.com/settings/keys/api

**Example:**

```php
<?php
// BREVO API Key for both email sending and credit checking
define('BREVO_API_KEY', 'xkeysib-your-actual-api-key-here');
?>
```

**Note:** The `brevo.php` file is ignored by Git to keep your API key secure.

**SMTP credentials are no longer needed** - the system uses BREVO's REST API instead of SMTP.

### How BREVO is Used in the Application

1. **students.php Email System**: 
   - Uses BREVO REST API to send emails to students
   - Checks BREVO API credits before bulk email operations
   - Supports single and batch email sending (up to 1000 per request)

2. **Credit Validation**:
   - For bulk emails (>1 recipient), the system checks BREVO credits via API
   - Ensures sufficient credits (required + 1500 buffer) before sending
   - Prevents sending if credits are insufficient

3. **Email Delivery**:
   - All emails are sent through BREVO's REST API endpoint: `POST /v3/smtp/email`
   - Supports HTML email templates with Nivasity branding
   - Automatic batching for large recipient lists (splits into groups of 1000)

### API Endpoints Used

1. **Email Sending**: `POST https://api.brevo.com/v3/smtp/email`
   - Send single or batch transactional emails
   - Requires API key authentication
   - Returns 201 on success

2. **Credit Checking**: `GET https://api.brevo.com/v3/account`
   - Retrieve account information and available credits
   - Used before bulk email operations
   - Ensures sufficient credits exist

### Getting Started with BREVO

1. Create a BREVO account at https://www.brevo.com
2. Navigate to Settings → API Keys
3. Generate an API key
4. Configure `config/brevo.php` with your API key
5. That's it! No SMTP configuration needed.

### Troubleshooting

- **"Brevo API key not configured"**: Ensure `config/brevo.php` exists with valid API key
- **"Insufficient Brevo credits"**: Your BREVO account needs more email credits
- **Email not sending**: Check error logs for API response details
- **API connection failed**: Verify that your BREVO API key is valid and active

### Additional Resources

- BREVO Website: https://www.brevo.com
- BREVO API Documentation: https://developers.brevo.com
- Send Transactional Email API: https://developers.brevo.com/docs/send-a-transactional-email
- API Keys: https://app.brevo.com/settings/keys/api

---

**Security Note**: Never commit actual API keys to version control. The `brevo.php` file is protected by `.gitignore`.
