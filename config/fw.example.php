<?php
// Payment Gateway Configuration Example
// Copy this file to fw.php and add your actual API keys
// 
// Flutterwave API keys can be obtained from: https://dashboard.flutterwave.com/settings/apis
// Paystack API keys can be obtained from: https://dashboard.paystack.com/#/settings/developers

// Flutterwave API Keys
define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-YOUR_PUBLIC_KEY_HERE');
define('FLW_SECRET_KEY', 'FLWSECK_TEST-YOUR_SECRET_KEY_HERE');

// Paystack API Keys
define('PAYSTACK_PUBLIC_KEY', 'pk_test_YOUR_PUBLIC_KEY_HERE');
define('PAYSTACK_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE');

// Shared secret required to trigger cron/daily_school_settlement.php over HTTP.
// Generate a long random value, e.g.: php -r "echo bin2hex(random_bytes(32));"
define('CRON_SETTLEMENT_KEY', 'REPLACE_WITH_A_LONG_RANDOM_SECRET');

?>
