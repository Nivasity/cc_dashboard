<?php
function generateVerificationCode($length)
{
    // Generate a random verification code of the specified length
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function isCodeUnique($code, $conn, $db_table)
{
    // Check if the code already exists in the table
    $query = "SELECT COUNT(*) as count FROM $db_table WHERE code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();

    return $count == 0; // If count is 0, the code is unique
}

function audit_log_empty_object()
{
    return new stdClass();
}

function audit_log_is_list(array $value)
{
    if ($value === []) {
        return true;
    }

    return array_keys($value) === range(0, count($value) - 1);
}

function normalize_audit_log_value($value, $fallbackKey = 'value')
{
    if ($value === null) {
        return audit_log_empty_object();
    }

    if (is_array($value)) {
        return $value === [] ? audit_log_empty_object() : $value;
    }

    if (is_object($value)) {
        return get_object_vars($value) === [] ? audit_log_empty_object() : $value;
    }

    return [$fallbackKey => $value];
}

function normalize_audit_log_details($details)
{
    if (is_string($details)) {
        $trimmed = trim($details);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $details = $decoded;
            }
        }
    }

    $before = null;
    $after = null;
    $metadata = [];

    if (is_object($details)) {
        $details = get_object_vars($details);
    }

    if (is_array($details)) {
        $hasBefore = array_key_exists('before', $details) || array_key_exists('former', $details);
        $hasAfter = array_key_exists('after', $details);

        if ($hasBefore) {
            $before = array_key_exists('before', $details) ? $details['before'] : $details['former'];
        }

        if ($hasAfter) {
            $after = $details['after'];
        }

        $metadata = $details;
        unset($metadata['before'], $metadata['former'], $metadata['after']);

        if (!$hasBefore && !$hasAfter) {
            $after = $details;
            $metadata = [];
        } elseif ($metadata !== []) {
            if ($after === null) {
                $after = $metadata;
            } elseif (is_array($after) && !audit_log_is_list($after)) {
                $after['_audit_meta'] = $metadata;
            } else {
                $after = [
                    'state' => normalize_audit_log_value($after),
                    '_audit_meta' => $metadata,
                ];
            }
        }
    } elseif ($details !== null) {
        $after = ['message' => (string) $details];
    }

    return [
        'before' => normalize_audit_log_value($before),
        'after' => normalize_audit_log_value($after),
    ];
}

function log_audit_event($conn, $admin_id, $action, $entity_type, $entity_id = null, $details = null)
{
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $admin_id = (int) $admin_id;
    $action = trim((string) $action);
    $entity_type = trim((string) $entity_type);

    if ($admin_id <= 0 || $action === '' || $entity_type === '') {
        return false;
    }

    $details = json_encode(
        normalize_audit_log_details($details),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($details === false) {
        return false;
    }

    $entity_id_param = $entity_id !== null ? (string) $entity_id : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $conn->prepare(
        'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'issssss',
        $admin_id,
        $action,
        $entity_type,
        $entity_id_param,
        $details,
        $ip_address,
        $user_agent
    );

    $ok = $stmt->execute();
    $stmt->close();

    return (bool) $ok;
}

/**
 * Check BREVO API Credits Before Sending Bulk Emails
 * 
 * This function validates that the BREVO account has sufficient email credits
 * before attempting to send bulk emails. It connects to BREVO's API v3 to 
 * retrieve the current credit balance and ensures adequate credits are available.
 * 
 * BREVO (formerly Sendinblue) uses a credit-based system for email sending.
 * Each email sent consumes credits from your account balance.
 * 
 * @param string $apiKey The BREVO API key (from config/brevo.php)
 * @param int $requiredCredits Number of credits needed for the operation
 * @return array Returns array with 'success' boolean and 'message' string
 *               On success: includes 'available_credits' and 'required_credits'
 *               On failure: includes error message explaining the issue
 */
function checkBrevoCredits($apiKey, $requiredCredits = 0) {
    // BREVO API v3 endpoint for account information
    $url = 'https://api.brevo.com/v3/account';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json',
        'api-key: ' . $apiKey  // BREVO API authentication
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return array('success' => false, 'message' => 'Failed to connect to Brevo API');
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['plan'])) {
        return array('success' => false, 'message' => 'Invalid API response from BREVO');
    }
    
    // Find subscription plan and extract available email credits from BREVO account
    $availableCredits = 0;
    foreach ($data['plan'] as $plan) {
        if ($plan['type'] === 'subscription' && isset($plan['credits'])) {
            $availableCredits = intval($plan['credits']);
            break;
        }
    }
    
    // Check if we have enough BREVO credits plus 1500 buffer
    // Buffer ensures account doesn't run completely out of credits
    $minRequired = $requiredCredits + 1500;
    
    if ($availableCredits < $minRequired) {
        return array(
            'success' => false, 
            'message' => "Insufficient Brevo credits. Available: $availableCredits, Required: $minRequired (including 1500 buffer)",
            'available_credits' => $availableCredits,
            'required_credits' => $minRequired
        );
    }
    
    return array(
        'success' => true, 
        'message' => 'Sufficient credits available',
        'available_credits' => $availableCredits,
        'required_credits' => $minRequired
    );
}

?>
