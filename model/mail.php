<?php
// Email System Configuration
// This file contains email sending functions using BREVO REST API with PHPMailer SMTP fallback
// Primary: BREVO REST API (when credits available)
// Fallback: PHPMailer SMTP (when BREVO credits low or unavailable)

// Include Brevo API configuration
if (file_exists(__DIR__ . '/../config/brevo.php')) {
  require_once(__DIR__ . '/../config/brevo.php');
}

// Include database configuration
if (file_exists(__DIR__ . '/../config/db.php')) {
  require_once(__DIR__ . '/../config/db.php');
}

// Include SMTP configuration (this also loads PHPMailer classes)
if (file_exists(__DIR__ . '/../config/mail.php')) {
  require_once(__DIR__ . '/../config/mail.php');
}

// Use PHPMailer classes (loaded from config/mail.php)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Get BREVO API key with validation
 * 
 * @return string|null Returns API key if configured, null otherwise
 */
function getBrevoAPIKey() {
    $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
    
    if (empty($apiKey)) {
        error_log('BREVO API key not configured. Will use PHPMailer SMTP fallback.');
        return null;
    }
    
    return $apiKey;
}

/**
 * Get BREVO account information including credits
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
        error_log("Could not determine BREVO credits, will use PHPMailer SMTP fallback");
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
 * Get SMTP configuration from mail.php
 * 
 * @return array|null Returns SMTP config array or null if not configured
 */
function getSMTPConfig() {
    // Check if all required SMTP constants are defined
    if (!defined('SMTP_HOST') || !defined('SMTP_PORT') || 
        !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD')) {
        error_log("SMTP credentials not configured in config/mail.php");
        return null;
    }
    
    return array(
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'from_email' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'contact@nivasity.com',
        'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Nivasity'
    );
}

/**
 * Create and configure PHPMailer instance
 * 
 * @return PHPMailer|null Returns configured PHPMailer instance or null on error
 */
function createPHPMailer() {
    $smtpConfig = getSMTPConfig();
    
    if (!$smtpConfig) {
        error_log("Cannot create PHPMailer: SMTP configuration not available");
        return null;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->Port = $smtpConfig['port'];
        
        // Set encryption based on port
        if ($smtpConfig['port'] == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        } elseif ($smtpConfig['port'] == 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
        }
        
        // Set default from address
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        
        // Content type
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        return $mail;
        
    } catch (Exception $e) {
        error_log("PHPMailer configuration error: " . $e->getMessage());
        return null;
    }
}

/**
 * Send email using BREVO REST API or PHPMailer SMTP fallback
 * 
 * This function sends emails via BREVO's REST API endpoint if credits are sufficient (> 50).
 * If credits are low (<= 50), it automatically falls back to PHPMailer SMTP.
 * 
 * @param string $subject The email subject line
 * @param string $body The email body content (HTML supported)
 * @param string $to The recipient email address
 * @return string Returns "success" if email sent successfully, "error" otherwise
 */
function sendMail($subject, $body, $to) {
    // Build email content with template
    $htmlContent = buildEmailTemplate($body);
    
    // Try BREVO API first if configured
    $apiKey = getBrevoAPIKey();
    
    if ($apiKey && hasBrevoCredits($apiKey)) {
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
        
        if ($result) {
            return "success";
        }
        
        // If BREVO API failed, fall through to PHPMailer
        error_log("BREVO API failed, falling back to PHPMailer for email to $to");
    } else {
        // No BREVO credits or API key, use PHPMailer
        error_log("BREVO credits low or unavailable, using PHPMailer SMTP for email to $to");
    }
    
    // Fallback to PHPMailer SMTP
    $mail = createPHPMailer();
    
    if (!$mail) {
        error_log("Failed to create PHPMailer instance for email to $to");
        return "error";
    }
    
    try {
        // Recipients
        $mail->addAddress($to);
        
        // Content
        $mail->Subject = $subject;
        $mail->Body = $htmlContent;
        $mail->AltBody = strip_tags($body);
        
        // Send email
        $mail->send();
        error_log("Email sent successfully via PHPMailer to $to");
        return "success";
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: Failed to send email to $to - " . $mail->ErrorInfo);
        return "error";
    }
}

/**
 * Send batch emails using BREVO REST API or PHPMailer SMTP fallback
 * 
 * Sends multiple emails using BREVO API if credits are sufficient (> 50).
 * If credits are low (<= 50), falls back to PHPMailer SMTP for each email.
 * 
 * @param string $subject The email subject line
 * @param string $body The email body content (HTML supported)
 * @param array $recipients Array of recipient email addresses
 * @return array Returns array with 'success_count' and 'fail_count'
 */
function sendMailBatch($subject, $body, $recipients) {
    if (empty($recipients) || !is_array($recipients)) {
        return array('success_count' => 0, 'fail_count' => 0);
    }
    
    // Build email content with template once
    $htmlContent = buildEmailTemplate($body);
    
    $successCount = 0;
    $failCount = 0;
    
    // Try BREVO API first if configured
    $apiKey = getBrevoAPIKey();
    
    if ($apiKey && hasBrevoCredits($apiKey)) {
        // Use BREVO REST API for batch sending
        error_log("Using BREVO REST API for batch email to " . count($recipients) . " recipients");
        
        // Split recipients into batches of 95 (to stay under BREVO's 99-recipient limit)
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
        // BREVO credits low or unavailable, use PHPMailer SMTP
        error_log("BREVO credits low or unavailable, using PHPMailer SMTP for batch email to " . count($recipients) . " recipients");
        
        // Send to each recipient individually using PHPMailer
        foreach ($recipients as $recipient) {
            $mail = createPHPMailer();
            
            if (!$mail) {
                $failCount++;
                continue;
            }
            
            try {
                // Recipients
                $mail->addAddress($recipient);
                
                // Content
                $mail->Subject = $subject;
                $mail->Body = $htmlContent;
                $mail->AltBody = strip_tags($body);
                
                // Send email
                $mail->send();
                $successCount++;
                
            } catch (Exception $e) {
                error_log("PHPMailer Error: Failed to send batch email to $recipient - " . $mail->ErrorInfo);
                $failCount++;
            }
            
            // Clear addresses for next iteration
            $mail->clearAddresses();
        }
    }
    
    error_log("Batch email complete: $successCount sent, $failCount failed");
    
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
                <img src="https://nivasity.com/nivasity.png" alt="Nivasity">
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

?>
