<?php
// PHPMailer Email System Configuration
// This file contains email sending functions using PHPMailer SMTP
// Configuration: SMTP credentials and PHPMailer includes are loaded from ../config/mail.php

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
 * Send email using PHPMailer SMTP
 * 
 * @param string $subject The email subject line
 * @param string $body The email body content (HTML supported)
 * @param string $to The recipient email address
 * @return string Returns "success" if email sent successfully, "error" otherwise
 */
function sendMail($subject, $body, $to) {
    $mail = createPHPMailer();
    
    if (!$mail) {
        error_log("Failed to create PHPMailer instance for email to $to");
        return "error";
    }
    
    try {
        // Build email content with template
        $htmlContent = buildEmailTemplate($body);
        
        // Recipients
        $mail->addAddress($to);
        
        // Content
        $mail->Subject = $subject;
        $mail->Body = $htmlContent;
        $mail->AltBody = strip_tags($body); // Plain text alternative
        
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
 * Send batch emails using PHPMailer SMTP
 * 
 * Sends multiple emails individually using PHPMailer.
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
    
    error_log("Sending batch email to " . count($recipients) . " recipients via PHPMailer");
    
    // Send to each recipient individually
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

?>
