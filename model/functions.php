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

    if (is_array($details) || is_object($details)) {
        $details = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } elseif ($details !== null) {
        $details = (string) $details;
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

    $stmt->execute();
    $stmt->close();

    return true;
}

?>
