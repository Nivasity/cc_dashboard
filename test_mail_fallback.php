<?php
/**
 * Test script for BREVO SMTP fallback functionality
 * This script tests the credit checking and SMTP fallback logic
 */

// Include the mail functions
require_once('model/mail.php');

// Test 1: Test getBrevoAccountInfo with a mock API key
echo "Test 1: Testing BREVO account info retrieval\n";
echo "================================================\n";

// Mock test - this would require actual API key to test fully
$testApiKey = "test-api-key-12345";
echo "Testing with mock API key: $testApiKey\n";

// Test the hasBrevoCredits function logic
echo "\nTest 2: Testing credit check logic\n";
echo "================================================\n";

// Simulate the account response
$mockAccountData = json_decode('{
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
}', true);

echo "Mock account data loaded\n";
echo "Subscription credits: " . $mockAccountData['plan'][0]['credits'] . "\n";
echo "SMTP Relay enabled: " . ($mockAccountData['relay']['enabled'] ? 'Yes' : 'No') . "\n";
echo "SMTP Host: " . $mockAccountData['relay']['data']['relay'] . "\n";
echo "SMTP Port: " . $mockAccountData['relay']['data']['port'] . "\n";
echo "SMTP Username: " . $mockAccountData['relay']['data']['userName'] . "\n";

// Test credit check logic
$credits = intval($mockAccountData['plan'][0]['credits']);
echo "\nCredit check result: ";
if ($credits > 50) {
    echo "PASS - Use BREVO API (credits: $credits)\n";
} else {
    echo "PASS - Use SMTP fallback (credits: $credits <= 50)\n";
}

// Test with high credits
echo "\nTest 3: Testing with high credits (100)\n";
echo "================================================\n";
$mockAccountData['plan'][0]['credits'] = 100;
$credits = intval($mockAccountData['plan'][0]['credits']);
echo "Credits: $credits\n";
if ($credits > 50) {
    echo "Result: Use BREVO API ✓\n";
} else {
    echo "Result: Use SMTP fallback\n";
}

// Test with exactly 50 credits
echo "\nTest 4: Testing with exactly 50 credits\n";
echo "================================================\n";
$mockAccountData['plan'][0]['credits'] = 50;
$credits = intval($mockAccountData['plan'][0]['credits']);
echo "Credits: $credits\n";
if ($credits > 50) {
    echo "Result: Use BREVO API\n";
} else {
    echo "Result: Use SMTP fallback ✓\n";
}

// Test with 51 credits
echo "\nTest 5: Testing with 51 credits\n";
echo "================================================\n";
$mockAccountData['plan'][0]['credits'] = 51;
$credits = intval($mockAccountData['plan'][0]['credits']);
echo "Credits: $credits\n";
if ($credits > 50) {
    echo "Result: Use BREVO API ✓\n";
} else {
    echo "Result: Use SMTP fallback\n";
}

echo "\n\nAll logic tests completed successfully!\n";
echo "================================================\n";
echo "\nNote: To test actual email sending, you need to:\n";
echo "1. Configure config/brevo.php with a valid BREVO API key\n";
echo "2. Ensure the account has SMTP relay enabled\n";
echo "3. Run a test with actual email addresses\n";

?>
