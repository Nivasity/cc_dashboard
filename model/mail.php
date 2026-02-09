<?php
// BREVO Email System Configuration
// This file contains email sending functions using BREVO (formerly Sendinblue) REST API
// BREVO is the email service provider for all transactional and bulk emails
// Configuration: API key is loaded from ../config/brevo.php

// Include Brevo API configuration
if (file_exists('../config/brevo.php')) {
  require_once('../config/brevo.php');
}

/**
 * Get BREVO API key with validation
 * 
 * @return string|null Returns API key if configured, null otherwise
 */
function getBrevoAPIKey() {
    $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
    
    if (empty($apiKey)) {
        error_log('BREVO API key not configured. Please create config/brevo.php with BREVO_API_KEY constant.');
        return null;
    }
    
    return $apiKey;
}

/**
 * Get BREVO account information including credits and SMTP relay details
 * 
 * @param string $apiKey BREVO API key
 * @return array|null Returns account info array or null on failure
 */
function getBrevoAccountInfo($apiKey) {
    $url = 'https://api.brevo.com/v3/account';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json',
        'api-key: ' . $apiKey
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Failed to get BREVO account info: HTTP $httpCode" . ($curlError ? ", curl_error: $curlError" : ""));
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        error_log("Failed to parse BREVO account response");
        return null;
    }
    
    return $data;
}

/**
 * Check if BREVO subscription credits are sufficient (> 50)
 * 
 * @param string $apiKey BREVO API key
 * @return bool Returns true if credits > 50, false otherwise
 */
function hasBrevoCredits($apiKey) {
    $accountInfo = getBrevoAccountInfo($apiKey);
    
    if (!$accountInfo || !isset($accountInfo['plan'])) {
        error_log("Could not determine BREVO credits, will attempt SMTP fallback");
        return false;
    }
    
    // Find subscription plan credits
    foreach ($accountInfo['plan'] as $plan) {
        if ($plan['type'] === 'subscription' && isset($plan['credits'])) {
            $credits = intval($plan['credits']);
            error_log("BREVO subscription credits: $credits");
            return $credits > 50;
        }
    }
    
    error_log("No subscription plan found in BREVO account");
    return false;
}

/**
 * Get BREVO SMTP relay configuration from account info
 * 
 * @param string $apiKey BREVO API key
 * @return array|null Returns SMTP config array or null on failure
 */
function getBrevoSMTPConfig($apiKey) {
    $accountInfo = getBrevoAccountInfo($apiKey);
    
    if (!$accountInfo || !isset($accountInfo['relay'])) {
        error_log("Could not get BREVO SMTP relay configuration");
        return null;
    }
    
    $relay = $accountInfo['relay'];
    if (!isset($relay['enabled']) || !$relay['enabled']) {
        error_log("BREVO SMTP relay is not enabled");
        return null;
    }
    
    if (!isset($relay['data'])) {
        error_log("BREVO SMTP relay data not available");
        return null;
    }
    
    return array(
        'host' => $relay['data']['relay'] ?? null,
        'port' => $relay['data']['port'] ?? 587,
        'username' => $relay['data']['userName'] ?? null,
        'password' => $apiKey  // BREVO uses API key as SMTP password
    );
}

/**
 * Send email using BREVO REST API or SMTP fallback
 * 
 * This function sends emails via BREVO's REST API endpoint if credits are sufficient (> 50).
 * If credits are low (<= 50), it automatically falls back to SMTP relay.
 * Uses API key authentication for both methods.
 * 
 * @param string $subject The email subject line
 * @param string $body The email body content (HTML supported)
 * @param string $to The recipient email address
 * @return string Returns "success" if email sent successfully, "error" otherwise
 */
function sendMail($subject, $body, $to) {
    // Get BREVO API key
    $apiKey = getBrevoAPIKey();
    
    if (!$apiKey) {
        return "error";
    }
    
    // Build email content with template
    $htmlContent = buildEmailTemplate($body);
    
    // Check if we have sufficient BREVO credits (> 50)
    $useAPI = hasBrevoCredits($apiKey);
    
    if ($useAPI) {
        // Use BREVO REST API
        error_log("Using BREVO REST API for email to $to");
        
        // Prepare API request payload
        $payload = array(
            'sender' => array(
                'name' => 'Nivasity',
                'email' => 'contact@nivasity.com'
            ),
            'to' => array(
                array('email' => $to)
            ),
            'subject' => $subject,
            'htmlContent' => $htmlContent
        );
        
        // Send via BREVO API
        $result = sendBrevoAPIRequest($apiKey, $payload);
    } else {
        // Fallback to SMTP relay
        error_log("BREVO credits low or unavailable, using SMTP relay for email to $to");
        
        $smtpConfig = getBrevoSMTPConfig($apiKey);
        if (!$smtpConfig) {
            error_log("Failed to get SMTP configuration");
            return "error";
        }
        
        $result = sendViaSMTP($subject, $htmlContent, $to, $smtpConfig);
    }
    
    return $result ? "success" : "error";
}

/**
 * Send batch emails using BREVO REST API or SMTP fallback
 * 
 * Sends multiple emails using BREVO API if credits are sufficient (> 50).
 * If credits are low (<= 50), falls back to SMTP relay for each email.
 * More efficient for bulk email operations when API is available.
 * 
 * @param string $subject The email subject line
 * @param string $body The email body content (HTML supported)
 * @param array $recipients Array of recipient email addresses
 * @return array Returns array with 'success_count' and 'fail_count'
 */
function sendMailBatch($subject, $body, $recipients) {
    // Get BREVO API key
    $apiKey = getBrevoAPIKey();
    
    if (!$apiKey) {
        return array('success_count' => 0, 'fail_count' => count($recipients));
    }
    
    // Build email content with template
    $htmlContent = buildEmailTemplate($body);
    
    $successCount = 0;
    $failCount = 0;
    
    // Check if we have sufficient BREVO credits (> 50)
    $useAPI = hasBrevoCredits($apiKey);
    
    if ($useAPI) {
        // Use BREVO REST API for batch sending
        error_log("Using BREVO REST API for batch email to " . count($recipients) . " recipients");
        
        // Split recipients into batches of 95 (to stay under BREVO's 99-recipient limit with 1 in 'to')
        $batches = array_chunk($recipients, 95);
        
        foreach ($batches as $batch) {
            // Prepare BCC array
            $bccArray = array();
            foreach ($batch as $email) {
                $bccArray[] = array('email' => $email);
            }

            // Prepare API request payload
            $payload = array(
                'sender' => array(
                    'name' => 'Nivasity',
                    'email' => 'contact@nivasity.com'
                ),
                'to' => array(
                    array('email' => 'support@nivasity.com')
                ),
                'bcc' => $bccArray,
                'subject' => $subject,
                'htmlContent' => $htmlContent
            );

            // Send via BREVO API
            $result = sendBrevoAPIRequest($apiKey, $payload);

            if ($result) {
                $successCount += count($batch);
            } else {
                $failCount += count($batch);
            }
        }
    } else {
        // Fallback to SMTP relay - send individually
        error_log("BREVO credits low or unavailable, using SMTP relay for batch email to " . count($recipients) . " recipients");
        
        $smtpConfig = getBrevoSMTPConfig($apiKey);
        if (!$smtpConfig) {
            error_log("Failed to get SMTP configuration for batch email");
            return array('success_count' => 0, 'fail_count' => count($recipients));
        }
        
        foreach ($recipients as $recipient) {
            $result = sendViaSMTP($subject, $htmlContent, $recipient, $smtpConfig);
            if ($result) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
    }
    
    return array(
        'success_count' => $successCount,
        'fail_count' => $failCount
    );
}

/**
 * Build HTML email template with Nivasity branding
 * 
 * @param string $body The email body content
 * @return string Complete HTML email template
 */
function buildEmailTemplate($body) {
    $currentYear = date("Y");
    $htmlContent = '
    <html>
    <head>
        <style>
            /* Import Nunito font for supported clients */
            @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap");

            body {
                font-family: "Nunito", Arial, sans-serif;
                background-color: #fff7ec;
                color: #333;
                padding: 0;
                margin: 0;
            }

            .container {
                width: 100%;
                max-width: 600px;
                margin: 40px auto;
                box-sizing: border-box;
                background-color: #fff;
                padding: 20px;
                border: 2px solid #7a3b73;
                border-radius: 8px;
            }

            .header {
                padding-left: 10px;
                padding-top: 10px;
            }

            .header img {
                height: 50px;
            }

            .content {
                padding: 20px;
                font-size: 16px;
                line-height: 1.6;
            }

            .content p {
                margin: 0 0 10px;
            }

            .content ol {
                font-weight: bold;
                color: #7a3b73;
            }

            .btn {
                display: inline-block;
                background-color: #FF9100;
                color: #fff !important;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                text-align: center;
            }

            a {
                color: #FF9100;
            }

            .footer {
                max-width: 600px;
                margin: 0 auto;
                box-sizing: border-box;
                font-size: 15px;
                color: #555555;
                text-align: center;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="header">
                <img src="https://nivasity.com/assets/images/nivasity-main.png" alt="Nivasity">
            </div>
            <div class="content">
                '.$body.'
            </div>
        </div>
        <div class="footer">
            <p>For any feedback or inquiries, get in touch with us at<br>
                <a href="mailto:support@nivasity.com">support@nivasity.com</a> <br> <br>

                Nivasity\'s services are provided by Nivasity Web Services.<br>
                A business duly incorporated under the laws of Nigeria. <br> <br><br>

                Copyright © Nivasity. ' . $currentYear . ' All rights reserved.<br>
        </div>
    </body>

    </html>';
    
    return $htmlContent;
}

/**
 * Send request to BREVO API
 * 
 * @param string $apiKey BREVO API key
 * @param array $payload Request payload
 * @return bool Returns true on success, false on failure
 */
function sendBrevoAPIRequest($apiKey, $payload) {
    $url = 'https://api.brevo.com/v3/smtp/email';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // BREVO API returns 201 on success
    if ($httpCode === 201) {
        return true;
    } else {
        // Log error code and response message for debugging
        $logMsg = "BREVO API error: HTTP status code $httpCode";
        if (!empty($response)) {
            $logMsg .= ", response: $response";
        }
        if (!empty($curlError)) {
            $logMsg .= ", curl_error: $curlError";
        }
        error_log($logMsg);
        return false;
    }
}

/**
 * Send email via SMTP using BREVO relay
 * 
 * @param string $subject Email subject
 * @param string $htmlContent HTML email content
 * @param string $to Recipient email address
 * @param array $smtpConfig SMTP configuration array
 * @return bool Returns true on success, false on failure
 */
function sendViaSMTP($subject, $htmlContent, $to, $smtpConfig) {
    if (!$smtpConfig || !isset($smtpConfig['host'], $smtpConfig['port'], $smtpConfig['username'], $smtpConfig['password'])) {
        error_log("Invalid SMTP configuration");
        return false;
    }
    
    // Build email headers
    $from = 'contact@nivasity.com';
    $fromName = 'Nivasity';
    
    $headers = array();
    $headers[] = "From: $fromName <$from>";
    $headers[] = "Reply-To: $from";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    
    // Configure SMTP settings in php.ini style
    ini_set('SMTP', $smtpConfig['host']);
    ini_set('smtp_port', $smtpConfig['port']);
    
    // For authentication, we'll use stream context if available
    // Note: PHP's mail() function has limited SMTP auth support
    // For production, consider using PHPMailer or SwiftMailer
    
    // Try to use mail() with custom headers
    $result = @mail($to, $subject, $htmlContent, implode("\r\n", $headers));
    
    if (!$result) {
        error_log("Failed to send email via SMTP to $to");
        // If mail() fails, try socket-based SMTP
        return sendViaSMTPSocket($subject, $htmlContent, $to, $from, $fromName, $smtpConfig);
    }
    
    error_log("Email sent successfully via SMTP to $to");
    return true;
}

/**
 * Send email via SMTP using socket connection (fallback method)
 * 
 * @param string $subject Email subject
 * @param string $htmlContent HTML email content
 * @param string $to Recipient email address
 * @param string $from From email address
 * @param string $fromName From name
 * @param array $smtpConfig SMTP configuration
 * @return bool Returns true on success, false on failure
 */
function sendViaSMTPSocket($subject, $htmlContent, $to, $from, $fromName, $smtpConfig) {
    $host = $smtpConfig['host'];
    $port = $smtpConfig['port'];
    $username = $smtpConfig['username'];
    $password = $smtpConfig['password'];
    
    // Connect to SMTP server
    $socket = @fsockopen($host, $port, $errno, $errstr, 30);
    if (!$socket) {
        error_log("SMTP Socket Error: $errno - $errstr");
        return false;
    }
    
    // Helper function to send SMTP command
    $sendCommand = function($command, $expectedCode = 250) use ($socket) {
        fwrite($socket, $command . "\r\n");
        $response = fgets($socket, 515);
        $code = substr($response, 0, 3);
        if ($code != $expectedCode) {
            error_log("SMTP Error: Expected $expectedCode, got $code - $response");
            return false;
        }
        return true;
    };
    
    // Read server greeting
    fgets($socket, 515);
    
    // SMTP conversation
    if (!$sendCommand("EHLO " . $_SERVER['SERVER_NAME'] ?? 'localhost', 250)) {
        fclose($socket);
        return false;
    }
    
    // Start TLS if port 587
    if ($port == 587) {
        if (!$sendCommand("STARTTLS", 220)) {
            fclose($socket);
            return false;
        }
        
        // Enable crypto
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("Failed to enable TLS");
            fclose($socket);
            return false;
        }
        
        // Send EHLO again after STARTTLS
        if (!$sendCommand("EHLO " . $_SERVER['SERVER_NAME'] ?? 'localhost', 250)) {
            fclose($socket);
            return false;
        }
    }
    
    // Authenticate
    if (!$sendCommand("AUTH LOGIN", 334)) {
        fclose($socket);
        return false;
    }
    
    if (!$sendCommand(base64_encode($username), 334)) {
        fclose($socket);
        return false;
    }
    
    if (!$sendCommand(base64_encode($password), 235)) {
        fclose($socket);
        return false;
    }
    
    // Send email
    if (!$sendCommand("MAIL FROM: <$from>", 250)) {
        fclose($socket);
        return false;
    }
    
    if (!$sendCommand("RCPT TO: <$to>", 250)) {
        fclose($socket);
        return false;
    }
    
    if (!$sendCommand("DATA", 354)) {
        fclose($socket);
        return false;
    }
    
    // Build email message
    $message = "From: $fromName <$from>\r\n";
    $message .= "To: <$to>\r\n";
    $message .= "Subject: $subject\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "\r\n";
    $message .= $htmlContent;
    $message .= "\r\n.\r\n";
    
    fwrite($socket, $message);
    $response = fgets($socket, 515);
    $code = substr($response, 0, 3);
    
    if ($code != 250) {
        error_log("SMTP Error sending DATA: $response");
        fclose($socket);
        return false;
    }
    
    // Quit
    $sendCommand("QUIT", 221);
    fclose($socket);
    
    error_log("Email sent successfully via SMTP socket to $to");
    return true;
}

?>
