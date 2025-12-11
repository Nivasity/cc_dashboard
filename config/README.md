# Configuration Files

This directory contains configuration files that are not tracked by Git for security reasons.

## Brevo API Configuration

To enable the email functionality with Brevo API credit checking:

1. Copy `brevo.example.php` to `brevo.php`
2. Edit `brevo.php` and replace `YOUR_BREVO_API_KEY_HERE` with your actual Brevo API key
3. Get your API key from: https://app.brevo.com/settings/keys/api

### Example:

```php
<?php
define('BREVO_API_KEY', 'xkeysib-your-actual-api-key-here');
?>
```

**Note:** The `brevo.php` file is ignored by Git to keep your API key secure.
