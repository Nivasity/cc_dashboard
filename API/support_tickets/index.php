<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/model/functions.php';
require_once dirname(__DIR__, 2) . '/model/mail.php';
require_once dirname(__DIR__, 2) . '/model/notification_helpers.php';

if (!defined('DB_USERNAME') || !defined('DB_PASSWORD')) {
    respond(500, ['success' => false, 'message' => 'Database credentials are not configured.']);
}

if (!defined('API_BEARER_TOKEN') || trim((string) API_BEARER_TOKEN) === '') {
    respond(500, ['success' => false, 'message' => 'API_BEARER_TOKEN is not configured in config/db.php.']);
}

if (!defined('API_ADMIN_ID') || (int) API_ADMIN_ID <= 0) {
    respond(500, ['success' => false, 'message' => 'API_ADMIN_ID is not configured in config/db.php.']);
}

authorizeRequest((string) API_BEARER_TOKEN);

$dbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? (string) DB_NAME : 'niverpay_db';
$apiAdminId = (int) API_ADMIN_ID;

$conn = mysqli_connect($dbHost, (string) DB_USERNAME, (string) DB_PASSWORD, $dbName);
if (!$conn) {
    respond(500, ['success' => false, 'message' => 'Failed to connect to database.']);
}
mysqli_set_charset($conn, 'utf8mb4');

$adminCheck = runPreparedQuery($conn, 'SELECT id FROM admins WHERE id = ? LIMIT 1', 'i', [$apiAdminId]);
if ($adminCheck === []) {
    respond(500, ['success' => false, 'message' => 'API_ADMIN_ID does not exist in admins table.']);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
switch ($method) {
    case 'GET':
        handleGetTickets($conn);
        break;
    case 'POST':
        handleCreateTicket($conn, $apiAdminId);
        break;
    case 'PATCH':
        handleUpdateTicket($conn, $apiAdminId);
        break;
    default:
        respond(405, ['success' => false, 'message' => 'Method not allowed. Use GET, POST, or PATCH.']);
}

function handleGetTickets(mysqli $conn): void
{
    $id = parseOptionalPositiveIntQueryParam('id');
    $code = trim((string) ($_GET['code'] ?? ''));

    if ($id !== null || $code !== '') {
        $ticket = fetchTicket($conn, $id, $code);
        if ($ticket === null) {
            respond(404, ['success' => false, 'message' => 'Support ticket not found.']);
        }

        $messages = runPreparedQuery(
            $conn,
            'SELECT id, ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at FROM support_ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC, id ASC',
            'i',
            [(int) $ticket['id']]
        );

        respond(200, [
            'success' => true,
            'data' => $ticket,
            'messages' => $messages,
        ]);
    }

    $limit = parseIntQueryParam('limit', 10, 1, 500);
    $offset = parseIntQueryParam('offset', 0, 0, 100000000);
    $status = trim((string) ($_GET['status'] ?? ''));
    $userId = parseOptionalPositiveIntQueryParam('user_id');

    $allowedSort = ['id', 'created_at', 'last_message_at', 'status', 'priority'];
    $sortRaw = trim((string) ($_GET['sort'] ?? '-created_at'));
    $sortDirection = 'DESC';
    $sortColumn = $sortRaw;
    if (strncmp($sortRaw, '-', 1) === 0) {
        $sortDirection = 'DESC';
        $sortColumn = substr($sortRaw, 1);
    } elseif (strncmp($sortRaw, '+', 1) === 0) {
        $sortDirection = 'ASC';
        $sortColumn = substr($sortRaw, 1);
    } else {
        $sortDirection = 'ASC';
    }
    if (!in_array($sortColumn, $allowedSort, true)) {
        badRequest('Invalid sort column.');
    }

    $where = [];
    $types = '';
    $params = [];
    if ($status !== '') {
        $where[] = 'st.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    if ($userId !== null) {
        $where[] = 'st.user_id = ?';
        $types .= 'i';
        $params[] = $userId;
    }
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countRows = runPreparedQuery(
        $conn,
        "SELECT COUNT(*) AS total_rows FROM support_tickets_v2 st{$whereSql}",
        $types,
        $params
    );
    $totalRows = isset($countRows[0]['total_rows']) ? (int) $countRows[0]['total_rows'] : 0;

    $rows = runPreparedQuery(
        $conn,
        "SELECT st.id, st.code, st.subject, st.user_id, st.status, st.priority, st.category, st.assigned_admin_id, st.last_message_at, st.closed_at, st.created_at, st.updated_at, u.first_name, u.last_name, u.email
         FROM support_tickets_v2 st
         JOIN users u ON u.id = st.user_id
         {$whereSql}
         ORDER BY st.`{$sortColumn}` {$sortDirection}
         LIMIT {$limit} OFFSET {$offset}",
        $types,
        $params
    );

    respond(200, [
        'success' => true,
        'meta' => [
            'total' => $totalRows,
            'count' => count($rows),
            'limit' => $limit,
            'offset' => $offset,
        ],
        'data' => $rows,
    ]);
}

function handleCreateTicket(mysqli $conn, int $apiAdminId): void
{
    $input = getRequestData();
    $userId = requirePositiveInt($input, 'user_id');
    $subject = requireNonEmptyString($input, 'subject', 150);
    $message = requireNonEmptyString($input, 'message', 50000);

    $category = null;
    if (array_key_exists('category', $input) && $input['category'] !== null && trim((string) $input['category']) !== '') {
        $category = trim((string) $input['category']);
        if (strlen($category) > 50) {
            badRequest('category cannot exceed 50 characters.');
        }
    }

    $priority = 'medium';
    if (array_key_exists('priority', $input) && $input['priority'] !== null && trim((string) $input['priority']) !== '') {
        $priority = strtolower(trim((string) $input['priority']));
    }
    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
        badRequest('priority must be one of: low, medium, high, urgent.');
    }

    $userRows = runPreparedQuery(
        $conn,
        'SELECT id, first_name, email FROM users WHERE id = ? LIMIT 1',
        'i',
        [$userId]
    );
    if ($userRows === []) {
        respond(404, ['success' => false, 'message' => 'User not found.']);
    }
    $user = $userRows[0];

    $code = generateUniqueTicketCode($conn);
    $now = date('Y-m-d H:i:s');

    mysqli_begin_transaction($conn);
    try {
        $stmt = executePrepared(
            $conn,
            'INSERT INTO support_tickets_v2 (code, subject, user_id, status, priority, category, assigned_admin_id, last_message_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'ssisssiss',
            [$code, $subject, $userId, 'open', $priority, $category, $apiAdminId, $now, $now]
        );
        mysqli_stmt_close($stmt);

        $ticketId = (int) mysqli_insert_id($conn);

        $stmt2 = executePrepared(
            $conn,
            'INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'isiisis',
            [$ticketId, 'user', $userId, null, $message, 0, $now]
        );
        mysqli_stmt_close($stmt2);

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        respond(500, ['success' => false, 'message' => 'Failed to create support ticket.', 'error' => $e->getMessage()]);
    }

    $mailStatus = 'skipped';
    $createdTicket = fetchTicket($conn, $ticketId, '');
    $audit = logSupportAuditChange(
        $conn,
        $apiAdminId,
        'create',
        $ticketId,
        [
            'after' => $createdTicket,
            'former' => null,
        ]
    );

    if (!empty($user['email'])) {
        $mailSubject = "Re: Support Ticket (#{$code}) - {$subject}";
        $mailBody = 'Hi ' . htmlspecialchars((string) ($user['first_name'] ?? 'there')) . ',<br><br>Your support ticket has been created successfully. Our team will respond shortly.<br><br>Ticket code: <b>#' . htmlspecialchars($code) . '</b><br><br>Best regards,<br>Support Team<br>Nivasity';
        $mailStatus = sendMail($mailSubject, $mailBody, (string) $user['email']);
    }

    $notificationResult = ['success' => false, 'message' => 'Notification helper unavailable'];
    if (function_exists('sendNotification')) {
        $notificationResult = sendNotification(
            $conn,
            $apiAdminId,
            $userId,
            'Support Ticket Created',
            "Your support ticket #{$code} has been created.",
            'support',
            [
                'action' => 'support_ticket',
                'ticket_id' => $ticketId,
                'ticket_code' => $code,
                'subject' => $subject,
                'status' => 'open',
            ]
        );
    }

    respond(201, [
        'success' => true,
        'message' => 'Support ticket created successfully.',
        'data' => [
            'id' => $ticketId,
            'code' => $code,
            'user_id' => $userId,
            'status' => 'open',
            'priority' => $priority,
            'category' => $category,
            'created_at' => $now,
        ],
        'audit' => $audit,
        'delivery' => [
            'mail_status' => $mailStatus,
            'notification' => $notificationResult,
        ],
    ]);
}

function handleUpdateTicket(mysqli $conn, int $apiAdminId): void
{
    $input = getRequestData();
    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if (!in_array($action, ['respond', 'close'], true)) {
        badRequest('action is required and must be respond or close.');
    }

    $id = array_key_exists('id', $input) ? toPositiveIntOrNull($input['id']) : null;
    $code = array_key_exists('code', $input) ? trim((string) $input['code']) : '';
    if ($id === null && $code === '') {
        badRequest('Provide id or code to identify the support ticket.');
    }

    $ticket = fetchTicket($conn, $id, $code);
    if ($ticket === null) {
        respond(404, ['success' => false, 'message' => 'Support ticket not found.']);
    }

    $ticketId = (int) $ticket['id'];
    $formerTicket = $ticket;
    $userId = (int) $ticket['user_id'];
    $ticketCode = (string) $ticket['code'];
    $subject = (string) $ticket['subject'];
    $now = date('Y-m-d H:i:s');

    $responseBody = '';
    if (array_key_exists('response', $input) && $input['response'] !== null) {
        $responseBody = trim((string) $input['response']);
        if (strlen($responseBody) > 50000) {
            badRequest('response cannot exceed 50000 characters.');
        }
    }

    $closeOnRespond = false;
    if ($action === 'respond') {
        if ($responseBody === '') {
            badRequest('response is required for action=respond.');
        }
        if (array_key_exists('close_ticket', $input)) {
            $closeOnRespond = filter_var($input['close_ticket'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }
    }

    mysqli_begin_transaction($conn);
    try {
        if ($action === 'respond' || ($action === 'close' && $responseBody !== '')) {
            $stmt = executePrepared(
                $conn,
                'INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'isiisis',
                [$ticketId, 'admin', null, $apiAdminId, $responseBody, 0, $now]
            );
            mysqli_stmt_close($stmt);
        }

        $newStatus = 'open';
        $closedAt = null;
        if ($action === 'close' || $closeOnRespond) {
            $newStatus = 'closed';
            $closedAt = $now;
        }

        $stmt2 = executePrepared(
            $conn,
            'UPDATE support_tickets_v2 SET status = ?, assigned_admin_id = ?, last_message_at = ?, closed_at = ? WHERE id = ?',
            'sissi',
            [$newStatus, $apiAdminId, $now, $closedAt, $ticketId]
        );
        mysqli_stmt_close($stmt2);

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        respond(500, ['success' => false, 'message' => 'Failed to update support ticket.', 'error' => $e->getMessage()]);
    }

    $firstName = (string) ($ticket['first_name'] ?? 'there');
    $userEmail = (string) ($ticket['email'] ?? '');
    $mailStatus = 'skipped';
    if ($userEmail !== '') {
        if ($action === 'close' || $closeOnRespond) {
            $mailSubject = "Re: Support Ticket (#{$ticketCode}) - {$subject}";
            $mailBody = 'Hi ' . htmlspecialchars($firstName) . ',<br><br>Your support ticket has been closed.';
            if ($responseBody !== '') {
                $mailBody .= '<br><br>' . nl2br(htmlspecialchars($responseBody));
            }
            $mailBody .= '<br><br>Best regards,<br>Support Team<br>Nivasity';
            $mailStatus = sendMail($mailSubject, $mailBody, $userEmail);
        } else {
            $mailSubject = "Re: Support Ticket (#{$ticketCode}) - {$subject}";
            $mailBody = 'Hi ' . htmlspecialchars($firstName) . ',<br><br>' . nl2br(htmlspecialchars($responseBody)) . '<br><br>Best regards,<br>Support Team<br>Nivasity';
            $mailStatus = sendMail($mailSubject, $mailBody, $userEmail);
        }
    }

    $notificationResult = ['success' => false, 'message' => 'Notification helper unavailable'];
    if ($action === 'close' || $closeOnRespond) {
        if (function_exists('notifySupportTicketClosed')) {
            $notificationResult = notifySupportTicketClosed($conn, $apiAdminId, $userId, $ticketId, $ticketCode, $subject);
        }
    } else {
        if (function_exists('notifySupportTicketResponse')) {
            $notificationResult = notifySupportTicketResponse($conn, $apiAdminId, $userId, $ticketId, $ticketCode, $subject);
        }
    }

    $afterTicket = fetchTicket($conn, $ticketId, '');
    $auditAction = ($action === 'close' || $closeOnRespond) ? 'close' : 'respond';
    $audit = logSupportAuditChange(
        $conn,
        $apiAdminId,
        $auditAction,
        $ticketId,
        [
            'after' => $afterTicket,
            'former' => $formerTicket,
        ]
    );

    respond(200, [
        'success' => true,
        'message' => 'Support ticket updated successfully.',
        'data' => [
            'id' => $ticketId,
            'code' => $ticketCode,
            'status' => ($action === 'close' || $closeOnRespond) ? 'closed' : 'open',
            'updated_at' => $now,
        ],
        'audit' => $audit,
        'delivery' => [
            'mail_status' => $mailStatus,
            'notification' => $notificationResult,
        ],
    ]);
}

function fetchTicket(mysqli $conn, ?int $id, string $code): ?array
{
    if ($id !== null) {
        $rows = runPreparedQuery(
            $conn,
            'SELECT st.id, st.code, st.subject, st.user_id, st.status, st.priority, st.category, st.assigned_admin_id, st.last_message_at, st.closed_at, st.created_at, st.updated_at, u.first_name, u.last_name, u.email
             FROM support_tickets_v2 st
             JOIN users u ON u.id = st.user_id
             WHERE st.id = ?
             LIMIT 1',
            'i',
            [$id]
        );
        return $rows[0] ?? null;
    }

    $rows = runPreparedQuery(
        $conn,
        'SELECT st.id, st.code, st.subject, st.user_id, st.status, st.priority, st.category, st.assigned_admin_id, st.last_message_at, st.closed_at, st.created_at, st.updated_at, u.first_name, u.last_name, u.email
         FROM support_tickets_v2 st
         JOIN users u ON u.id = st.user_id
         WHERE st.code = ?
         LIMIT 1',
        's',
        [$code]
    );
    return $rows[0] ?? null;
}

function generateUniqueTicketCode(mysqli $conn): string
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $rows = runPreparedQuery($conn, 'SELECT id FROM support_tickets_v2 WHERE code = ? LIMIT 1', 's', [$code]);
        if ($rows === []) {
            return $code;
        }
    }

    throw new RuntimeException('Unable to generate unique ticket code.');
}

function getRequestData(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || trim($rawBody) === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            badRequest('Invalid JSON payload.');
        }
        return $decoded;
    }

    return $_POST;
}

function requireNonEmptyString(array $input, string $field, int $maxLength): string
{
    if (!array_key_exists($field, $input)) {
        badRequest("Missing required field: {$field}");
    }
    $value = trim((string) $input[$field]);
    if ($value === '') {
        badRequest("Field {$field} cannot be empty.");
    }
    if (strlen($value) > $maxLength) {
        badRequest("Field {$field} exceeds max length of {$maxLength}.");
    }
    return $value;
}

function requirePositiveInt(array $input, string $field): int
{
    if (!array_key_exists($field, $input)) {
        badRequest("Missing required field: {$field}");
    }
    $intValue = filter_var($input[$field], FILTER_VALIDATE_INT);
    if ($intValue === false || (int) $intValue <= 0) {
        badRequest("Field {$field} must be a positive integer.");
    }
    return (int) $intValue;
}

function toPositiveIntOrNull($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $intValue = filter_var($value, FILTER_VALIDATE_INT);
    if ($intValue === false || (int) $intValue <= 0) {
        badRequest('id must be a positive integer.');
    }
    return (int) $intValue;
}

function parseOptionalPositiveIntQueryParam(string $name): ?int
{
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return null;
    }
    $value = filter_var($_GET[$name], FILTER_VALIDATE_INT);
    if ($value === false || (int) $value <= 0) {
        badRequest("Query parameter {$name} must be a positive integer.");
    }
    return (int) $value;
}

function parseIntQueryParam(string $paramName, int $default, int $min, int $max): int
{
    if (!isset($_GET[$paramName]) || $_GET[$paramName] === '') {
        return $default;
    }
    $value = filter_var($_GET[$paramName], FILTER_VALIDATE_INT);
    if ($value === false) {
        badRequest("Invalid integer for {$paramName}.");
    }
    $intValue = (int) $value;
    if ($intValue < $min || $intValue > $max) {
        badRequest("{$paramName} must be between {$min} and {$max}.");
    }
    return $intValue;
}

function executePrepared(mysqli $conn, string $sql, string $types, array $params): mysqli_stmt
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare query: ' . mysqli_error($conn));
    }

    if ($types !== '') {
        bindStatementParams($stmt, $types, $params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Failed to execute query: ' . $error);
    }

    return $stmt;
}

function runPreparedQuery(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = executePrepared($conn, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function bindStatementParams(mysqli_stmt $stmt, string $types, array $params): void
{
    $bindArgs = [$types];
    foreach ($params as $index => $value) {
        $bindArgs[] = &$params[$index];
    }

    $ok = call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    if ($ok === false) {
        throw new RuntimeException('Failed to bind query parameters.');
    }
}

function logSupportAuditChange(mysqli $conn, int $adminId, string $action, int $ticketId, array $details): array
{
    if (!function_exists('log_audit_event')) {
        return ['success' => false, 'message' => 'log_audit_event() not available.'];
    }

    $ok = log_audit_event($conn, $adminId, $action, 'support_ticket', (string) $ticketId, $details);
    return [
        'success' => (bool) $ok,
        'message' => $ok ? 'Audit log recorded.' : 'Failed to record audit log.',
        'details' => $details,
    ];
}

function authorizeRequest(string $expectedToken): void
{
    $authorization = getAuthorizationHeader();
    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        unauthorized('Missing or invalid Authorization header. Use: Bearer <token>.');
    }
    $incomingToken = trim($matches[1]);
    if ($incomingToken === '' || !hash_equals($expectedToken, $incomingToken)) {
        unauthorized('Invalid bearer token.');
    }
}

function getAuthorizationHeader(): string
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                return trim((string) $value);
            }
        }
    }
    return '';
}

function badRequest(string $message): void
{
    respond(400, ['success' => false, 'message' => $message]);
}

function unauthorized(string $message): void
{
    header('WWW-Authenticate: Bearer');
    respond(401, ['success' => false, 'message' => $message]);
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
