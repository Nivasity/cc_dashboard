<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/model/functions.php';
require_once dirname(__DIR__, 2) . '/model/mail.php';

if (!defined('DB_USERNAME') || !defined('DB_PASSWORD')) {
    respond(500, [
        'success' => false,
        'message' => 'Database credentials are not configured.',
    ]);
}

if (!defined('API_BEARER_TOKEN') || trim((string) API_BEARER_TOKEN) === '') {
    respond(500, [
        'success' => false,
        'message' => 'API_BEARER_TOKEN is not configured in config/db.php.',
    ]);
}

authorizeRequest((string) API_BEARER_TOKEN);

$dbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? (string) DB_NAME : 'niverpay_db';

$conn = mysqli_connect($dbHost, (string) DB_USERNAME, (string) DB_PASSWORD, $dbName);
if (!$conn) {
    respond(500, [
        'success' => false,
        'message' => 'Failed to connect to database.',
    ]);
}
mysqli_set_charset($conn, 'utf8mb4');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
}

$input = getRequestData();
$email = trim((string) ($input['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    badRequest('A valid email field is required.');
}
$email = strtolower($email);

$userRows = runPreparedQuery(
    $conn,
    'SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1',
    's',
    [$email]
);
if ($userRows === []) {
    respond(404, [
        'success' => false,
        'message' => 'User not found for the provided email.',
    ]);
}

$user = $userRows[0];
$userId = (int) $user['id'];
$firstName = trim((string) ($user['first_name'] ?? 'there'));

$verificationCode = generateUniqueVerificationCode($conn, 12);
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

executePrepared(
    $conn,
    'DELETE FROM verification_code WHERE exp_date IS NOT NULL AND exp_date < NOW()',
    '',
    []
);

executePrepared(
    $conn,
    'INSERT INTO verification_code (user_id, code, exp_date) VALUES (?, ?, ?)',
    'iss',
    [$userId, $verificationCode, $expiresAt]
);

$verificationUrl = buildVerificationUrl($verificationCode);
$subject = 'Verify Your Account on NIVASITY';
$body = 'Hello ' . htmlspecialchars($firstName) . ',<br><br>'
    . 'We received a request to verify your Nivasity account.<br><br>'
    . 'Your verification code is: <b>' . htmlspecialchars($verificationCode) . '</b><br><br>'
    . 'You can also verify directly with this link:<br>'
    . '<a href="' . htmlspecialchars($verificationUrl) . '">' . htmlspecialchars($verificationUrl) . '</a><br><br>'
    . 'This code will expire on <b>' . htmlspecialchars($expiresAt) . '</b>.<br><br>'
    . 'If you did not request this, please ignore this email.<br><br>'
    . 'Best regards,<br>Nivasity Team';

$mailStatus = sendMail($subject, $body, $email);
if ($mailStatus !== 'success') {
    executePrepared(
        $conn,
        'DELETE FROM verification_code WHERE user_id = ? AND code = ? LIMIT 1',
        'is',
        [$userId, $verificationCode]
    );

    respond(500, [
        'success' => false,
        'message' => 'Failed to send verification email.',
        'delivery' => [
            'mail_status' => $mailStatus,
        ],
    ]);
}

respond(200, [
    'success' => true,
    'message' => 'Verification code sent successfully.',
    'data' => [
        'user_id' => $userId,
        'email' => $email,
        'expires_at' => $expiresAt,
    ],
    'delivery' => [
        'mail_status' => $mailStatus,
    ],
]);

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

function generateUniqueVerificationCode(mysqli $conn, int $length): string
{
    if (!function_exists('generateVerificationCode') || !function_exists('isCodeUnique')) {
        respond(500, [
            'success' => false,
            'message' => 'Verification code helpers are not available in model/functions.php.',
        ]);
    }

    for ($attempt = 0; $attempt < 25; $attempt++) {
        $code = generateVerificationCode($length);
        if (isCodeUnique($code, $conn, 'verification_code')) {
            return $code;
        }
    }

    respond(500, [
        'success' => false,
        'message' => 'Unable to generate a unique verification code.',
    ]);
}

function buildVerificationUrl(string $code): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/API/verification_code/index.php'));
    $basePath = preg_replace('#/API/verification_code(?:/index\.php)?$#', '', $scriptName);
    if (!is_string($basePath)) {
        $basePath = '';
    }

    return $scheme . '://' . $host . rtrim($basePath, '/') . '/setup.html?verify=' . rawurlencode($code);
}

function executePrepared(mysqli $conn, string $sql, string $types, array $params): void
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        respond(500, [
            'success' => false,
            'message' => 'Failed to prepare database query.',
            'error' => mysqli_error($conn),
        ]);
    }

    if ($types !== '') {
        bindStatementParams($stmt, $types, $params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        respond(500, [
            'success' => false,
            'message' => 'Failed to execute database query.',
            'error' => $error,
        ]);
    }

    mysqli_stmt_close($stmt);
}

function runPreparedQuery(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        respond(500, [
            'success' => false,
            'message' => 'Failed to prepare database query.',
            'error' => mysqli_error($conn),
        ]);
    }

    if ($types !== '') {
        bindStatementParams($stmt, $types, $params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        respond(500, [
            'success' => false,
            'message' => 'Failed to execute database query.',
            'error' => $error,
        ]);
    }

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

    $bound = call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    if ($bound === false) {
        respond(500, [
            'success' => false,
            'message' => 'Failed to bind query parameters.',
        ]);
    }
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
    respond(400, [
        'success' => false,
        'message' => $message,
    ]);
}

function unauthorized(string $message): void
{
    header('WWW-Authenticate: Bearer');
    respond(401, [
        'success' => false,
        'message' => $message,
    ]);
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
