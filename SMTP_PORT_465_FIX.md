# SMTP Port 465 Fix - Resolution Guide

## Problem

You were experiencing the following error when sending emails via SMTP fallback:

```
[10-Feb-2026 09:36:49 Africa/Lagos] BREVO subscription credits: 0
[10-Feb-2026 09:36:49 Africa/Lagos] BREVO credits low or unavailable, using normal SMTP for email to akinyemisamuel170@gmail.com
[10-Feb-2026 09:37:49 Africa/Lagos] SMTP Error: Expected 250, got  -
```

## Root Cause

The error `Expected 250, got -` indicates that the SMTP server did not respond as expected. The issue occurred because:

1. Your SMTP configuration uses **port 465** (as shown in `config/mail.example.php`)
2. Port 465 requires **implicit SSL/TLS** - an encrypted connection from the very start
3. The previous code only handled port 587 with **STARTTLS** (which starts as plain text and upgrades to encrypted)
4. When the code tried to connect to port 465 without SSL, the server expected an SSL handshake but received plain SMTP commands
5. This caused the server to not respond properly, resulting in an empty response ("-")

## Solution

The code has been updated to automatically detect and handle both port types:

### Port 465 (SSL/TLS - Implicit SSL)
- **Connection**: `ssl://mail.nivasity.com:465`
- **How it works**: SSL/TLS encryption is established immediately upon connection
- **Use case**: Most mail servers, including mail.nivasity.com
- **Recommended**: Yes, for better security

### Port 587 (STARTTLS - Explicit TLS)
- **Connection**: `mail.nivasity.com:587`
- **How it works**: Starts as plain connection, then upgrades to encrypted using STARTTLS command
- **Use case**: Gmail, Yahoo, and other public mail services
- **Recommended**: Yes, widely supported

## Changes Made

### 1. model/mail.php - `sendViaSMTPSocket()` function

**Before:**
```php
// Connect to SMTP server
$socket = @fsockopen($host, $port, $errno, $errstr, 30);
```

**After:**
```php
// Determine if we need SSL (port 465) or plain connection (port 587, 25)
// Port 465 requires implicit SSL (SSL from the start)
// Port 587 uses STARTTLS (upgrade to TLS after connecting)
$useSSL = ($port == 465);
$connectionString = $useSSL ? "ssl://$host" : $host;

// Connect to SMTP server
$socket = @fsockopen($connectionString, $port, $errno, $errstr, 30);
```

### 2. Enhanced Error Handling

Added detection for empty responses:

```php
// Handle empty or invalid response
if ($response === false || trim($response) === '') {
    error_log("SMTP Error: No response received for command: $command");
    return false;
}
```

### 3. Improved Logging

Better error messages that show the actual connection string:

```php
error_log("SMTP Socket Error: $errno - $errstr (connecting to $connectionString:$port)");
```

## What This Means For You

Your email system will now work correctly with:

✅ **Port 465** - SSL/TLS (your current configuration)
✅ **Port 587** - STARTTLS (if you switch to Gmail or similar)
✅ **Better error messages** - Easier to debug connection issues
✅ **Empty response detection** - Catches connection failures immediately

## Testing

You can verify the fix by running:

```bash
php test_mail_fallback.php
```

The test will show:
- Your current SMTP configuration
- The connection type being used (SSL/TLS or STARTTLS)
- Port validation

## Next Steps

1. **No action needed** - The fix is already applied
2. Your SMTP configuration in `config/mail.php` should now work correctly with port 465
3. Emails will be sent via SMTP when BREVO credits are low (≤ 50)

## Troubleshooting

If you still experience issues:

### Check SSL Support
```bash
php -i | grep "OpenSSL support"
```

### Test SSL Connection
```bash
openssl s_client -connect mail.nivasity.com:465
```

### Verify Firewall
Ensure your server allows outbound connections on port 465

### Common Issues

**Issue**: Connection timeout
- **Solution**: Check firewall rules for port 465

**Issue**: SSL handshake failed
- **Solution**: Verify OpenSSL is installed and up to date

**Issue**: Authentication failed
- **Solution**: Verify SMTP username and password in `config/mail.php`

## Configuration Reference

Your `config/mail.php` should look like this:

```php
<?php
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

define('SMTP_HOST', 'mail.nivasity.com');
define('SMTP_USERNAME', 'admin@nivasity.com');
define('SMTP_PASSWORD', 'your_actual_password');
define('SMTP_PORT', 465);  // SSL/TLS - encrypted from start
?>
```

## Summary

The SMTP error has been fixed by adding proper SSL support for port 465. Your email system will now:
- Automatically use SSL for port 465
- Automatically use STARTTLS for port 587
- Provide better error messages for debugging
- Handle connection failures gracefully

The fix is backward compatible and requires no changes to your configuration.
