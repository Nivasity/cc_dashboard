# BREVO SMTP Fallback Implementation

## Overview

The email system now automatically falls back to SMTP relay when BREVO subscription credits are low (50 or below). This ensures continuous email delivery even when API credits are depleted.

## How It Works

### Credit-Based Routing

Before sending any email, the system:

1. **Checks BREVO account credits** via API call to `https://api.brevo.com/v3/account`
2. **Evaluates subscription credits**:
   - If credits > 50: Use BREVO REST API (fast, efficient)
   - If credits ≤ 50: Use SMTP relay (reliable fallback)
3. **Logs the decision** for monitoring and debugging

### API Response Structure

The BREVO account API returns information including:

```json
{
  "relay": {
    "enabled": true,
    "data": {
      "userName": "913f62001@smtp-brevo.com",
      "relay": "smtp-relay.brevo.com",
      "port": 587
    }
  },
  "plan": [
    {
      "type": "subscription",
      "credits": 0,
      "creditsType": "sendLimit"
    }
  ]
}
```

### SMTP Configuration

When falling back to SMTP, the system uses:

- **Host**: smtp-relay.brevo.com (from `relay.data.relay`)
- **Port**: 587 (from `relay.data.port`)
- **Username**: From `relay.data.userName` 
- **Password**: BREVO API key
- **Encryption**: TLS 1.2 or TLS 1.3 with STARTTLS
- **Authentication**: LOGIN method

## Implementation Details

### New Functions

#### `getBrevoAccountInfo($apiKey)`
Retrieves complete account information from BREVO API.

**Returns**: Array with account data or null on failure

#### `hasBrevoCredits($apiKey)`
Checks if subscription credits are sufficient.

**Returns**: 
- `true` if credits > 50 (use REST API)
- `false` if credits ≤ 50 (use SMTP)

#### `getBrevoSMTPConfig($apiKey)`
Extracts SMTP relay configuration from account info.

**Returns**: Array with SMTP settings or null if relay is not enabled

#### `sendViaSMTP($subject, $htmlContent, $to, $smtpConfig)`
Wrapper function for SMTP email sending.

**Parameters**:
- `$subject`: Email subject
- `$htmlContent`: HTML email body
- `$to`: Recipient email address
- `$smtpConfig`: SMTP configuration array

**Returns**: `true` on success, `false` on failure

#### `sendViaSMTPSocket($subject, $htmlContent, $to, $from, $fromName, $smtpConfig)`
Low-level socket-based SMTP implementation with TLS support.

**Features**:
- Direct TCP socket connection
- STARTTLS encryption
- TLS 1.2/1.3 only (secure versions)
- AUTH LOGIN authentication
- Proper error handling and logging

### Modified Functions

#### `sendMail($subject, $body, $to)`
**Previous behavior**: Always used BREVO REST API

**New behavior**:
1. Checks credits via `hasBrevoCredits()`
2. If credits > 50: Uses REST API (fast)
3. If credits ≤ 50: Uses SMTP relay (reliable)
4. Logs which method is being used

#### `sendMailBatch($subject, $body, $recipients)`
**Previous behavior**: Always used BREVO REST API with BCC

**New behavior**:
1. Checks credits via `hasBrevoCredits()`
2. If credits > 50: Uses REST API with batching (efficient)
3. If credits ≤ 50: Sends individual emails via SMTP (slower but works)
4. Returns success/failure counts

## Usage Examples

### Single Email
```php
// Include mail functions
require_once('model/mail.php');

// Send email (automatically chooses API or SMTP based on credits)
$result = sendMail(
    "Test Subject",
    "<p>This is a test email</p>",
    "recipient@example.com"
);

if ($result === "success") {
    echo "Email sent successfully";
} else {
    echo "Email failed to send";
}
```

### Batch Email
```php
// Include mail functions
require_once('model/mail.php');

// Send to multiple recipients
$recipients = [
    "user1@example.com",
    "user2@example.com",
    "user3@example.com"
];

$result = sendMailBatch(
    "Batch Email Subject",
    "<p>This is a batch email</p>",
    $recipients
);

echo "Success: " . $result['success_count'] . "\n";
echo "Failed: " . $result['fail_count'] . "\n";
```

## Monitoring and Debugging

### Log Messages

The system logs various events for monitoring:

**Credit Check**:
```
BREVO subscription credits: 0
BREVO credits low or unavailable, using SMTP relay for email to user@example.com
```

**Using REST API**:
```
BREVO subscription credits: 100
Using BREVO REST API for email to user@example.com
```

**SMTP Success**:
```
Email sent successfully via SMTP socket to user@example.com
```

**SMTP Errors**:
```
SMTP Error: Expected 250, got 550 - Mailbox unavailable
Failed to get SMTP configuration
```

### Checking Logs

View PHP error logs to monitor email sending:

```bash
# On most systems
tail -f /var/log/php_errors.log

# Or check Apache logs
tail -f /var/log/apache2/error.log
```

## Performance Considerations

### REST API (When Credits > 50)
- **Speed**: Very fast (single HTTP request)
- **Batch efficiency**: Up to 95 recipients per API call
- **Resource usage**: Minimal
- **Best for**: High-volume sending with available credits

### SMTP Relay (When Credits ≤ 50)
- **Speed**: Slower (TCP connection + SMTP handshake per email)
- **Batch efficiency**: One connection per recipient
- **Resource usage**: More CPU and memory
- **Best for**: Fallback when credits are low

### Recommendations

1. **Monitor Credits**: Regularly check BREVO credits to avoid fallback
2. **Credit Alerts**: Set up monitoring for when credits drop below 100
3. **Batch Timing**: Schedule batch emails during off-peak hours
4. **Test Fallback**: Periodically test SMTP fallback to ensure it works

## Troubleshooting

### Issue: Emails not sending via SMTP

**Check**:
1. BREVO API key is valid
2. SMTP relay is enabled in BREVO account
3. Server can connect to smtp-relay.brevo.com on port 587
4. TLS 1.2 or 1.3 is supported

**Test connection**:
```bash
openssl s_client -connect smtp-relay.brevo.com:587 -starttls smtp
```

### Issue: "Could not get BREVO SMTP relay configuration"

**Cause**: SMTP relay not enabled in BREVO account

**Solution**: 
1. Log into BREVO dashboard
2. Go to Settings → SMTP & API
3. Enable SMTP relay
4. Note the username and configuration

### Issue: TLS connection fails

**Check**:
1. PHP version supports TLS 1.2+ (PHP 7.1+)
2. OpenSSL extension is enabled
3. Server allows outbound connections on port 587

**Verify**:
```bash
php -i | grep "OpenSSL support"
php -i | grep "TLS"
```

### Issue: Authentication failures

**Check**:
1. BREVO API key is correct in config/brevo.php
2. API key has not expired
3. BREVO account is active

## Security Features

### Encryption
- **TLS 1.2 or TLS 1.3 only**: Older, vulnerable versions disabled
- **STARTTLS**: Upgrades connection to encrypted
- **Certificate validation**: Automatic via OpenSSL

### Authentication
- **AUTH LOGIN**: Standard SMTP authentication
- **Credentials**: API key used as password (secure)
- **No plaintext**: All credentials encrypted over TLS

### Error Handling
- **No sensitive data in logs**: Passwords and full credentials not logged
- **Graceful fallback**: If SMTP fails, error is logged but system continues
- **Proper cleanup**: Socket connections always closed

## Testing

### Test Credit Logic

Run the included test script:

```bash
php test_mail_fallback.php
```

**Expected output**:
```
Test 1: Testing BREVO account info retrieval
================================================
Testing with mock API key: test-api-key-12345

Test 2: Testing credit check logic
================================================
Mock account data loaded
Subscription credits: 0
SMTP Relay enabled: Yes
SMTP Host: smtp-relay.brevo.com
SMTP Port: 587
SMTP Username: 913f62001@smtp-brevo.com

Credit check result: PASS - Use SMTP fallback (credits: 0 <= 50)

...

All logic tests completed successfully!
```

### Test Actual Email Sending

Create a test script:

```php
<?php
require_once('model/mail.php');

// Send test email
$result = sendMail(
    "Test Email - SMTP Fallback",
    "<p>This is a test to verify SMTP fallback works correctly.</p>",
    "your-test-email@example.com"
);

echo "Result: " . $result . "\n";
```

Check logs to see which method was used.

## Migration Notes

### No Changes Required

Existing code using `sendMail()` and `sendMailBatch()` will automatically benefit from SMTP fallback. No code changes needed.

### Backward Compatibility

- ✅ Function signatures unchanged
- ✅ Return values unchanged
- ✅ Error handling consistent
- ✅ All existing functionality preserved

## Future Enhancements

Potential improvements for future versions:

1. **Connection Pooling**: Reuse SMTP connections for batch sending
2. **Retry Logic**: Automatic retry with exponential backoff
3. **Credit Threshold**: Make the 50-credit threshold configurable
4. **Multiple Fallbacks**: Support additional SMTP providers
5. **Performance Metrics**: Track API vs SMTP usage statistics
6. **Admin Dashboard**: UI to view email sending status and credits

## Support

For issues or questions:
- Check error logs first
- Review this documentation
- Test with the provided test script
- Contact development team if issue persists

## References

- BREVO API Documentation: https://developers.brevo.com/docs
- BREVO Account API: https://developers.brevo.com/reference/getaccount
- SMTP Relay Guide: https://developers.brevo.com/docs/smtp-setup
- TLS Best Practices: https://www.php.net/manual/en/function.stream-socket-enable-crypto.php
