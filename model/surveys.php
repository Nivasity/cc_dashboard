<?php
/**
 * Survey System — Data Access Layer
 *
 * Functions for managing surveys and survey responses.
 * Follows the same patterns as model/careers.php.
 */

// ─── Table existence checks (graceful degradation) ──────────────

function ccSurveysTableExists(mysqli $conn): bool
{
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'surveys'");
    return $result && mysqli_num_rows($result) > 0;
}

function ccSurveyResponsesTableExists(mysqli $conn): bool
{
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'survey_responses'");
    return $result && mysqli_num_rows($result) > 0;
}

function ccSurveysTablesReady(mysqli $conn): bool
{
    return ccSurveysTableExists($conn) && ccSurveyResponsesTableExists($conn);
}

// ─── Survey statuses ─────────────────────────────────────────────

function ccSurveysStatuses(): array
{
    return ['draft', 'published', 'closed', 'archived'];
}

function ccSurveysNormalizeStatus(string $status): string
{
    $status = strtolower(trim($status));
    return in_array($status, ccSurveysStatuses(), true) ? $status : 'draft';
}

function ccSurveysStatusBadge(string $status): string
{
    $status = ccSurveysNormalizeStatus($status);
    $map = [
        'draft'     => 'secondary',
        'published' => 'success',
        'closed'    => 'warning',
        'archived'  => 'dark',
    ];
    return $map[$status] ?? 'secondary';
}

// ─── Survey CRUD ─────────────────────────────────────────────────

/**
 * Fetch all surveys, optionally filtered by status.
 */
function ccSurveysFetchAll(mysqli $conn, string $status = ''): array
{
    $sql = "SELECT s.*, (SELECT COUNT(*) FROM survey_responses sr WHERE sr.survey_id = s.id) AS response_count FROM surveys s";
    if ($status !== '') {
        $statusSafe = mysqli_real_escape_string($conn, $status);
        $sql .= " WHERE s.status = '$statusSafe'";
    }
    $sql .= " ORDER BY s.created_at DESC";

    $result = mysqli_query($conn, $sql);
    if (!$result) return [];

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Fetch all published (and not expired) surveys (for public API).
 */
function ccSurveysFetchPublished(mysqli $conn): array
{
    $sql = "SELECT * FROM surveys WHERE status = 'published' AND (expiry_date IS NULL OR expiry_date > NOW()) ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    if (!$result) return [];

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Fetch a single survey by slug.
 */
function ccSurveysFetchBySlug(mysqli $conn, string $slug): ?array
{
    $slugSafe = mysqli_real_escape_string($conn, $slug);
    $result = mysqli_query($conn, "SELECT * FROM surveys WHERE slug = '$slugSafe' LIMIT 1");
    if (!$result || mysqli_num_rows($result) === 0) return null;
    return mysqli_fetch_assoc($result);
}

/**
 * Fetch a single survey by ID.
 */
function ccSurveysFetchById(mysqli $conn, int $id): ?array
{
    $result = mysqli_query($conn, "SELECT * FROM surveys WHERE id = $id LIMIT 1");
    if (!$result || mysqli_num_rows($result) === 0) return null;
    return mysqli_fetch_assoc($result);
}

/**
 * Check if a survey is currently accepting responses.
 */
function ccSurveysIsAcceptingResponses(array $survey): bool
{
    if (($survey['status'] ?? 'draft') !== 'published') return false;
    $expiryDate = $survey['expiry_date'] ?? null;
    if ($expiryDate !== null && $expiryDate !== '' && strtotime($expiryDate) < time()) return false;
    return true;
}

/**
 * Generate a URL-safe slug from a title.
 */
function ccSurveysGenerateSlug(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'survey';
}

/**
 * Ensure slug is unique by appending a numeric suffix if needed.
 */
function ccSurveysEnsureUniqueSlug(mysqli $conn, string $slug, int $excludeId = 0): string
{
    $baseSlug = $slug;
    $counter = 1;
    while (true) {
        $slugSafe = mysqli_real_escape_string($conn, $slug);
        $excludeSql = $excludeId > 0 ? " AND id != $excludeId" : '';
        $result = mysqli_query($conn, "SELECT id FROM surveys WHERE slug = '$slugSafe' $excludeSql LIMIT 1");
        if (!$result || mysqli_num_rows($result) === 0) break;
        $slug = $baseSlug . '-' . (++$counter);
    }
    return $slug;
}

/**
 * Create a new survey.
 */
function ccSurveysCreate(mysqli $conn, string $title, string $description, string $questionsJson, string $status, ?string $expiryDate, int $allowDuplicateEmail, int $adminId): int
{
    $slug = ccSurveysEnsureUniqueSlug($conn, ccSurveysGenerateSlug($title));
    $titleSafe = mysqli_real_escape_string($conn, $title);
    $descSafe = mysqli_real_escape_string($conn, $description);
    $jsonSafe = mysqli_real_escape_string($conn, $questionsJson);
    $statusSafe = mysqli_real_escape_string($conn, ccSurveysNormalizeStatus($status));
    $slugSafe = mysqli_real_escape_string($conn, $slug);
    $expiryValue = ($expiryDate !== null && $expiryDate !== '') ? "'" . mysqli_real_escape_string($conn, $expiryDate) . "'" : 'NULL';
    $allowDup = $allowDuplicateEmail ? 1 : 0;

    $sql = "INSERT INTO surveys (slug, title, description, questions_json, status, expiry_date, allow_duplicate_email, created_by_admin_id, updated_by_admin_id, created_at, updated_at)
            VALUES ('$slugSafe', '$titleSafe', '$descSafe', '$jsonSafe', '$statusSafe', $expiryValue, $allowDup, $adminId, $adminId, NOW(), NOW())";

    if (!mysqli_query($conn, $sql)) {
        return 0;
    }

    return (int) mysqli_insert_id($conn);
}

/**
 * Update a survey.
 */
function ccSurveysUpdate(mysqli $conn, int $id, string $title, string $description, string $questionsJson, string $status, ?string $expiryDate, int $allowDuplicateEmail, int $adminId): bool
{
    $slug = ccSurveysEnsureUniqueSlug($conn, ccSurveysGenerateSlug($title), $id);
    $titleSafe = mysqli_real_escape_string($conn, $title);
    $descSafe = mysqli_real_escape_string($conn, $description);
    $jsonSafe = mysqli_real_escape_string($conn, $questionsJson);
    $statusSafe = mysqli_real_escape_string($conn, ccSurveysNormalizeStatus($status));
    $slugSafe = mysqli_real_escape_string($conn, $slug);
    $expiryValue = ($expiryDate !== null && $expiryDate !== '') ? "'" . mysqli_real_escape_string($conn, $expiryDate) . "'" : 'NULL';
    $allowDup = $allowDuplicateEmail ? 1 : 0;

    $sql = "UPDATE surveys SET
                slug = '$slugSafe',
                title = '$titleSafe',
                description = '$descSafe',
                questions_json = '$jsonSafe',
                status = '$statusSafe',
                expiry_date = $expiryValue,
                allow_duplicate_email = $allowDup,
                updated_by_admin_id = $adminId,
                updated_at = NOW()
            WHERE id = $id LIMIT 1";

    return (bool) mysqli_query($conn, $sql);
}

/**
 * Update only the status of a survey.
 */
function ccSurveysUpdateStatus(mysqli $conn, int $id, string $status, int $adminId): bool
{
    $statusSafe = mysqli_real_escape_string($conn, ccSurveysNormalizeStatus($status));
    return (bool) mysqli_query($conn, "UPDATE surveys SET status = '$statusSafe', updated_by_admin_id = $adminId, updated_at = NOW() WHERE id = $id LIMIT 1");
}

/**
 * Delete a survey and its responses.
 */
function ccSurveysDelete(mysqli $conn, int $id): bool
{
    mysqli_query($conn, "DELETE FROM survey_responses WHERE survey_id = $id");
    return (bool) mysqli_query($conn, "DELETE FROM surveys WHERE id = $id LIMIT 1");
}

// ─── Survey Response CRUD ────────────────────────────────────────

/**
 * Fetch all responses for a survey.
 */
function ccSurveysFetchResponses(mysqli $conn, int $surveyId): array
{
    $result = mysqli_query($conn, "SELECT * FROM survey_responses WHERE survey_id = $surveyId ORDER BY created_at DESC");
    if (!$result) return [];

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Fetch a single response by ID.
 */
function ccSurveysFetchResponseById(mysqli $conn, int $responseId): ?array
{
    $result = mysqli_query($conn, "SELECT sr.*, s.title AS survey_title, s.slug AS survey_slug, s.questions_json AS survey_questions_json FROM survey_responses sr JOIN surveys s ON s.id = sr.survey_id WHERE sr.id = $responseId LIMIT 1");
    if (!$result || mysqli_num_rows($result) === 0) return null;
    return mysqli_fetch_assoc($result);
}

/**
 * Check if an email has already responded to a survey.
 */
function ccSurveysCheckDuplicateEmail(mysqli $conn, int $surveyId, string $email): bool
{
    $emailSafe = mysqli_real_escape_string($conn, strtolower(trim($email)));
    $result = mysqli_query($conn, "SELECT id FROM survey_responses WHERE survey_id = $surveyId AND email = '$emailSafe' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

/**
 * Insert a new survey response.
 */
function ccSurveysInsertResponse(mysqli $conn, int $surveyId, string $firstName, string $lastName, string $email, string $phone, string $responsesJson, string $submitterIp, string $userAgent): int
{
    $surveyIdSafe = (int) $surveyId;
    $firstNameSafe = mysqli_real_escape_string($conn, $firstName);
    $lastNameSafe = mysqli_real_escape_string($conn, $lastName);
    $emailSafe = mysqli_real_escape_string($conn, strtolower(trim($email)));
    $phoneSafe = mysqli_real_escape_string($conn, $phone);
    $responsesJsonSafe = mysqli_real_escape_string($conn, $responsesJson);
    $ipValue = $submitterIp !== '' ? "'" . mysqli_real_escape_string($conn, $submitterIp) . "'" : 'NULL';
    $uaValue = $userAgent !== '' ? "'" . mysqli_real_escape_string($conn, substr($userAgent, 0, 500)) . "'" : 'NULL';

    $sql = "INSERT INTO survey_responses (survey_id, first_name, last_name, email, phone, responses_json, submitter_ip, user_agent, created_at)
            VALUES ($surveyIdSafe, '$firstNameSafe', '$lastNameSafe', '$emailSafe', '$phoneSafe', '$responsesJsonSafe', $ipValue, $uaValue, NOW())";

    if (!mysqli_query($conn, $sql)) {
        return 0;
    }

    return (int) mysqli_insert_id($conn);
}

// ─── Analytics / Stats ───────────────────────────────────────────

/**
 * Build analytics stats for a survey.
 */
function ccSurveysBuildStats(mysqli $conn, int $surveyId): array
{
    $stats = [
        'total' => 0,
        'today' => 0,
        'this_week' => 0,
        'this_month' => 0,
    ];

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM survey_responses WHERE survey_id = $surveyId");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['total'] = (int) $row['cnt'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM survey_responses WHERE survey_id = $surveyId AND DATE(created_at) = CURDATE()");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['today'] = (int) $row['cnt'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM survey_responses WHERE survey_id = $surveyId AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['this_week'] = (int) $row['cnt'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM survey_responses WHERE survey_id = $surveyId AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['this_month'] = (int) $row['cnt'];
    }

    return $stats;
}

/**
 * Build global survey stats (across all surveys).
 */
function ccSurveysBuildGlobalStats(mysqli $conn): array
{
    $stats = [
        'total_surveys' => 0,
        'published_surveys' => 0,
        'total_responses' => 0,
        'responses_today' => 0,
        'responses_this_week' => 0,
    ];

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM surveys");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['total_surveys'] = (int) $row['cnt'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM surveys WHERE status = 'published'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['published_surveys'] = (int) $row['cnt'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM survey_responses");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['total_responses'] = (int) $row['cnt'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM survey_responses WHERE DATE(created_at) = CURDATE()");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['responses_today'] = (int) $row['cnt'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM survey_responses WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['responses_this_week'] = (int) $row['cnt'];
    }

    return $stats;
}

// ─── CSV Export ──────────────────────────────────────────────────

/**
 * Generate CSV data for a survey's responses.
 * Returns an array of rows (each row is an array of values).
 * First row is the header.
 */
function ccSurveysGenerateCsvData(mysqli $conn, int $surveyId): array
{
    $survey = ccSurveysFetchById($conn, $surveyId);
    if (!$survey) return [];

    $responses = ccSurveysFetchResponses($conn, $surveyId);
    if (empty($responses)) return [];

    // Parse the survey questions to get question IDs and labels
    $questionsJson = json_decode($survey['questions_json'] ?? '{}', true);
    $questionMap = ccSurveysExtractQuestionMap($questionsJson);

    // Build header row
    $header = ['#', 'First Name', 'Last Name', 'Email', 'Phone', 'Submitted At'];
    foreach ($questionMap as $qId => $qLabel) {
        $header[] = $qLabel;
    }

    $rows = [$header];

    foreach ($responses as $index => $response) {
        $answersJson = json_decode($response['responses_json'] ?? '{}', true);
        if (!is_array($answersJson)) $answersJson = [];

        $row = [
            $index + 1,
            $response['first_name'] ?? '',
            $response['last_name'] ?? '',
            $response['email'] ?? '',
            $response['phone'] ?? '',
            $response['created_at'] ?? '',
        ];

        foreach ($questionMap as $qId => $qLabel) {
            $answer = $answersJson[$qId] ?? '';
            if (is_array($answer)) {
                $answer = implode(', ', $answer);
            }
            $row[] = (string) $answer;
        }

        $rows[] = $row;
    }

    return $rows;
}

/**
 * Extract a flat map of question_id => question_label from the survey JSON.
 * Supports both flat questions and sectioned formats.
 */
function ccSurveysExtractQuestionMap(array $questionsJson): array
{
    $map = [];

    // Sectioned format
    if (isset($questionsJson['sections']) && is_array($questionsJson['sections'])) {
        foreach ($questionsJson['sections'] as $section) {
            if (!isset($section['questions']) || !is_array($section['questions'])) continue;
            foreach ($section['questions'] as $q) {
                if (isset($q['id'], $q['question'])) {
                    $map[$q['id']] = $q['question'];
                }
            }
        }
        return $map;
    }

    // Flat format
    if (isset($questionsJson['questions']) && is_array($questionsJson['questions'])) {
        foreach ($questionsJson['questions'] as $q) {
            if (isset($q['id'], $q['question'])) {
                $map[$q['id']] = $q['question'];
            }
        }
    }

    return $map;
}

// ─── AI Chat (Gemini) ────────────────────────────────────────────

/**
 * Send a question to Gemini about survey responses.
 * Requires config/llm.php to be loaded with GEMINI_API_KEY and GEMINI_MODEL defined.
 *
 * @param string $question The user's question about the survey
 * @param array $survey The survey row from DB
 * @param array $responses All response rows for that survey
 * @return array ['success' => bool, 'answer' => string, 'error' => string|null]
 */
function ccSurveysAiChat(string $question, array $survey, array $responses): array
{
    if (!defined('GEMINI_API_KEY') || trim((string) GEMINI_API_KEY) === '' || GEMINI_API_KEY === 'your_gemini_api_key_here') {
        return ['success' => false, 'answer' => '', 'error' => 'Gemini API key is not configured. Please set up config/llm.php.'];
    }

    $model = defined('GEMINI_MODEL') ? (string) GEMINI_MODEL : 'gemini-2.5-flash';
    $apiKey = (string) GEMINI_API_KEY;

    // Build context from survey data
    $surveyTitle = $survey['title'] ?? 'Survey';
    $totalResponses = count($responses);

    // Parse questions for context
    $questionsJson = json_decode($survey['questions_json'] ?? '{}', true);
    $questionMap = ccSurveysExtractQuestionMap($questionsJson ?: []);

    // Build a summary of all responses
    $responseSummary = [];
    foreach ($responses as $idx => $resp) {
        $answersJson = json_decode($resp['responses_json'] ?? '{}', true);
        if (!is_array($answersJson)) $answersJson = [];

        $entry = [
            'respondent' => ($resp['first_name'] ?? '') . ' ' . ($resp['last_name'] ?? ''),
            'email' => $resp['email'] ?? '',
            'submitted_at' => $resp['created_at'] ?? '',
            'answers' => [],
        ];

        foreach ($questionMap as $qId => $qLabel) {
            $answer = $answersJson[$qId] ?? '';
            if (is_array($answer)) {
                $answer = implode(', ', $answer);
            }
            $entry['answers'][$qLabel] = (string) $answer;
        }

        $responseSummary[] = $entry;
    }

    $systemPrompt = "You are an analytics assistant for Nivasity's survey system. You analyze survey responses and provide clear, actionable insights. Be concise but thorough. Use numbers and percentages when relevant. Format responses with markdown.";

    $contextPrompt = "Here is the survey data you have access to:\n\n"
        . "**Survey Title:** {$surveyTitle}\n"
        . "**Total Responses:** {$totalResponses}\n\n"
        . "**Questions in this survey:**\n";

    foreach ($questionMap as $qId => $qLabel) {
        $contextPrompt .= "- [{$qId}] {$qLabel}\n";
    }

    $contextPrompt .= "\n**All Response Data (JSON):**\n```json\n" . json_encode($responseSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n```\n\n";
    $contextPrompt .= "**User's Question:** {$question}";

    // Call Gemini API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'system_instruction' => [
            'parts' => [
                ['text' => $systemPrompt],
            ],
        ],
        'contents' => [
            [
                'parts' => [
                    ['text' => $contextPrompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 2048,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return ['success' => false, 'answer' => '', 'error' => 'Network error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        $decoded = json_decode($rawResponse, true);
        $errMsg = $decoded['error']['message'] ?? "Gemini API returned HTTP {$httpCode}";
        return ['success' => false, 'answer' => '', 'error' => $errMsg];
    }

    $decoded = json_decode($rawResponse, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if ($text === '') {
        return ['success' => false, 'answer' => '', 'error' => 'Gemini returned an empty response.'];
    }

    return ['success' => true, 'answer' => $text, 'error' => null];
}
