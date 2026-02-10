# Configuration Files

This directory contains configuration files that are not tracked by Git for security reasons.

## Required Configuration Files

### 1. Database Configuration (`db.php`)

This file contains database credentials.

To enable database connectivity:

1. Copy `db.example.php` to `db.php`
2. Edit `db.php` and replace placeholder values with your actual credentials
3. Configure database credentials (DB_USERNAME, DB_PASSWORD)

**Example:**

```php
<?php
// Database Credentials
define('DB_USERNAME', 'your_db_username');
define('DB_PASSWORD', 'your_db_password');
?>
```

**Important**: 
- The `db.php` file is ignored by Git to keep your credentials secure

### 2. SMTP Configuration (`mail.php`)

This file contains SMTP server configuration for sending emails via PHPMailer.

To enable email functionality:

1. Copy `mail.example.php` to `mail.php`
2. Edit `mail.php` and replace placeholder values with your actual SMTP credentials

**Example:**

```php
<?php
// PHPMailer library files
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

define('SMTP_HOST', 'mail.nivasity.com');
define('SMTP_USERNAME', 'admin@nivasity.com');
define('SMTP_PASSWORD', 'your_smtp_password');
define('SMTP_PORT', 465);

// Optional overrides
// define('SMTP_FROM_EMAIL', 'contact@nivasity.com');
// define('SMTP_FROM_NAME', 'Nivasity');
?>
```

**Important**: 
- The `mail.php` file is ignored by Git to keep your credentials secure
- All emails are sent via PHPMailer SMTP (no BREVO API dependency)
- PHPMailer library files must be in `PHPMailer-master/src/` directory
- **Port Selection**:
  - Port **465**: Use for SSL/TLS (implicit SSL - secure from start)
  - Port **587**: Use for STARTTLS (explicit TLS - upgrade to secure after connecting)
  - Port **25**: Use for non-encrypted (not recommended)
- For Gmail, use an [App Password](https://support.google.com/accounts/answer/185833) instead of your regular password

### 3. Payment Gateway Configuration (`fw.php`)

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

---

**Security Note**: Never commit actual API keys or passwords to version control. All configuration files with credentials (`db.php`, `mail.php`, `fw.php`) are protected by `.gitignore`.

