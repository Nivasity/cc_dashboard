<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, PATCH, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/model/functions.php';
require_once dirname(__DIR__, 2) . '/model/mail.php';
require_once dirname(__DIR__, 2) . '/model/notification_helpers.php';

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

if (!defined('API_ADMIN_ID') || (int) API_ADMIN_ID <= 0) {
    respond(500, [
        'success' => false,
        'message' => 'API_ADMIN_ID is not configured in config/db.php.',
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

$apiAdminId = (int) API_ADMIN_ID;
$adminRows = runPreparedQuery($conn, 'SELECT id FROM admins WHERE id = ? LIMIT 1', 'i', [$apiAdminId]);
if ($adminRows === []) {
    respond(500, [
        'success' => false,
        'message' => 'API_ADMIN_ID does not exist in admins table.',
    ]);
}

if (!defined('API_EFFECTIVE_ADMIN_ID')) {
    define('API_EFFECTIVE_ADMIN_ID', $apiAdminId);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
switch ($method) {
    case 'GET':
        handleGetUsers($conn);
        break;
    case 'PATCH':
        handleUpdateUser($conn);
        break;
    default:
        respond(405, [
            'success' => false,
            'message' => 'Method not allowed. Use GET or PATCH.',
        ]);
}

function handleGetUsers(mysqli $conn): void
{
    $tableColumns = getUsersColumns($conn);
    if ($tableColumns === []) {
        respond(500, [
            'success' => false,
            'message' => 'Unable to read users table schema.',
        ]);
    }

    // Never return password hashes from API responses.
    $safeColumns = array_values(array_filter(
        $tableColumns,
        static function (string $column): bool {
            return $column !== 'password';
        }
    ));
    $columnSet = array_fill_keys($safeColumns, true);

    $id = parseOptionalPositiveIntQueryParam('id');
    $selectClause = buildSelectClause($_GET['columns'] ?? '', $columnSet, $safeColumns);

    if ($id !== null) {
        $sql = "SELECT {$selectClause} FROM `users` WHERE `id` = ? LIMIT 1";
        $rows = runPreparedQuery($conn, $sql, 'i', [$id]);
        if ($rows === []) {
            respond(404, [
                'success' => false,
                'message' => 'User not found.',
            ]);
        }

        respond(200, [
            'success' => true,
            'data' => $rows[0],
        ]);
    }

    $whereClauses = [];
    $types = '';
    $params = [];

    appendFilters($whereClauses, $types, $params, $columnSet, $_GET['filter'] ?? null, '=');
    appendFilters($whereClauses, $types, $params, $columnSet, $_GET['filter_ne'] ?? null, '!=');
    appendFilters($whereClauses, $types, $params, $columnSet, $_GET['filter_gt'] ?? null, '>');
    appendFilters($whereClauses, $types, $params, $columnSet, $_GET['filter_gte'] ?? null, '>=');
    appendFilters($whereClauses, $types, $params, $columnSet, $_GET['filter_lt'] ?? null, '<');
    appendFilters($whereClauses, $types, $params, $columnSet, $_GET['filter_lte'] ?? null, '<=');
    appendLikeFilters($whereClauses, $types, $params, $columnSet, $_GET['filter_like'] ?? null);
    appendInFilters($whereClauses, $types, $params, $columnSet, $_GET['filter_in'] ?? null);

    $orderByClause = buildSortClause($_GET['sort'] ?? '', $columnSet);
    $limit = parseIntQueryParam('limit', 10, 1, 500);
    $offset = parseIntQueryParam('offset', 0, 0, 100000000);

    $whereSql = $whereClauses ? (' WHERE ' . implode(' AND ', $whereClauses)) : '';

    $countSql = "SELECT COUNT(*) AS total_rows FROM `users`{$whereSql}";
    $countRows = runPreparedQuery($conn, $countSql, $types, $params);
    $totalRows = isset($countRows[0]['total_rows']) ? (int) $countRows[0]['total_rows'] : 0;

    $dataSql = "SELECT {$selectClause} FROM `users`{$whereSql}{$orderByClause} LIMIT {$limit} OFFSET {$offset}";
    $rows = runPreparedQuery($conn, $dataSql, $types, $params);

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

function handleUpdateUser(mysqli $conn): void
{
    $id = parseRequiredPositiveIntQueryParam('id');
    $existing = fetchSafeUserById($conn, $id);
    if ($existing === null) {
        respond(404, [
            'success' => false,
            'message' => 'User not found.',
        ]);
    }

    $input = getRequestData();
    $payload = normalizeUserPayload($input);
    if ($payload === []) {
        badRequest('No updatable fields supplied.');
    }

    if (isset($payload['email'])) {
        $emailConflict = runPreparedQuery(
            $conn,
            'SELECT `id` FROM `users` WHERE `email` = ? AND `id` != ? LIMIT 1',
            'si',
            [$payload['email'], $id]
        );
        if ($emailConflict !== []) {
            respond(409, [
                'success' => false,
                'message' => 'Another user already uses this email.',
            ]);
        }
    }

    $setFragments = [];
    $types = '';
    $params = [];
    foreach ($payload as $column => $value) {
        $escapedColumn = str_replace('`', '``', $column);
        $setFragments[] = "`{$escapedColumn}` = ?";
        $types .= inferMysqliType($value);
        $params[] = $value;
    }

    $types .= 'i';
    $params[] = $id;

    $setSql = implode(', ', $setFragments);
    $sql = "UPDATE `users` SET {$setSql} WHERE `id` = ?";
    executePreparedStatement($conn, $sql, $types, $params, 'Failed to update user.');

    $updatedUser = fetchSafeUserById($conn, $id);
    $audit = logUserAuditUpdate($conn, (int) API_EFFECTIVE_ADMIN_ID, $id, $existing, $updatedUser);
    $delivery = sendUserUpdateNotifications($conn, $id, $payload, $updatedUser);

    respond(200, [
        'success' => true,
        'message' => 'User updated successfully.',
        'data' => $updatedUser,
        'audit' => $audit,
        'delivery' => $delivery,
    ]);
}

function getUsersColumns(mysqli $conn): array
{
    $result = mysqli_query($conn, 'SHOW COLUMNS FROM `users`');
    if (!$result) {
        return [];
    }

    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['Field'])) {
            $columns[] = (string) $row['Field'];
        }
    }

    return $columns;
}

function fetchSafeUserById(mysqli $conn, int $id): ?array
{
    $rows = runPreparedQuery(
        $conn,
        'SELECT `id`, `first_name`, `last_name`, `email`, `phone`, `gender`, `school`, `dept`, `matric_no`, `role`, `status`, `adm_year`, `profile_pic`, `last_login` FROM `users` WHERE `id` = ? LIMIT 1',
        'i',
        [$id]
    );

    return $rows[0] ?? null;
}

function sendUserUpdateNotifications(mysqli $conn, int $userId, array $payload, ?array $user): array
{
    $result = [
        'mail_status' => 'skipped',
        'notification' => ['success' => false, 'message' => 'Notification not sent.'],
    ];

    if ($user === null) {
        $result['notification']['message'] = 'User not found after update.';
        return $result;
    }

    $updateType = 'info';
    if (array_key_exists('status', $payload)) {
        $newStatus = strtolower((string) ($payload['status'] ?? ''));
        $updateType = $newStatus === 'verified' ? 'verification' : 'status';
    }

    $firstName = trim((string) ($user['first_name'] ?? 'there'));
    $email = trim((string) ($user['email'] ?? ''));

    if ($email !== '' && function_exists('sendMail')) {
        if ($updateType === 'verification') {
            $subject = 'Account Verification Update';
            $body = 'Hi ' . htmlspecialchars($firstName) . ',<br><br>Your account has been verified by an administrator.<br><br>Best regards,<br>Support Team<br>Nivasity';
        } elseif ($updateType === 'status') {
            $subject = 'Account Status Update';
            $body = 'Hi ' . htmlspecialchars($firstName) . ',<br><br>Your account status has been updated by an administrator. Please check your profile for details.<br><br>Best regards,<br>Support Team<br>Nivasity';
        } else {
            $subject = 'Profile Update Notification';
            $body = 'Hi ' . htmlspecialchars($firstName) . ',<br><br>Your profile information has been updated by an administrator.<br><br>Best regards,<br>Support Team<br>Nivasity';
        }

        $result['mail_status'] = sendMail($subject, $body, $email);
    }

    $apiAdminId = defined('API_EFFECTIVE_ADMIN_ID') ? (int) API_EFFECTIVE_ADMIN_ID : (defined('API_ADMIN_ID') ? (int) API_ADMIN_ID : 0);
    if ($apiAdminId <= 0) {
        $result['notification'] = ['success' => false, 'message' => 'API_ADMIN_ID is not configured.'];
        return $result;
    }

    $adminRows = runPreparedQuery($conn, 'SELECT id FROM admins WHERE id = ? LIMIT 1', 'i', [$apiAdminId]);
    if ($adminRows === []) {
        $result['notification'] = ['success' => false, 'message' => 'API_ADMIN_ID does not exist in admins table.'];
        return $result;
    }

    if (function_exists('notifyStudentProfileUpdate')) {
        $result['notification'] = notifyStudentProfileUpdate($conn, $apiAdminId, $userId, $updateType);
    } else {
        $result['notification'] = ['success' => false, 'message' => 'notifyStudentProfileUpdate() not available.'];
    }

    return $result;
}

function logUserAuditUpdate(mysqli $conn, int $adminId, int $userId, array $former, ?array $after): array
{
    if (!function_exists('log_audit_event')) {
        return ['success' => false, 'message' => 'log_audit_event() not available.'];
    }

    $details = [
        'after' => $after,
        'former' => $former,
    ];

    $ok = log_audit_event($conn, $adminId, 'update', 'user', (string) $userId, $details);
    return [
        'success' => (bool) $ok,
        'message' => $ok ? 'Audit log recorded.' : 'Failed to record audit log.',
        'details' => $details,
    ];
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

function normalizeUserPayload(array $input): array
{
    $rules = [
        'first_name' => ['nullable' => false, 'type' => 'string'],
        'last_name' => ['nullable' => false, 'type' => 'string'],
        'email' => ['nullable' => false, 'type' => 'email'],
        'phone' => ['nullable' => false, 'type' => 'string'],
        'gender' => ['nullable' => true, 'type' => 'string'],
        'school' => ['nullable' => true, 'type' => 'int'],
        'dept' => ['nullable' => true, 'type' => 'int'],
        'matric_no' => ['nullable' => true, 'type' => 'string'],
        'role' => ['nullable' => false, 'type' => 'string'],
        'status' => ['nullable' => true, 'type' => 'string'],
        'adm_year' => ['nullable' => true, 'type' => 'string'],
        'profile_pic' => ['nullable' => true, 'type' => 'string'],
    ];
    $expectedFields = array_keys($rules);

    if (array_key_exists('password', $input)) {
        badRequest('Password updates are not allowed on this endpoint.', [
            'unsupported_fields' => ['password'],
            'expected_fields' => $expectedFields,
        ]);
    }

    $unknownKeys = array_values(array_diff(array_keys($input), $expectedFields));
    if ($unknownKeys !== []) {
        badRequestUnsupportedFields($unknownKeys, $expectedFields);
    }

    $payload = [];
    foreach ($rules as $field => $rule) {
        $hasInput = array_key_exists($field, $input);

        if (!$hasInput) {
            continue;
        }

        $value = $input[$field];
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' && ($rule['nullable'] ?? false)) {
            $value = null;
        }

        if ($value === null && !($rule['nullable'] ?? false)) {
            badRequest("Field {$field} cannot be null.");
        }

        $type = (string) ($rule['type'] ?? 'string');
        switch ($type) {
            case 'int':
                if ($value === null) {
                    $payload[$field] = null;
                    break;
                }

                $validated = filter_var($value, FILTER_VALIDATE_INT);
                if ($validated === false) {
                    badRequest("Field {$field} must be an integer.");
                }
                $payload[$field] = (int) $validated;
                break;

            case 'email':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    badRequest("Field {$field} must be a valid email address.");
                }
                $payload[$field] = strtolower($value);
                break;

            case 'string':
            default:
                if ($value !== null && !is_scalar($value)) {
                    badRequest("Field {$field} must be a scalar value.");
                }
                $payload[$field] = $value === null ? null : (string) $value;
                break;
        }
    }

    return $payload;
}

function parseRequiredPositiveIntQueryParam(string $paramName): int
{
    if (!isset($_GET[$paramName]) || $_GET[$paramName] === '') {
        badRequest("Missing required query parameter: {$paramName}");
    }

    $value = filter_var($_GET[$paramName], FILTER_VALIDATE_INT);
    if ($value === false || (int) $value <= 0) {
        badRequest("Query parameter {$paramName} must be a positive integer.");
    }

    return (int) $value;
}

function parseOptionalPositiveIntQueryParam(string $paramName): ?int
{
    if (!isset($_GET[$paramName]) || $_GET[$paramName] === '') {
        return null;
    }

    $value = filter_var($_GET[$paramName], FILTER_VALIDATE_INT);
    if ($value === false || (int) $value <= 0) {
        badRequest("Query parameter {$paramName} must be a positive integer.");
    }

    return (int) $value;
}

function inferMysqliType($value): string
{
    return is_int($value) ? 'i' : 's';
}

function executePreparedStatement(mysqli $conn, string $sql, string $types, array $params, string $errorMessage): void
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
            'message' => $errorMessage,
            'error' => $error,
        ]);
    }

    mysqli_stmt_close($stmt);
}

function buildSelectClause($columnsInput, array $columnSet, array $defaultColumns): string
{
    $raw = trim((string) $columnsInput);
    if ($raw === '' || $raw === '*') {
        $escapedDefault = array_map(
            static function (string $column): string {
                return '`' . str_replace('`', '``', $column) . '`';
            },
            $defaultColumns
        );
        return implode(', ', $escapedDefault);
    }

    $requested = array_filter(
        array_map('trim', explode(',', $raw)),
        static function ($value): bool {
            return $value !== '';
        }
    );
    if ($requested === []) {
        $escapedDefault = array_map(
            static function (string $column): string {
                return '`' . str_replace('`', '``', $column) . '`';
            },
            $defaultColumns
        );
        return implode(', ', $escapedDefault);
    }

    $selected = [];
    foreach ($requested as $column) {
        if (!isset($columnSet[$column])) {
            badRequest("Invalid or restricted column in columns parameter: {$column}");
        }

        $selected[$column] = true;
    }

    $escaped = array_map(
        static function (string $column): string {
            return '`' . str_replace('`', '``', $column) . '`';
        },
        array_keys($selected)
    );

    return implode(', ', $escaped);
}

function appendFilters(array &$whereClauses, string &$types, array &$params, array $columnSet, $filters, string $operator): void
{
    if ($filters === null) {
        return;
    }

    if (!is_array($filters)) {
        badRequest('Filter parameters must use array syntax, e.g. filter[id]=1.');
    }

    foreach ($filters as $column => $value) {
        if (!is_string($column) || !isset($columnSet[$column])) {
            badRequest('Invalid filter column provided.');
        }

        if (is_array($value)) {
            badRequest("Filter for {$column} must be a single scalar value.");
        }

        $escapedColumn = str_replace('`', '``', $column);
        $whereClauses[] = "`{$escapedColumn}` {$operator} ?";
        $types .= 's';
        $params[] = (string) $value;
    }
}

function appendLikeFilters(array &$whereClauses, string &$types, array &$params, array $columnSet, $filters): void
{
    if ($filters === null) {
        return;
    }

    if (!is_array($filters)) {
        badRequest('filter_like must use array syntax, e.g. filter_like[name]=john.');
    }

    foreach ($filters as $column => $value) {
        if (!is_string($column) || !isset($columnSet[$column])) {
            badRequest('Invalid filter_like column provided.');
        }

        if (is_array($value)) {
            badRequest("LIKE filter for {$column} must be a single scalar value.");
        }

        $escapedColumn = str_replace('`', '``', $column);
        $whereClauses[] = "`{$escapedColumn}` LIKE ?";
        $types .= 's';
        $params[] = '%' . (string) $value . '%';
    }
}

function appendInFilters(array &$whereClauses, string &$types, array &$params, array $columnSet, $filters): void
{
    if ($filters === null) {
        return;
    }

    if (!is_array($filters)) {
        badRequest('filter_in must use array syntax, e.g. filter_in[id]=1,2,3.');
    }

    foreach ($filters as $column => $value) {
        if (!is_string($column) || !isset($columnSet[$column])) {
            badRequest('Invalid filter_in column provided.');
        }

        $items = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                $trimmed = trim((string) $item);
                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        } else {
            foreach (explode(',', (string) $value) as $item) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        }

        if ($items === []) {
            badRequest("filter_in for {$column} must include at least one value.");
        }

        $escapedColumn = str_replace('`', '``', $column);
        $placeholders = implode(', ', array_fill(0, count($items), '?'));
        $whereClauses[] = "`{$escapedColumn}` IN ({$placeholders})";
        $types .= str_repeat('s', count($items));
        foreach ($items as $item) {
            $params[] = $item;
        }
    }
}

function buildSortClause($sortInput, array $columnSet): string
{
    $rawSort = trim((string) $sortInput);
    if ($rawSort === '') {
        return '';
    }

    $parts = array_filter(
        array_map('trim', explode(',', $rawSort)),
        static function ($value): bool {
            return $value !== '';
        }
    );
    if ($parts === []) {
        return '';
    }

    $sortItems = [];
    foreach ($parts as $part) {
        $direction = 'ASC';
        $column = $part;

        if (strncmp($part, '-', 1) === 0) {
            $direction = 'DESC';
            $column = substr($part, 1);
        } elseif (strncmp($part, '+', 1) === 0) {
            $column = substr($part, 1);
        }

        if ($column === '' || !isset($columnSet[$column])) {
            badRequest("Invalid sort column: {$part}");
        }

        $escapedColumn = str_replace('`', '``', $column);
        $sortItems[] = "`{$escapedColumn}` {$direction}";
    }

    return $sortItems ? (' ORDER BY ' . implode(', ', $sortItems)) : '';
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

function badRequestUnsupportedFields(array $unsupportedFields, array $expectedFields): void
{
    $unsupported = array_values(array_unique(array_map('strval', $unsupportedFields)));
    sort($unsupported);

    $expected = array_values(array_unique(array_map('strval', $expectedFields)));
    sort($expected);

    badRequest('Unsupported field(s) in request payload.', [
        'unsupported_fields' => $unsupported,
        'expected_fields' => $expected,
    ]);
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
