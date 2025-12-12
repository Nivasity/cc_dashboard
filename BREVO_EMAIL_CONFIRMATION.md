# BREVO Email System Confirmation

## Overview
This document confirms that the **students.php** page and the entire email system in the Command Center Dashboard uses **BREVO** (formerly Sendinblue) REST API for sending emails.

## Email System Architecture

### 1. Frontend: students.php
- **Location**: `/students.php`
- **Feature**: "Email Students" tab (lines 252-343)
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
  - **Line 503-640**: Handles `email_customer` POST request
  - **Line 608-626**: **BREVO Credit Validation**
    - For bulk emails (>1 recipient), checks BREVO API credits before sending
    - Uses `checkBrevoCredits()` function with BREVO_API_KEY
    - Ensures sufficient credits are available (required credits + 1500 buffer)
    - Prevents sending if credits are insufficient
  - **Line 628**: Calls `sendMailBatch()` function to send emails via BREVO REST API

### 3. Email Sending Functions: model/mail.php
- **Location**: `/model/mail.php`
- **BREVO REST API Integration**:
  - **`sendMail()` function**: Sends single email via BREVO REST API
    - Uses API key authentication (no SMTP credentials needed)
    - Endpoint: `POST https://api.brevo.com/v3/smtp/email`
    - Returns "success" or "error"
  - **`sendMailBatch()` function**: Sends batch emails via BREVO REST API
    - Automatically splits recipients into batches of 1000 (BREVO API limit)
    - More efficient for bulk operations
    - Returns success and fail counts
  - **`buildEmailTemplate()` function**: Builds HTML email with Nivasity branding
  - **`sendBrevoAPIRequest()` function**: Handles API communication
    - Returns 201 status code on success
    - Logs errors for debugging

### 4. BREVO Credit Checking: model/functions.php
- **Location**: `/model/functions.php`
- **Function**: `checkBrevoCredits()` (lines 77-147)
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
  - **This is the only configuration file needed**

## BREVO Service Details

### What is BREVO?
BREVO (formerly Sendinblue) is a comprehensive email marketing and transactional email service provider. It offers:
- REST API for sending transactional emails
- Batch email sending (up to 1000 emails per API call)
- RESTful API for account management and credit checking
- Email delivery infrastructure
- Credit-based pricing model

### Why BREVO REST API?
The system uses BREVO REST API for:
1. **Single Authentication**: Only API key needed (no SMTP credentials)
2. **Batch Sending**: Send up to 1000 emails per API call for efficiency
3. **Credit Management**: API-based credit checking prevents overspending
4. **Better Error Handling**: Detailed API responses for troubleshooting
5. **Modern Integration**: RESTful API is easier to maintain than SMTP

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
        → Send emails in batches via BREVO REST API
        → Split into groups of max 1000 per API call
ELSE (single email):
    → Send email via BREVO REST API (no credit check)
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

**That's it!** No SMTP configuration needed.

## Confirmation Summary

✅ **CONFIRMED**: The students.php page email system uses BREVO REST API for:
- Email sending via BREVO REST API (POST /v3/smtp/email)
- Batch email sending (up to 1000 per API call)
- API-based credit validation for bulk emails
- Account management and monitoring

The integration is comprehensive and includes:
- Credit checking before bulk operations
- Batch splitting for large recipient lists
- REST API for actual email sending
- Error handling for insufficient credits
- Audit logging of email activities

## Key Benefits of Using BREVO REST API

1. **Simplified Configuration**: Only API key needed (no SMTP credentials)
2. **Better Performance**: Batch sending reduces API calls
3. **Improved Reliability**: Direct API communication
4. **Enhanced Error Handling**: Detailed API responses
5. **Modern Architecture**: RESTful API is industry standard

---

**Last Updated**: December 2024
**BREVO Service**: https://www.brevo.com
**API Documentation**: https://developers.brevo.com/docs/send-a-transactional-email
