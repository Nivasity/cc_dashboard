<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: PATCH, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';

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

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'PATCH') {
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed. Use PATCH.',
    ]);
}

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

$input = getRequestData();
ensureAllowedInputFields($input, ['new_manual_id', 'old_manual_id', 'ref_id']);

$newManualId = requirePositiveInt($input, 'new_manual_id');
$oldManualId = requirePositiveInt($input, 'old_manual_id');
$refId = requireNonEmptyString($input, 'ref_id', 50);

$matchingOrderRows = runPreparedQuery(
    $conn,
    'SELECT id, manual_id, price, buyer, ref_id, status FROM manuals_bought WHERE manual_id = ? AND ref_id = ? ORDER BY id ASC',
    'is',
    [$oldManualId, $refId]
);

$matchingCount = count($matchingOrderRows);
if ($matchingCount === 0) {
    respond(404, [
        'success' => false,
        'message' => 'No manuals_bought order found for the supplied old_manual_id and ref_id.',
    ]);
}

if ($matchingCount > 1) {
    respond(409, [
        'success' => false,
        'message' => 'Duplicate records found for old_manual_id and ref_id. Update aborted.',
        'duplicates_count' => $matchingCount,
    ]);
}

$orderRow = $matchingOrderRows[0];
$orderId = (int) $orderRow['id'];
$orderAmount = (int) ($orderRow['price'] ?? 0);
$buyerId = (int) ($orderRow['buyer'] ?? 0);

if ($buyerId <= 0) {
    respond(409, [
        'success' => false,
        'message' => 'Matched order has an invalid buyer.',
    ]);
}

$oldManualRows = runPreparedQuery(
    $conn,
    'SELECT id, price, status FROM manuals WHERE id = ? LIMIT 1',
    'i',
    [$oldManualId]
);
if ($oldManualRows === []) {
    respond(404, [
        'success' => false,
        'message' => 'old_manual_id does not exist in manuals table.',
    ]);
}
$oldManual = $oldManualRows[0];

$newManualRows = runPreparedQuery(
    $conn,
    'SELECT id, price, status, depts FROM manuals WHERE id = ? LIMIT 1',
    'i',
    [$newManualId]
);
if ($newManualRows === []) {
    respond(404, [
        'success' => false,
        'message' => 'new_manual_id does not exist in manuals table.',
    ]);
}
$newManual = $newManualRows[0];

$buyerRows = runPreparedQuery(
    $conn,
    'SELECT id, dept FROM users WHERE id = ? LIMIT 1',
    'i',
    [$buyerId]
);
if ($buyerRows === []) {
    respond(404, [
        'success' => false,
        'message' => 'Order buyer was not found in users table.',
    ]);
}
$buyerDept = (int) ($buyerRows[0]['dept'] ?? 0);
if ($buyerDept <= 0) {
    respond(403, [
        'success' => false,
        'message' => 'User is not allowed to buy this material.',
        'reason' => 'Buyer has no valid department.',
    ]);
}

$newManualDeptIds = parseDeptCsv((string) ($newManual['depts'] ?? ''));
if (!in_array($buyerDept, $newManualDeptIds, true)) {
    respond(403, [
        'success' => false,
        'message' => 'User is not allowed to buy this material.',
        'reason' => 'Buyer department is outside material department scope.',
        'buyer_dept' => $buyerDept,
    ]);
}

if (strtolower((string) ($newManual['status'] ?? '')) !== 'open') {
    respond(409, [
        'success' => false,
        'message' => 'new_manual_id is not open.',
    ]);
}

$oldManualPrice = (int) ($oldManual['price'] ?? 0);
$newManualPrice = (int) ($newManual['price'] ?? 0);
if ($newManualPrice !== $oldManualPrice) {
    respond(409, [
        'success' => false,
        'message' => 'new_manual_id price does not match old_manual_id price.',
        'old_manual_price' => $oldManualPrice,
        'new_manual_price' => $newManualPrice,
    ]);
}

if ($newManualPrice !== $orderAmount) {
    respond(409, [
        'success' => false,
        'message' => 'new_manual_id price does not match the current order amount.',
        'order_amount' => $orderAmount,
        'new_manual_price' => $newManualPrice,
    ]);
}

executePrepared(
    $conn,
    'UPDATE manuals_bought SET manual_id = ? WHERE id = ? LIMIT 1',
    'ii',
    [$newManualId, $orderId]
);

$updatedRows = runPreparedQuery(
    $conn,
    'SELECT id, manual_id, price, seller, buyer, school_id, ref_id, status, created_at, grant_status, export_id FROM manuals_bought WHERE id = ? LIMIT 1',
    'i',
    [$orderId]
);

respond(200, [
    'success' => true,
    'message' => 'Order manual_id updated successfully.',
    'data' => [
        'order_id' => $orderId,
        'ref_id' => $refId,
        'old_manual_id' => $oldManualId,
        'new_manual_id' => $newManualId,
        'record' => $updatedRows[0] ?? null,
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

function ensureAllowedInputFields(array $input, array $allowedFields): void
{
    $unsupported = array_values(array_diff(array_keys($input), $allowedFields));
    if ($unsupported === []) {
        return;
    }

    sort($unsupported);
    $expected = array_values($allowedFields);
    sort($expected);

    badRequest('Unsupported field(s) in request payload.', [
        'unsupported_fields' => $unsupported,
        'expected_fields' => $expected,
    ]);
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

function parseDeptCsv(string $csv): array
{
    if ($csv === '') {
        return [];
    }

    $parts = explode(',', $csv);
    $ids = [];
    foreach ($parts as $part) {
        $id = (int) trim($part);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
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

function badRequest(string $message, array $extra = []): void
{
    respond(400, array_merge([
        'success' => false,
        'message' => $message,
    ], $extra));
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
