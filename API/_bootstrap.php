<?php
declare(strict_types=1);

if (!function_exists('api_send_json_failure')) {
    function api_send_json_failure(int $status, string $message, ?string $detail = null): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($status);
        }

        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($detail !== null && $detail !== '') {
            $payload['error'] = $detail;
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!defined('API_JSON_ERROR_HANDLERS_REGISTERED')) {
    define('API_JSON_ERROR_HANDLERS_REGISTERED', true);

    ini_set('display_errors', '0');

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $exception): void {
        $status = 500;
        $code = $exception->getCode();
        if (is_int($code) && $code >= 400 && $code <= 599) {
            $status = $code;
        }

        api_send_json_failure($status, 'Internal server error.', $exception->getMessage());
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        $detail = (string) ($error['message'] ?? 'Unknown fatal error');
        api_send_json_failure(500, 'Internal server error.', $detail);
    });
}
