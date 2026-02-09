<?php
// Fund Request Handler
// Processes fund request form submissions and sends email notifications

header('Content-Type: application/json');

// Include database config and mail functions
require_once('config.php');
require_once('mail.php');

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'An error occurred'
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit();
}

// Validate required fields
$required_fields = ['fullName', 'email', 'phone', 'amountRequested', 'purpose'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        $response['message'] = 'All required fields must be filled';
        echo json_encode($response);
        exit();
    }
}

// Sanitize and validate input
$full_name = mysqli_real_escape_string($conn, trim($_POST['fullName']));
$email = mysqli_real_escape_string($conn, trim($_POST['email']));
$phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
$organization = isset($_POST['organization']) ? mysqli_real_escape_string($conn, trim($_POST['organization'])) : null;
$amount_requested = floatval($_POST['amountRequested']);
$purpose = mysqli_real_escape_string($conn, trim($_POST['purpose']));
$additional_details = isset($_POST['additionalDetails']) ? mysqli_real_escape_string($conn, trim($_POST['additionalDetails'])) : null;

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email address';
    echo json_encode($response);
    exit();
}

// Validate amount
if ($amount_requested <= 0) {
    $response['message'] = 'Amount must be greater than zero';
    echo json_encode($response);
    exit();
}

// Insert into database
$insert_query = "INSERT INTO fund_requests 
    (full_name, email, phone, organization, amount_requested, purpose, additional_details, status, created_at) 
    VALUES 
    ('$full_name', '$email', '$phone', " . ($organization ? "'$organization'" : "NULL") . ", $amount_requested, '$purpose', " . ($additional_details ? "'$additional_details'" : "NULL") . ", 'pending', NOW())";

if (mysqli_query($conn, $insert_query)) {
    $request_id = mysqli_insert_id($conn);
    
    // Prepare email content
    $formatted_amount = number_format($amount_requested, 2);
    $current_date = date('F j, Y g:i A');
    
    $email_body = "
        <h2>New Fund Request Submission</h2>
        <p>A new fund request has been submitted through the Nivasity Management Fund Request form.</p>
        
        <h3>Request Details:</h3>
        <table style='border-collapse: collapse; width: 100%; max-width: 600px;'>
            <tr style='background-color: #f8f9fa;'>
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Request ID:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>#$request_id</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Full Name:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>$full_name</td>
            </tr>
            <tr style='background-color: #f8f9fa;'>
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Email:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>$email</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Phone:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>$phone</td>
            </tr>
            " . ($organization ? "
            <tr style='background-color: #f8f9fa;'>
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Organization:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>$organization</td>
            </tr>
            " : "") . "
            <tr" . (!$organization ? " style='background-color: #f8f9fa;'" : "") . ">
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Amount Requested:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>NGN $formatted_amount</td>
            </tr>
            <tr" . ($organization ? "" : " style='background-color: #f8f9fa;'") . ">
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Purpose:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>$purpose</td>
            </tr>
            " . ($additional_details ? "
            <tr" . (!$organization ? "" : " style='background-color: #f8f9fa;'") . ">
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Additional Details:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>$additional_details</td>
            </tr>
            " : "") . "
            <tr" . ($additional_details ? ($organization ? "" : " style='background-color: #f8f9fa;'") : (!$organization ? " style='background-color: #f8f9fa;'" : "")) . ">
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>Submitted:</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>$current_date</td>
            </tr>
        </table>
        
        <p style='margin-top: 20px;'><strong>Next Steps:</strong></p>
        <p>Please review this request and follow up with the applicant at the provided contact information.</p>
    ";
    
    // Send email to finance@nivasity.com with CC
    $subject = "New Fund Request - $full_name (NGN $formatted_amount)";
    
    // Use custom email function with CC support
    $result = sendFundRequestEmail($subject, $email_body, $email, $full_name);
    
    if ($result) {
        $response['status'] = 'success';
        $response['message'] = 'Your fund request has been submitted successfully';
    } else {
        // Even if email fails, request is saved in database
        $response['status'] = 'success';
        $response['message'] = 'Your fund request has been submitted successfully';
        error_log("Fund request #$request_id saved but email notification failed");
    }
} else {
    $response['message'] = 'Database error: ' . mysqli_error($conn);
    error_log("Fund request insertion failed: " . mysqli_error($conn));
}

echo json_encode($response);

/**
 * Send fund request email with CC to finance team
 * 
 * @param string $subject Email subject
 * @param string $body Email body content
 * @param string $applicant_email Applicant's email address
 * @param string $applicant_name Applicant's name
 * @return bool Success status
 */
function sendFundRequestEmail($subject, $body, $applicant_email, $applicant_name) {
    // Get BREVO API key
    $apiKey = getBrevoAPIKey();
    
    if (!$apiKey) {
        error_log('BREVO API key not configured for fund request email');
        return false;
    }
    
    // Build email content with template
    $htmlContent = buildEmailTemplate($body);
    
    // Recipients - primary to finance@nivasity.com
    // CC to samuel@nivasity.com, samuel.cf@nivasity.com, blessing.cf@nivasity.com
    $payload = array(
        'sender' => array(
            'name' => 'Nivasity Fund Requests',
            'email' => 'contact@nivasity.com'
        ),
        'to' => array(
            array('email' => 'finance@nivasity.com', 'name' => 'Finance Team')
        ),
        'cc' => array(
            array('email' => 'samuel@nivasity.com', 'name' => 'Samuel'),
            array('email' => 'samuel.cf@nivasity.com', 'name' => 'Samuel CF'),
            array('email' => 'blessing.cf@nivasity.com', 'name' => 'Blessing CF')
        ),
        'replyTo' => array(
            'email' => $applicant_email,
            'name' => $applicant_name
        ),
        'subject' => $subject,
        'htmlContent' => $htmlContent
    );
    
    // Send via BREVO API
    $result = sendBrevoAPIRequest($apiKey, $payload);
    
    return $result;
}
?>
