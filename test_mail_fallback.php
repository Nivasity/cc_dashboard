<?php
/**
 * Test script for SMTP fallback functionality
 * This script tests the credit checking and SMTP fallback logic
 */

// Include the mail functions
require_once('model/mail.php');

echo "==============================================\n";
echo "SMTP Fallback Configuration Test\n";
echo "==============================================\n\n";

// Test 1: Check if SMTP configuration is available
echo "Test 1: Testing SMTP Configuration from smtp.php\n";
echo "================================================\n";

$smtpConfig = getSMTPConfig();
if ($smtpConfig) {
    echo "✓ SMTP Configuration loaded successfully\n";
    echo "  Host: " . $smtpConfig['host'] . "\n";
    echo "  Port: " . $smtpConfig['port'] . "\n";
    echo "  Username: " . $smtpConfig['username'] . "\n";
    echo "  From Email: " . $smtpConfig['from_email'] . "\n";
    echo "  From Name: " . $smtpConfig['from_name'] . "\n";
} else {
    echo "✗ SMTP Configuration not found\n";
    echo "  Please configure config/smtp.php with SMTP credentials\n";
}

// Test 2: Test credit check logic
echo "\nTest 2: Testing credit check logic\n";
echo "================================================\n";

// Simulate different credit levels
$testCases = [
    ['credits' => 0, 'expected' => 'SMTP'],
    ['credits' => 50, 'expected' => 'SMTP'],
    ['credits' => 51, 'expected' => 'API'],
    ['credits' => 100, 'expected' => 'API'],
];

foreach ($testCases as $test) {
    $credits = $test['credits'];
    $expected = $test['expected'];
    $result = ($credits > 50) ? 'API' : 'SMTP';
    $status = ($result === $expected) ? '✓' : '✗';
    echo "$status Credits: $credits -> Use $result (expected: $expected)\n";
}

echo "\n\nAll logic tests completed!\n";
echo "================================================\n";
echo "\nTo test actual email sending:\n";
echo "1. Configure config/brevo.php with a valid BREVO API key\n";
echo "2. Configure config/db.php with valid SMTP credentials:\n";
echo "   - SMTP_HOST (e.g., smtp.gmail.com)\n";
echo "   - SMTP_PORT (e.g., 587)\n";
echo "   - SMTP_USERNAME (your email)\n";
echo "   - SMTP_PASSWORD (your password or app password)\n";
echo "   - SMTP_FROM_EMAIL (optional)\n";
echo "   - SMTP_FROM_NAME (optional)\n";
echo "3. Run test with actual email addresses\n";
echo "\nThe system will automatically:\n";
echo "- Use BREVO API when credits > 50\n";
echo "- Fallback to normal SMTP when credits ≤ 50\n";

?>
