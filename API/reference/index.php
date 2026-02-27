<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
    ]);
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

$schemaTables = loadTablesFromSchema(dirname(__DIR__) . '/niverpay_db.sql');
$allowedTables = $schemaTables ?: loadTablesFromDatabase($conn);

if ($allowedTables === []) {
    respond(500, [
        'success' => false,
        'message' => 'No tables available for reference endpoint.',
    ]);
}

$table = trim((string) ($_GET['table'] ?? ''));
if ($table === '') {
    respond(200, [
        'success' => true,
        'message' => 'Pass ?table=<table_name> to query a table.',
        'tables' => $allowedTables,
        'query_options' => [
            'columns' => 'Comma-separated columns (default: *)',
            'filter[field]' => 'Equals filter',
            'filter_ne[field]' => 'Not-equals filter',
            'filter_gt[field]' => 'Greater-than filter',
            'filter_gte[field]' => 'Greater-than-or-equal filter',
            'filter_lt[field]' => 'Less-than filter',
            'filter_lte[field]' => 'Less-than-or-equal filter',
            'filter_like[field]' => 'Contains filter',
            'filter_in[field]' => 'IN filter using comma-separated values',
            'sort' => 'Comma list. Prefix with - for DESC (example: sort=-id,created_at)',
            'limit' => 'Rows per request (1 - 500, default: 10)',
            'offset' => 'Start row offset (default: 0)',
        ],
    ]);
}

if (!in_array($table, $allowedTables, true)) {
    badRequest('Invalid table name. Only schema tables are allowed.');
}

$availableColumns = getTableColumns($conn, $table);
if ($availableColumns === []) {
    badRequest('Selected table does not expose queryable columns.');
}

$columnSet = array_fill_keys($availableColumns, true);
$selectClause = buildSelectClause($_GET['columns'] ?? '*', $columnSet);

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

$countSql = "SELECT COUNT(*) AS total_rows FROM `{$table}`{$whereSql}";
$countRows = runPreparedQuery($conn, $countSql, $types, $params);
$totalRows = isset($countRows[0]['total_rows']) ? (int) $countRows[0]['total_rows'] : 0;

$dataSql = "SELECT {$selectClause} FROM `{$table}`{$whereSql}{$orderByClause} LIMIT {$limit} OFFSET {$offset}";
$rows = runPreparedQuery($conn, $dataSql, $types, $params);

respond(200, [
    'success' => true,
    'table' => $table,
    'meta' => [
        'total' => $totalRows,
        'count' => count($rows),
        'limit' => $limit,
        'offset' => $offset,
    ],
    'data' => $rows,
]);

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

function loadTablesFromSchema(string $schemaFile): array
{
    if (!is_file($schemaFile)) {
        return [];
    }

    $schemaSql = file_get_contents($schemaFile);
    if ($schemaSql === false) {
        return [];
    }

    preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i', $schemaSql, $matches);
    if (empty($matches[1])) {
        return [];
    }

    $tables = array_values(array_unique($matches[1]));
    sort($tables, SORT_STRING);
    return $tables;
}

function loadTablesFromDatabase(mysqli $conn): array
{
    $result = mysqli_query($conn, 'SHOW TABLES');
    if (!$result) {
        return [];
    }

    $tables = [];
    while ($row = mysqli_fetch_row($result)) {
        if (isset($row[0])) {
            $tables[] = (string) $row[0];
        }
    }

    sort($tables, SORT_STRING);
    return $tables;
}

function getTableColumns(mysqli $conn, string $table): array
{
    $escapedTable = str_replace('`', '``', $table);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$escapedTable}`");
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

function buildSelectClause($columnsInput, array $columnSet): string
{
    $raw = trim((string) $columnsInput);
    if ($raw === '' || $raw === '*') {
        return '*';
    }

    $requested = array_filter(
        array_map('trim', explode(',', $raw)),
        static function ($value): bool {
            return $value !== '';
        }
    );
    if ($requested === []) {
        return '*';
    }

    $selected = [];
    foreach ($requested as $column) {
        if (!isset($columnSet[$column])) {
            badRequest("Invalid column in columns parameter: {$column}");
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
        ]);
    }

    if ($types !== '') {
        bindStatementParams($stmt, $types, $params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        respond(500, [
            'success' => false,
            'message' => 'Failed to execute database query.',
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
