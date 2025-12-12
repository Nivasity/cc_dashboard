# BREVO Email System Confirmation

## Overview
This document confirms that the **students.php** page and the entire email system in the Command Center Dashboard uses **BREVO** (formerly Sendinblue) for sending emails.

## Email System Architecture

### 1. Frontend: students.php
- **Location**: `/students.php`
- **Feature**: "Email Students" tab (lines 66-68, 252-343)
- **Functionality**: 
  - Allows administrators to send emails to:
    - Single student
    - All students
    - All HOCs (Heads of Class)
    - All students + HOCs
    - Students of a specific school
    - Students of a specific faculty
    - Students of a specific department
  - Form submission handled via AJAX to `model/support.php`

### 2. Backend Processing: model/support.php
- **Location**: `/model/support.php`
- **BREVO Integration Points**:
  - **Line 8-10**: Includes BREVO API configuration from `config/brevo.php`
  - **Line 503-675**: Handles `email_customer` POST request
  - **Line 606-621**: **BREVO Credit Validation**
    - For bulk emails (>1 recipient), checks BREVO API credits before sending
    - Uses `checkBrevoCredits()` function with BREVO_API_KEY
    - Ensures sufficient credits are available (required credits + 1500 buffer)
    - Prevents sending if credits are insufficient
  - **Line 627**: Calls `sendMail()` function to send emails via BREVO SMTP

### 3. Email Sending Function: model/mail.php
- **Location**: `/model/mail.php`
- **BREVO SMTP Configuration**:
  - **Line 2**: Requires SMTP configuration from `../config/mail.php`
  - **Line 8-146**: `sendMail()` function
  - **Line 119-125**: PHPMailer configured with SMTP settings:
    - `SMTP_HOST`: BREVO's SMTP server (smtp-relay.brevo.com)
    - `SMTP_USERNAME`: BREVO SMTP username
    - `SMTP_PASSWORD`: BREVO SMTP password
    - `SMTP_PORT`: BREVO SMTP port (465 for SSL)
    - `SMTP_SECURE`: TLS encryption

### 4. BREVO Credit Checking: model/functions.php
- **Location**: `/model/functions.php`
- **Function**: `checkBrevoCredits()` (lines 77-129)
- **Functionality**:
  - Connects to BREVO API endpoint: `https://api.brevo.com/v3/account`
  - Uses BREVO_API_KEY for authentication
  - Retrieves available email credits from subscription plan
  - Validates sufficient credits exist before bulk email operations
  - Requires minimum buffer of 1500 credits

### 5. Configuration Files
- **BREVO API Key**: `/config/brevo.php` (gitignored)
  - Contains `BREVO_API_KEY` constant
  - Example template: `/config/brevo.example.php`
  - API key obtained from: https://app.brevo.com/settings/keys/api

- **SMTP Credentials**: `/config/mail.php` (gitignored)
  - Contains BREVO SMTP credentials:
    - `SMTP_HOST`
    - `SMTP_USERNAME`
    - `SMTP_PASSWORD`
    - `SMTP_PORT`

## BREVO Service Details

### What is BREVO?
BREVO (formerly Sendinblue) is a comprehensive email marketing and transactional email service provider. It offers:
- SMTP relay services for sending transactional emails
- RESTful API for account management and credit checking
- Email delivery infrastructure
- Credit-based pricing model

### Why BREVO?
The system uses BREVO for:
1. **Reliable Email Delivery**: Professional SMTP infrastructure
2. **Credit Management**: API-based credit checking prevents overspending
3. **Bulk Email Support**: Handles mass email campaigns to students
4. **Transactional Emails**: Sends individual notifications and messages

## Email Flow in students.php

```
User Action (students.php)
    ↓
Form Submit → AJAX POST to model/support.php
    ↓
Collect recipients based on selection criteria
    ↓
IF bulk email (>1 recipient):
    ↓
    Check BREVO credits via API
    ↓
    IF insufficient credits:
        → Show error message
    ELSE:
        → Send emails via BREVO SMTP
ELSE (single email):
    → Send email via BREVO SMTP (no credit check)
    ↓
Success/Error response
```

## Configuration Required

To use the BREVO email system:

1. **Create `/config/brevo.php`**:
   ```php
   <?php
   define('BREVO_API_KEY', 'xkeysib-your-api-key-here');
   ?>
   ```

2. **Create `/config/mail.php`** with BREVO SMTP credentials:
   ```php
   <?php
   define('SMTP_HOST', 'smtp-relay.brevo.com');
   define('SMTP_USERNAME', 'your-brevo-email@example.com');
   define('SMTP_PASSWORD', 'your-brevo-smtp-password');
   define('SMTP_PORT', 465);
   ?>
   ```

## Confirmation Summary

✅ **CONFIRMED**: The students.php page email system uses BREVO for:
- SMTP email delivery via PHPMailer
- API-based credit validation for bulk emails
- Account management and monitoring

The integration is comprehensive and includes:
- Credit checking before bulk operations
- SMTP relay for actual email sending
- Error handling for insufficient credits
- Audit logging of email activities

---

**Last Updated**: December 2024
**BREVO Service**: https://www.brevo.com
**API Documentation**: https://developers.brevo.com
