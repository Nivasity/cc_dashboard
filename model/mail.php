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
 * Send email using BREVO REST API
 * 
 * This function sends emails via BREVO's REST API endpoint: POST /v3/smtp/email
 * Uses API key authentication instead of SMTP credentials
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
    
    return $result ? "success" : "error";
}

/**
 * Send batch emails using BREVO REST API
 * 
 * Sends multiple emails in a single API call (max 1000 per request)
 * More efficient for bulk email operations
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
    
    // Split recipients into batches of 1000 (BREVO API limit)
    $batches = array_chunk($recipients, 1000);
    
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
    curl_close($ch);
    
    // BREVO API returns 201 on success
    if ($httpCode === 201) {
        return true;
    } else {
        // Log error for debugging (without sensitive response data)
        error_log("BREVO API error: HTTP status code $httpCode");
        return false;
    }
}

?>
