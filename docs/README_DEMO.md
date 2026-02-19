# Quick Login Demo Setup Guide

This guide provides instructions for developers on how to set up the `demo.php` file on the Nivasity domain to handle quick login functionality.

## Overview

The Quick Login feature allows administrators (roles 1-3) to create temporary login links for students that are valid for 24 hours. These links direct to `https://nivasity.com/demo.php?code={dynamic_code}` and automatically log the student in without requiring manual credential entry.

## Database Setup

First, run the SQL script to create the required table:

```bash
mysql -u your_username -p niverpay_db < sql/quick_login_codes.sql
```

Or execute the SQL manually in your database:

```sql
-- See sql/quick_login_codes.sql for the complete table definition
```

## demo.php Implementation

Create a file at `https://nivasity.com/demo.php` with the following implementation:

```php
<?php
session_start();

// Include database configuration
require_once('path/to/your/config/db.php');

// Get the code from URL parameter
$code = $_GET['code'] ?? '';

if (empty($code)) {
    die('Invalid access. No code provided.');
}

// Sanitize the code
$code = mysqli_real_escape_string($conn, $code);

// Set timezone
date_default_timezone_set('Africa/Lagos');
$now = date('Y-m-d H:i:s');

// Query to fetch the login code details
$query = "SELECT qlc.*, u.email, u.password, u.id as user_id, u.role
          FROM quick_login_codes qlc
          JOIN users u ON qlc.student_id = u.id
          WHERE qlc.code = '$code'
          AND qlc.status = 'active'
          AND qlc.expiry_datetime > '$now'
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    // Invalid or expired code
    die('Invalid or expired login link. Please contact your administrator.');
}

$login_data = mysqli_fetch_assoc($result);

// Mark the code as used
mysqli_query($conn, "UPDATE quick_login_codes SET status = 'used' WHERE code = '$code'");

// Set session variables for the user
$_SESSION['nivas_userId'] = $login_data['user_id'];
$_SESSION['nivas_userEmail'] = $login_data['email'];
$_SESSION['nivas_userRole'] = $login_data['role'];

// Update last login timestamp
mysqli_query($conn, "UPDATE users SET last_login = '$now' WHERE id = " . $login_data['user_id']);

// Redirect to the user dashboard or appropriate page
header('Location: /dashboard.php'); // Adjust this URL to your actual dashboard
exit();
?>
```

## Security Considerations

1. **Code Uniqueness**: Each code is a 64-character hexadecimal string generated using `bin2hex(random_bytes(32))`, ensuring cryptographic randomness.

2. **Expiration**: Links automatically expire after 24 hours. The system checks expiry on every access.

3. **Single Use**: Once a code is used, its status changes to 'used', preventing reuse.

4. **Database Constraints**: Foreign keys ensure data integrity between `quick_login_codes`, `users`, and `admins` tables.

5. **Status Updates**: Background processes should periodically update expired codes:
   ```sql
   UPDATE quick_login_codes 
   SET status = 'expired' 
   WHERE expiry_datetime < NOW() 
   AND status = 'active';
   ```

## File Locations

- **Main Feature Page**: `/quick_login.php`
- **Backend Handler**: `/model/quick_login.php`
- **SQL Schema**: `/sql/quick_login_codes.sql`
- **Demo Handler**: `https://nivasity.com/demo.php` (to be created)

## Configuration Requirements

### Database Connection
Ensure your `demo.php` has access to the same database as the Command Center dashboard:

- Database: `niverpay_db`
- Table: `quick_login_codes`
- Required tables: `users`, `admins`, `schools`, `depts`

### Session Configuration
The demo.php must use compatible session variable names with the main application:

- `$_SESSION['nivas_userId']` - User ID
- `$_SESSION['nivas_userEmail']` - User email
- `$_SESSION['nivas_userRole']` - User role

### Timezone
Always set timezone to `Africa/Lagos` for consistency with the main application.

## Testing the Feature

1. **Access the Quick Login page**: 
   - Log in as admin with role 1, 2, or 3
   - Navigate to: Customer Management > Students > Quick Login

2. **Create a test link**:
   - Click "Create New Login Link"
   - Enter a valid student email
   - Student details should auto-load
   - Click "Create Link"
   - Copy the generated link

3. **Test the link**:
   - Open the link in a new browser/incognito window
   - Verify automatic login occurs
   - Check that the student is redirected appropriately

4. **Verify expiration**:
   - Check that links expire after 24 hours
   - Verify expired links show appropriate error message

5. **Test deletion**:
   - Delete a link from the admin panel
   - Verify the link no longer works

## Troubleshooting

### "Invalid or expired login link"
- Check if the code exists in the database
- Verify the code hasn't expired
- Ensure status is 'active'

### Auto-login not working
- Verify session variables are being set correctly
- Check redirect URL is correct
- Ensure database connection is working

### Permission denied
- Verify user has admin role 1, 2, or 3
- Check session is properly initialized

## Maintenance

### Regular cleanup (recommended cron job)
```sql
-- Mark expired codes
UPDATE quick_login_codes 
SET status = 'expired' 
WHERE expiry_datetime < NOW() 
AND status = 'active';

-- Delete old records (optional, after 30 days)
DELETE FROM quick_login_codes 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Audit logging
All quick login link deletions are automatically logged in the `audit_logs` table for security and compliance.

## Support

For issues or questions, please contact the development team or refer to the main Command Center documentation.
