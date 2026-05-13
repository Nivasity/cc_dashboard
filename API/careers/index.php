<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
sendCareerCorsHeaders();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/model/careers.php';
require_once dirname(__DIR__, 2) . '/model/mail.php';

if (!defined('DB_USERNAME') || !defined('DB_PASSWORD')) {
    careersRespond(500, ['success' => false, 'message' => 'Database credentials are not configured.']);
}

$dbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? (string) DB_NAME : 'niverpay_db';

$conn = mysqli_connect($dbHost, (string) DB_USERNAME, (string) DB_PASSWORD, $dbName);
if (!$conn) {
    careersRespond(500, ['success' => false, 'message' => 'Failed to connect to database.']);
}
mysqli_set_charset($conn, 'utf8mb4');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    handleCareerOpeningsGet($conn);
}

if ($method === 'POST') {
    handleCareerApplicationSubmit($conn);
}

careersRespond(405, ['success' => false, 'message' => 'Method not allowed. Use GET, POST, or OPTIONS.']);

function handleCareerOpeningsGet(mysqli $conn): void
{
    if (!ccCareersOpeningsTableExists($conn)) {
        careersRespond(200, [
            'success' => true,
            'meta' => [
                'total' => 0,
                'count' => 0,
            ],
            'data' => [],
        ]);
    }

    $openingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $openingSlug = trim((string) ($_GET['slug'] ?? ''));
    $nowTs = time();

    if ($openingId > 0 || $openingSlug !== '') {
        $opening = $openingId > 0
            ? ccCareersFetchOpeningById($conn, $openingId)
            : ccCareersFetchOpeningBySlug($conn, $openingSlug);

        if (!$opening || !ccCareersOpeningIsPublic($opening, $nowTs)) {
            careersRespond(404, ['success' => false, 'message' => 'Career opening not found.']);
        }

        careersRespond(200, [
            'success' => true,
            'data' => normalizeCareerOpeningPayload($opening),
        ]);
    }

    $rows = ccCareersFetchOpenings($conn, [
        'status' => 'published',
        'is_public' => 1,
    ]);

    $data = [];
    foreach ($rows as $row) {
        if (!ccCareersOpeningIsPublic($row, $nowTs)) {
            continue;
        }

        $data[] = normalizeCareerOpeningPayload($row);
    }

    careersRespond(200, [
        'success' => true,
        'meta' => [
            'total' => count($data),
            'count' => count($data),
        ],
        'data' => $data,
    ]);
}

function handleCareerApplicationSubmit(mysqli $conn): void
{
    if (!ccCareersTablesReady($conn)) {
        careersRespond(503, ['success' => false, 'message' => 'Careers application tables are not available yet.']);
    }

    $input = getCareerRequestPayload();
    validateCareerPayloadKeys($input, [
        'opening_id',
        'opening_slug',
        'campus_affiliation',
        'school_name',
        'level_text',
        'full_name',
        'email',
        'phone',
        'portfolio_url',
        'availability_text',
        'motivation_text',
        'willing_to_join',
        'responses',
        'website',
    ]);

    $honeypot = trim((string) ($input['website'] ?? ''));
    if ($honeypot !== '') {
        careersRespond(400, ['success' => false, 'message' => 'Invalid submission received.']);
    }

    $openingId = isset($input['opening_id']) ? (int) $input['opening_id'] : 0;
    $openingSlug = trim((string) ($input['opening_slug'] ?? ''));
    if ($openingId <= 0 && $openingSlug === '') {
        careersRespond(400, ['success' => false, 'message' => 'Select a valid career opening.']);
    }

    $opening = $openingId > 0
        ? ccCareersFetchOpeningById($conn, $openingId)
        : ccCareersFetchOpeningBySlug($conn, $openingSlug);
    if (!$opening || !ccCareersOpeningIsPublic($opening)) {
        careersRespond(400, ['success' => false, 'message' => 'The selected career opening is not accepting applications right now.']);
    }

    $campusAffiliation = ccCareersNormalizeCampus($input['campus_affiliation'] ?? 'other');
    $schoolName = ccCareersTrimmed($input['school_name'] ?? '', 160);
    if ($campusAffiliation === 'other' && $schoolName === '') {
        careersRespond(400, ['success' => false, 'message' => 'School name is required when you select Other school.']);
    }
    if ($campusAffiliation !== 'other') {
        $schoolName = '';
    }

    $levelText = ccCareersTrimmed($input['level_text'] ?? '', 30);
    if ($levelText === '') {
        careersRespond(400, ['success' => false, 'message' => 'Your current level is required.']);
    }
    if (preg_match('/^100/i', $levelText)) {
        careersRespond(400, ['success' => false, 'message' => 'This internship is only open to 200 level students and above.']);
    }

    $fullName = ccCareersTrimmed($input['full_name'] ?? '', 160);
    if ($fullName === '') {
        careersRespond(400, ['success' => false, 'message' => 'Full name is required.']);
    }

    $email = strtolower(ccCareersTrimmed($input['email'] ?? '', 180));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        careersRespond(400, ['success' => false, 'message' => 'A valid email address is required.']);
    }

    $phone = ccCareersTrimmed($input['phone'] ?? '', 30);
    if ($phone === '') {
        careersRespond(400, ['success' => false, 'message' => 'Phone number is required.']);
    }

    $portfolioUrl = ccCareersTrimmed($input['portfolio_url'] ?? '', 255);
    if ($portfolioUrl !== '' && !filter_var($portfolioUrl, FILTER_VALIDATE_URL)) {
        careersRespond(400, ['success' => false, 'message' => 'Portfolio link must be a valid URL.']);
    }

    $availabilityText = ccCareersTrimmed($input['availability_text'] ?? '', 160);
    if ($availabilityText === '') {
        careersRespond(400, ['success' => false, 'message' => 'Availability or expected start date is required.']);
    }

    $motivationText = trim((string) ($input['motivation_text'] ?? ''));
    if ($motivationText === '') {
        careersRespond(400, ['success' => false, 'message' => 'Tell us why you want to join the Nivasity team.']);
    }

    $willingToJoin = filter_var($input['willing_to_join'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $willingToJoin = $willingToJoin === null ? 1 : ($willingToJoin ? 1 : 0);

    $openingQuestions = ccCareersDecodeQuestionsJson($opening['questions_json'] ?? null);
    $responses = normalizeCareerResponses($input['responses'] ?? [], $openingQuestions);

    $submitterIp = resolveCareerSubmitterIp();
    enforceCareerCooldown($conn, $email, $submitterIp);

    $code = ccCareersGenerateApplicationCode($conn);
    $codeSafe = mysqli_real_escape_string($conn, $code);
    $campusSafe = mysqli_real_escape_string($conn, $campusAffiliation);
    $levelSafe = mysqli_real_escape_string($conn, $levelText);
    $fullNameSafe = mysqli_real_escape_string($conn, $fullName);
    $emailSafe = mysqli_real_escape_string($conn, $email);
    $phoneSafe = mysqli_real_escape_string($conn, $phone);
    $portfolioValue = $portfolioUrl !== '' ? "'" . mysqli_real_escape_string($conn, $portfolioUrl) . "'" : 'NULL';
    $availabilitySafe = mysqli_real_escape_string($conn, $availabilityText);
    $motivationValue = $motivationText !== '' ? "'" . mysqli_real_escape_string($conn, $motivationText) . "'" : 'NULL';
    $schoolValue = $schoolName !== '' ? "'" . mysqli_real_escape_string($conn, $schoolName) . "'" : 'NULL';
    $responsesJson = ccCareersEncodeResponsesJson($responses);
    $responsesValue = trim((string) $responsesJson) !== '' ? "'" . mysqli_real_escape_string($conn, (string) $responsesJson) . "'" : 'NULL';
    $submitterIpValue = $submitterIp !== '' ? "'" . mysqli_real_escape_string($conn, $submitterIp) . "'" : 'NULL';
    $openingId = (int) ($opening['id'] ?? 0);

    mysqli_begin_transaction($conn);
    try {
        $sql = "INSERT INTO career_applications
                  (code, opening_id, campus_affiliation, school_name, level_text, full_name, email, phone, portfolio_url, availability_text, motivation_text, willing_to_join, responses_json, status, submitter_ip, created_at, updated_at)
                VALUES
                  ('$codeSafe', $openingId, '$campusSafe', $schoolValue, '$levelSafe', '$fullNameSafe', '$emailSafe', '$phoneSafe', $portfolioValue, '$availabilitySafe', $motivationValue, $willingToJoin, $responsesValue, 'submitted', $submitterIpValue, NOW(), NOW())";
        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException('Failed to save career application.');
        }

        $applicationId = (int) mysqli_insert_id($conn);
        if (!ccCareersInsertHistory($conn, $applicationId, 'submitted', null, 'submitted', 'Application submitted via careers page.')) {
            throw new RuntimeException('Failed to record career application history.');
        }

        mysqli_commit($conn);
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
        careersRespond(500, ['success' => false, 'message' => 'Unable to submit application right now.', 'error' => $exception->getMessage()]);
    }

    $delivery = sendCareerApplicationEmails($opening, [
        'code' => $code,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'campus_affiliation' => $campusAffiliation,
        'school_name' => $schoolName,
        'level_text' => $levelText,
        'portfolio_url' => $portfolioUrl,
        'availability_text' => $availabilityText,
        'motivation_text' => $motivationText,
        'responses' => $responses,
    ]);

    careersRespond(201, [
        'success' => true,
        'message' => 'Career application submitted successfully.',
        'data' => [
            'application_code' => $code,
            'opening' => [
                'id' => (int) ($opening['id'] ?? 0),
                'slug' => (string) ($opening['slug'] ?? ''),
                'title' => (string) ($opening['title'] ?? ''),
            ],
        ],
        'delivery' => $delivery,
    ]);
}

function normalizeCareerOpeningPayload(array $opening): array
{
    return [
        'id' => (int) ($opening['id'] ?? 0),
        'slug' => (string) ($opening['slug'] ?? ''),
        'title' => (string) ($opening['title'] ?? ''),
        'team' => (string) ($opening['team'] ?? ''),
        'summary' => (string) ($opening['summary'] ?? ''),
        'description' => (string) ($opening['description'] ?? ''),
        'employment_type' => (string) ($opening['employment_type'] ?? 'internship'),
        'internship_duration' => (string) ($opening['internship_duration'] ?? ''),
        'eligibility_text' => (string) ($opening['eligibility_text'] ?? ''),
        'application_open_at' => (string) ($opening['application_open_at'] ?? ''),
        'application_close_at' => (string) ($opening['application_close_at'] ?? ''),
        'questions' => ccCareersDecodeQuestionsJson($opening['questions_json'] ?? null),
    ];
}

function normalizeCareerResponses($rawResponses, array $openingQuestions): array
{
    if (is_string($rawResponses) && trim($rawResponses) !== '') {
        $decoded = json_decode($rawResponses, true);
        if (is_array($decoded)) {
            $rawResponses = $decoded;
        }
    }

    if (!is_array($rawResponses)) {
        $rawResponses = [];
    }

    $responses = [];
    if ($openingQuestions === []) {
        return $responses;
    }

    foreach ($openingQuestions as $index => $question) {
        $answer = '';
        if (isset($rawResponses[$index]) && is_array($rawResponses[$index])) {
            $answer = trim((string) ($rawResponses[$index]['answer'] ?? ''));
        } elseif (isset($rawResponses[$index]) && !is_array($rawResponses[$index])) {
            $answer = trim((string) $rawResponses[$index]);
        }

        if ($answer === '') {
            careersRespond(400, ['success' => false, 'message' => 'Answer all role-specific questions before submitting.']);
        }

        $responses[] = [
            'question' => ccCareersTrimmed($question, 255),
            'answer' => $answer,
        ];
    }

    return $responses;
}

function sendCareerApplicationEmails(array $opening, array $application): array
{
    $applicantStatus = 'skipped';
    $internalStatuses = [];

    $openingTitle = trim((string) ($opening['title'] ?? 'Nivasity Internship'));
    $applicantName = trim((string) ($application['full_name'] ?? 'there'));
    $applicantEmail = trim((string) ($application['email'] ?? ''));
    $applicationCode = trim((string) ($application['code'] ?? ''));

    if ($applicantEmail !== '') {
        $applicantSubject = 'Application Received - ' . $openingTitle;
        $applicantBody = "Hello {$applicantName},<br><br>"
            . "We have received your application for <strong>{$openingTitle}</strong> on Nivasity Careers.<br><br>"
            . "Your application code is <strong>{$applicationCode}</strong>.<br><br>"
            . "Our team will review your submission and reach out if you are shortlisted.<br><br>"
            . 'Best regards,<br><strong>Nivasity Team</strong>';
        $applicantStatus = sendMail($applicantSubject, $applicantBody, $applicantEmail);
    }

    $internalSubject = 'New Careers Application - ' . $openingTitle;
    $internalBody = buildCareerInternalEmailBody($opening, $application);
    foreach (getCareerNotificationRecipients() as $recipient) {
        $internalStatuses[$recipient] = sendMail($internalSubject, $internalBody, $recipient);
    }

    return [
        'applicant' => $applicantStatus,
        'internal' => $internalStatuses,
    ];
}

function buildCareerInternalEmailBody(array $opening, array $application): string
{
    $body = 'A new application has been submitted on Nivasity Careers.<br><br>';
    $body .= '<strong>Role:</strong> ' . htmlspecialchars((string) ($opening['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>';
    $body .= '<strong>Application Code:</strong> ' . htmlspecialchars((string) ($application['code'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>';
    $body .= '<strong>Name:</strong> ' . htmlspecialchars((string) ($application['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>';
    $body .= '<strong>Email:</strong> ' . htmlspecialchars((string) ($application['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>';
    $body .= '<strong>Phone:</strong> ' . htmlspecialchars((string) ($application['phone'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>';
    $body .= '<strong>Campus:</strong> ' . htmlspecialchars(ccCareersCampusLabel($application['campus_affiliation'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (trim((string) ($application['school_name'] ?? '')) !== '') {
        $body .= '<br><strong>School:</strong> ' . htmlspecialchars((string) $application['school_name'], ENT_QUOTES, 'UTF-8');
    }

    $body .= '<br><strong>Level:</strong> ' . htmlspecialchars((string) ($application['level_text'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>';
    $body .= '<strong>Availability:</strong> ' . htmlspecialchars((string) ($application['availability_text'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>';

    if (trim((string) ($application['portfolio_url'] ?? '')) !== '') {
        $safeUrl = htmlspecialchars((string) $application['portfolio_url'], ENT_QUOTES, 'UTF-8');
        $body .= '<strong>Portfolio:</strong> <a href="' . $safeUrl . '">' . $safeUrl . '</a><br>';
    }

    $body .= '<br><strong>Why Nivasity?</strong><br>' . nl2br(htmlspecialchars((string) ($application['motivation_text'] ?? ''), ENT_QUOTES, 'UTF-8'));

    $responses = is_array($application['responses'] ?? null) ? $application['responses'] : [];
    if ($responses !== []) {
        $body .= '<br><br><strong>Role-Specific Responses</strong><br>';
        foreach ($responses as $item) {
            $question = htmlspecialchars((string) ($item['question'] ?? ''), ENT_QUOTES, 'UTF-8');
            $answer = nl2br(htmlspecialchars((string) ($item['answer'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $body .= '<br><strong>' . $question . '</strong><br>' . $answer . '<br>';
        }
    }

    return $body;
}

function getCareerNotificationRecipients(): array
{
    $recipients = [];
    if (defined('CAREERS_NOTIFICATION_EMAILS')) {
        $configured = explode(',', (string) CAREERS_NOTIFICATION_EMAILS);
        foreach ($configured as $email) {
            $email = trim($email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }
    }

    if ($recipients === []) {
        $recipients[] = 'support@nivasity.com';
    }

    return array_values(array_unique($recipients));
}

function enforceCareerCooldown(mysqli $conn, string $email, string $submitterIp): void
{
    $emailSafe = mysqli_real_escape_string($conn, $email);
    $recentWindowSql = "created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)";

    $emailResult = mysqli_query(
        $conn,
        "SELECT id FROM career_applications WHERE email = '$emailSafe' AND $recentWindowSql LIMIT 1"
    );
    if ($emailResult && mysqli_num_rows($emailResult) > 0) {
        careersRespond(429, ['success' => false, 'message' => 'You recently submitted an application. Please wait a few minutes before trying again.']);
    }

    if ($submitterIp !== '') {
        $ipSafe = mysqli_real_escape_string($conn, $submitterIp);
        $ipResult = mysqli_query(
            $conn,
            "SELECT id FROM career_applications WHERE submitter_ip = '$ipSafe' AND $recentWindowSql LIMIT 1"
        );
        if ($ipResult && mysqli_num_rows($ipResult) > 0) {
            careersRespond(429, ['success' => false, 'message' => 'Please wait a few minutes before sending another application from this device.']);
        }
    }
}

function resolveCareerSubmitterIp(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $parts = explode(',', $candidate);
        $ip = trim((string) ($parts[0] ?? ''));
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '';
}

function getCareerRequestPayload(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            careersRespond(400, ['success' => false, 'message' => 'Request body must be valid JSON.']);
        }

        return $decoded;
    }

    return $_POST;
}

function validateCareerPayloadKeys(array $input, array $allowedKeys): void
{
    $unsupported = [];
    foreach (array_keys($input) as $key) {
        if (!in_array($key, $allowedKeys, true)) {
            $unsupported[] = $key;
        }
    }

    if ($unsupported !== []) {
        careersRespond(400, [
            'success' => false,
            'message' => 'Unsupported field(s) in request payload.',
            'unsupported_fields' => array_values($unsupported),
            'expected_fields' => array_values($allowedKeys),
        ]);
    }
}

function sendCareerCorsHeaders(): void
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigins = [
        'https://nivasity.com',
        'https://www.nivasity.com',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
    ];

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

function careersRespond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
