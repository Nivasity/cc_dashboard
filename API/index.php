<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/API/index.php')), '/');
$referenceUrl = $scheme . '://' . $host . $basePath . '/reference';

echo json_encode(
    [
        'success' => true,
        'name' => 'Niverpay API',
        'endpoints' => [
            'reference' => $referenceUrl . '?table=users&limit=25&sort=-id',
            'users' => $scheme . '://' . $host . $basePath . '/users?limit=25&sort=-id',
            'support_tickets' => $scheme . '://' . $host . $basePath . '/support_tickets?limit=25&sort=-created_at',
            'verification_code' => $scheme . '://' . $host . $basePath . '/verification_code',
        ],
        'auth' => 'Bearer token required via Authorization header',
    ],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
