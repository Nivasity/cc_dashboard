<?php
/**
 * Test script for PHPMailer SMTP functionality
 * This script tests the SMTP configuration and PHPMailer setup
 */

// Include the mail functions
require_once('model/mail.php');

echo "==============================================\n";
echo "PHPMailer SMTP Configuration Test\n";
echo "==============================================\n\n";

// Test 1: Check if SMTP configuration is available
echo "Test 1: Testing SMTP Configuration from mail.php\n";
echo "================================================\n";

$smtpConfig = getSMTPConfig();
if ($smtpConfig) {
    echo "✓ SMTP Configuration loaded successfully\n";
    echo "  Host: " . $smtpConfig['host'] . "\n";
    echo "  Port: " . $smtpConfig['port'] . "\n";
    
    // Check port and show connection type
    $port = $smtpConfig['port'];
    if ($port == 465) {
        echo "  Connection Type: SSL/TLS (implicit SSL from start)\n";
    } elseif ($port == 587) {
        echo "  Connection Type: STARTTLS (upgrade to TLS after connecting)\n";
    } elseif ($port == 25) {
        echo "  Connection Type: Plain (not encrypted - not recommended)\n";
    } else {
        echo "  Connection Type: Unknown port\n";
    }
    
    echo "  Username: " . $smtpConfig['username'] . "\n";
    echo "  From Email: " . $smtpConfig['from_email'] . "\n";
    echo "  From Name: " . $smtpConfig['from_name'] . "\n";
} else {
    echo "✗ SMTP Configuration not found\n";
    echo "  Please configure config/mail.php with SMTP credentials\n";
}

// Test 2: Test PHPMailer creation
echo "\nTest 2: Testing PHPMailer Instance Creation\n";
echo "================================================\n";

$mail = createPHPMailer();
if ($mail) {
    echo "✓ PHPMailer instance created successfully\n";
    echo "  SMTP Host: " . $mail->Host . "\n";
    echo "  SMTP Port: " . $mail->Port . "\n";
    echo "  SMTP Auth: " . ($mail->SMTPAuth ? 'Enabled' : 'Disabled') . "\n";
    echo "  From Address: " . $mail->From . "\n";
    echo "  From Name: " . $mail->FromName . "\n";
} else {
    echo "✗ Failed to create PHPMailer instance\n";
    echo "  Check SMTP configuration in config/mail.php\n";
}

echo "\n\nAll tests completed!\n";
echo "================================================\n";
echo "\nPHPMailer SMTP Email System:\n";
echo "- Uses PHPMailer library for all email sending\n";
echo "- No BREVO API dependency\n";
echo "- Direct SMTP connection for all emails\n";
echo "\nTo send test emails:\n";
echo "1. Configure config/mail.php with valid SMTP credentials\n";
echo "2. Use sendMail() for single emails\n";
echo "3. Use sendMailBatch() for multiple recipients\n";
echo "\nConnection types supported:\n";
echo "- Port 465: SSL/TLS (implicit SSL - recommended)\n";
echo "- Port 587: STARTTLS (explicit TLS - common for Gmail)\n";

?>
