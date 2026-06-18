<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
sendSurveyCorsHeaders();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/model/surveys.php';

if (!defined('DB_USERNAME') || !defined('DB_PASSWORD')) {
    surveyRespond(500, ['success' => false, 'message' => 'Database credentials are not configured.']);
}

$dbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? (string) DB_NAME : 'niverpay_db';

$conn = mysqli_connect($dbHost, (string) DB_USERNAME, (string) DB_PASSWORD, $dbName);
if (!$conn) {
    surveyRespond(500, ['success' => false, 'message' => 'Failed to connect to database.']);
}
mysqli_set_charset($conn, 'utf8mb4');

if (!ccSurveysTablesReady($conn)) {
    surveyRespond(503, ['success' => false, 'message' => 'Survey tables are not available yet. Run the migration first.']);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$isAdmin = isset($_GET['admin']) && $_GET['admin'] === '1';

// ── Admin endpoints (Bearer token protected) ─────────────────────
if ($isAdmin) {
    verifyBearerToken();

    if ($method === 'GET') {
        handleAdminGet($conn);
    }
    if ($method === 'POST') {
        handleAdminPost($conn);
    }

    surveyRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

// ── Public endpoints ─────────────────────────────────────────────
if ($method === 'GET') {
    handlePublicGet($conn);
}

if ($method === 'POST') {
    handlePublicPost($conn);
}

surveyRespond(405, ['success' => false, 'message' => 'Method not allowed. Use GET, POST, or OPTIONS.']);


// ══════════════════════════════════════════════════════════════════
//  PUBLIC HANDLERS
// ══════════════════════════════════════════════════════════════════

function handlePublicGet(mysqli $conn): void
{
    $slug = trim((string) ($_GET['slug'] ?? ''));

    // Single survey by slug
    if ($slug !== '') {
        $survey = ccSurveysFetchBySlug($conn, $slug);
        if (!$survey || $survey['status'] !== 'published') {
            surveyRespond(404, ['success' => false, 'message' => 'Survey not found.']);
        }

        // Check expiry
        if (!ccSurveysIsAcceptingResponses($survey)) {
            surveyRespond(410, ['success' => false, 'message' => 'This survey has expired or is no longer accepting responses.']);
        }

        $questionsData = json_decode($survey['questions_json'] ?? '{}', true);
        surveyRespond(200, [
            'success' => true,
            'data' => [
                'id' => (int) $survey['id'],
                'slug' => $survey['slug'],
                'title' => $questionsData['title'] ?? $survey['title'],
                'description' => $questionsData['description'] ?? ($survey['description'] ?? ''),
                'questions' => $questionsData['questions'] ?? null,
                'sections' => $questionsData['sections'] ?? null,
            ],
        ]);
    }

    // List all published surveys
    $surveys = ccSurveysFetchPublished($conn);
    $data = [];
    foreach ($surveys as $s) {
        $data[] = [
            'id' => (int) $s['id'],
            'slug' => $s['slug'],
            'title' => $s['title'],
            'description' => $s['description'] ?? '',
        ];
    }

    surveyRespond(200, [
        'success' => true,
        'meta' => ['total' => count($data)],
        'data' => $data,
    ]);
}

function handlePublicPost(mysqli $conn): void
{
    $input = getSurveyRequestPayload();
    $action = trim((string) ($input['action'] ?? ''));

    $formId = trim((string) ($input['form_id'] ?? ''));
    if ($formId === '') {
        surveyRespond(400, ['success' => false, 'message' => 'Missing form_id.']);
    }

    // Resolve survey by slug (form_id = slug)
    $survey = ccSurveysFetchBySlug($conn, $formId);
    if (!$survey) {
        surveyRespond(404, ['success' => false, 'message' => 'Survey not found.']);
    }

    $surveyId = (int) $survey['id'];

    // ── Email duplicate check ────────────────────────────────────
    if ($action === 'confirm') {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            surveyRespond(400, ['success' => false, 'message' => 'Email is required.']);
        }

        $allowDuplicate = (int) ($survey['allow_duplicate_email'] ?? 0);
        if ($allowDuplicate) {
            // Always say "not recorded" so the form continues
            surveyRespond(404, ['success' => false, 'message' => 'Email not recorded.']);
        }

        $isDuplicate = ccSurveysCheckDuplicateEmail($conn, $surveyId, $email);
        if ($isDuplicate) {
            surveyRespond(200, ['success' => true, 'message' => 'Email already recorded.']);
        }

        // Not yet recorded → 404 tells the frontend to continue
        surveyRespond(404, ['success' => false, 'message' => 'Email not recorded.']);
    }

    // ── Submit response ──────────────────────────────────────────
    if ($action === 'submit') {
        if (!ccSurveysIsAcceptingResponses($survey)) {
            surveyRespond(410, ['success' => false, 'message' => 'This survey is no longer accepting responses.']);
        }

        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $responses = $input['responses'] ?? [];

        if ($firstName === '') {
            surveyRespond(400, ['success' => false, 'message' => 'First name is required.']);
        }
        if ($lastName === '') {
            surveyRespond(400, ['success' => false, 'message' => 'Last name is required.']);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            surveyRespond(400, ['success' => false, 'message' => 'A valid email address is required.']);
        }

        // Check for duplicate (unless allowed)
        $allowDuplicate = (int) ($survey['allow_duplicate_email'] ?? 0);
        if (!$allowDuplicate && ccSurveysCheckDuplicateEmail($conn, $surveyId, $email)) {
            surveyRespond(409, ['success' => false, 'message' => 'You have already submitted a response for this survey.']);
        }

        // Rate limiting by IP
        $submitterIp = resolveSurveySubmitterIp();
        enforceSurveyCooldown($conn, $email, $submitterIp);

        $responsesJson = is_array($responses) ? json_encode($responses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $responses;
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        $responseId = ccSurveysInsertResponse($conn, $surveyId, $firstName, $lastName, $email, $phone, $responsesJson, $submitterIp, $userAgent);

        if ($responseId === 0) {
            $dbError = mysqli_error($conn);
            // Duplicate key error (email already submitted)
            if (strpos($dbError, 'Duplicate entry') !== false) {
                surveyRespond(409, ['success' => false, 'message' => 'You have already submitted a response for this survey.']);
            }
            surveyRespond(500, ['success' => false, 'message' => 'Failed to save response.']);
        }

        surveyRespond(201, [
            'success' => true,
            'message' => 'Survey response submitted successfully.',
            'data' => ['response_id' => $responseId],
        ]);
    }

    surveyRespond(400, ['success' => false, 'message' => 'Invalid action. Use "confirm" or "submit".']);
}


// ══════════════════════════════════════════════════════════════════
//  ADMIN HANDLERS (Bearer token protected)
// ══════════════════════════════════════════════════════════════════

function handleAdminGet(mysqli $conn): void
{
    $surveyId = isset($_GET['survey_id']) ? (int) $_GET['survey_id'] : 0;

    // CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && $surveyId > 0) {
        $csvData = ccSurveysGenerateCsvData($conn, $surveyId);
        if (empty($csvData)) {
            surveyRespond(404, ['success' => false, 'message' => 'No data to export.']);
        }

        $survey = ccSurveysFetchById($conn, $surveyId);
        $filename = 'survey_' . ($survey['slug'] ?? $surveyId) . '_responses_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    // Stats for a survey
    if (isset($_GET['stats']) && $_GET['stats'] === '1' && $surveyId > 0) {
        $stats = ccSurveysBuildStats($conn, $surveyId);
        surveyRespond(200, ['success' => true, 'data' => $stats]);
    }

    // Single response detail
    $responseId = isset($_GET['response_id']) ? (int) $_GET['response_id'] : 0;
    if ($responseId > 0) {
        $response = ccSurveysFetchResponseById($conn, $responseId);
        if (!$response) {
            surveyRespond(404, ['success' => false, 'message' => 'Response not found.']);
        }
        surveyRespond(200, ['success' => true, 'data' => $response]);
    }

    // All responses for a survey
    if ($surveyId > 0) {
        $responses = ccSurveysFetchResponses($conn, $surveyId);
        surveyRespond(200, [
            'success' => true,
            'meta' => ['total' => count($responses)],
            'data' => $responses,
        ]);
    }

    // List all surveys (admin view with response counts)
    $surveys = ccSurveysFetchAll($conn);
    surveyRespond(200, [
        'success' => true,
        'meta' => ['total' => count($surveys)],
        'data' => $surveys,
    ]);
}

function handleAdminPost(mysqli $conn): void
{
    $input = getSurveyRequestPayload();
    $action = trim((string) ($input['action'] ?? ''));

    if ($action === 'ai_chat') {
        // Load LLM config
        $llmConfigPath = dirname(__DIR__, 2) . '/config/llm.php';
        if (file_exists($llmConfigPath)) {
            require_once $llmConfigPath;
        }

        $surveyId = (int) ($input['survey_id'] ?? 0);
        $question = trim((string) ($input['question'] ?? ''));

        if ($surveyId <= 0) {
            surveyRespond(400, ['success' => false, 'message' => 'survey_id is required.']);
        }
        if ($question === '') {
            surveyRespond(400, ['success' => false, 'message' => 'question is required.']);
        }

        $survey = ccSurveysFetchById($conn, $surveyId);
        if (!$survey) {
            surveyRespond(404, ['success' => false, 'message' => 'Survey not found.']);
        }

        $responses = ccSurveysFetchResponses($conn, $surveyId);
        $result = ccSurveysAiChat($question, $survey, $responses);

        if (!$result['success']) {
            surveyRespond(500, ['success' => false, 'message' => $result['error'] ?? 'AI chat failed.']);
        }

        surveyRespond(200, [
            'success' => true,
            'data' => [
                'answer' => $result['answer'],
                'model' => defined('GEMINI_MODEL') ? (string) GEMINI_MODEL : 'gemini-2.5-flash',
                'responses_analyzed' => count($responses),
            ],
        ]);
    }

    surveyRespond(400, ['success' => false, 'message' => 'Invalid admin action.']);
}


// ══════════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════════

function verifyBearerToken(): void
{
    if (!defined('API_BEARER_TOKEN')) {
        surveyRespond(500, ['success' => false, 'message' => 'API bearer token is not configured.']);
    }

    $authHeader = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    
    // Fallback for Apache
    if ($authHeader === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = trim((string) ($headers['Authorization'] ?? $headers['authorization'] ?? ''));
    }

    if ($authHeader === '') {
        surveyRespond(401, ['success' => false, 'message' => 'Authorization header is required.']);
    }

    if (stripos($authHeader, 'Bearer ') !== 0) {
        surveyRespond(401, ['success' => false, 'message' => 'Invalid authorization format. Use Bearer token. (Received: ' . htmlspecialchars($authHeader) . ')']);
    }

    $token = trim(substr($authHeader, 7));
    if ($token !== (string) API_BEARER_TOKEN) {
        surveyRespond(403, ['success' => false, 'message' => 'Invalid bearer token.']);
    }
}

function getSurveyRequestPayload(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            surveyRespond(400, ['success' => false, 'message' => 'Request body must be valid JSON.']);
        }
        return $decoded;
    }
    return $_POST;
}

function resolveSurveySubmitterIp(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') continue;
        $parts = explode(',', $candidate);
        $ip = trim((string) ($parts[0] ?? ''));
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }

    return '';
}

function enforceSurveyCooldown(mysqli $conn, string $email, string $submitterIp): void
{
    $emailSafe = mysqli_real_escape_string($conn, $email);
    $recentWindowSql = "created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)";

    $emailResult = mysqli_query($conn, "SELECT id FROM survey_responses WHERE email = '$emailSafe' AND $recentWindowSql LIMIT 1");
    if ($emailResult && mysqli_num_rows($emailResult) > 0) {
        surveyRespond(429, ['success' => false, 'message' => 'You recently submitted a response. Please wait a moment before trying again.']);
    }

    if ($submitterIp !== '') {
        $ipSafe = mysqli_real_escape_string($conn, $submitterIp);
        $ipResult = mysqli_query($conn, "SELECT id FROM survey_responses WHERE submitter_ip = '$ipSafe' AND $recentWindowSql LIMIT 1");
        if ($ipResult && mysqli_num_rows($ipResult) > 0) {
            surveyRespond(429, ['success' => false, 'message' => 'Please wait a moment before sending another response.']);
        }
    }
}

function sendSurveyCorsHeaders(): void
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigins = [
        'https://nivasity.com',
        'https://www.nivasity.com',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
    ];

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

function surveyRespond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
