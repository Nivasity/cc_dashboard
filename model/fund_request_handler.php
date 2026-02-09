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
$full_name = trim($_POST['fullName']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$organization = isset($_POST['organization']) ? trim($_POST['organization']) : null;
$amount_requested = floatval($_POST['amountRequested']);
$purpose = trim($_POST['purpose']);
$additional_details = isset($_POST['additionalDetails']) ? trim($_POST['additionalDetails']) : null;

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

// Insert into database using prepared statement
$stmt = mysqli_prepare($conn, "INSERT INTO fund_requests 
    (full_name, email, phone, organization, amount_requested, purpose, additional_details, status, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

mysqli_stmt_bind_param($stmt, "ssssdss", $full_name, $email, $phone, $organization, $amount_requested, $purpose, $additional_details);

if (mysqli_stmt_execute($stmt)) {
    $request_id = mysqli_insert_id($conn);
    
    // Prepare email content with proper HTML escaping
    $formatted_amount = number_format($amount_requested, 2);
    $current_date = date('F j, Y g:i A');
    
    // Escape all user input for HTML context
    $safe_full_name = htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8');
    $safe_email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safe_phone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    $safe_organization = $organization ? htmlspecialchars($organization, ENT_QUOTES, 'UTF-8') : null;
    $safe_purpose = htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8');
    $safe_additional_details = $additional_details ? htmlspecialchars($additional_details, ENT_QUOTES, 'UTF-8') : null;
    
    // Build email table rows
    $rows = [
        ['Request ID:', "#$request_id"],
        ['Full Name:', $safe_full_name],
        ['Email:', $safe_email],
        ['Phone:', $safe_phone]
    ];
    
    if ($safe_organization) {
        $rows[] = ['Organization:', $safe_organization];
    }
    
    $rows[] = ['Amount Requested:', "NGN $formatted_amount"];
    $rows[] = ['Purpose:', nl2br($safe_purpose)];
    
    if ($safe_additional_details) {
        $rows[] = ['Additional Details:', nl2br($safe_additional_details)];
    }
    
    $rows[] = ['Submitted:', $current_date];
    
    // Generate table rows with alternating colors
    $table_rows = '';
    foreach ($rows as $index => $row) {
        $bg_color = $index % 2 === 0 ? " style='background-color: #f8f9fa;'" : "";
        $table_rows .= "
            <tr$bg_color>
                <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold;'>{$row[0]}</td>
                <td style='padding: 10px; border: 1px solid #dee2e6;'>{$row[1]}</td>
            </tr>";
    }
    
    $email_body = "
        <h2>New Fund Request Submission</h2>
        <p>A new fund request has been submitted through the Nivasity Management Fund Request form.</p>
        
        <h3>Request Details:</h3>
        <table style='border-collapse: collapse; width: 100%; max-width: 600px;'>
            $table_rows
        </table>
        
        <p style='margin-top: 20px;'><strong>Next Steps:</strong></p>
        <p>Please review this request and follow up with the applicant at the provided contact information.</p>
    ";
    
    // Send email to finance@nivasity.com with CC
    $subject = "New Fund Request - $safe_full_name (NGN $formatted_amount)";
    
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
    $response['message'] = 'Failed to save fund request. Please try again.';
    error_log("Fund request insertion failed: " . mysqli_error($conn));
}

mysqli_stmt_close($stmt);

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
