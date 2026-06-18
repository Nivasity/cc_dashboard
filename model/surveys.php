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

    $surveyTitle = $survey['title'] ?? 'Survey';
    $surveyId = (int) $survey['id'];
    $totalResponses = count($responses);

    $questionsJson = json_decode($survey['questions_json'] ?? '{}', true);
    $questionMap = ccSurveysExtractQuestionMap($questionsJson ?: []);

    $schemaDesc = "Database Schema:\n";
    $schemaDesc .= "- Table `surveys` (id, slug, title, description, questions_json, status, created_at)\n";
    $schemaDesc .= "- Table `survey_responses` (id, survey_id, responses_json, created_at) Note: names, email and phone are strictly protected and omitted.\n\n";
    $schemaDesc .= "The `responses_json` column contains a JSON object mapping Question IDs to Answers. For this survey (ID {$surveyId}), the Question IDs are:\n";
    foreach ($questionMap as $qId => $qLabel) {
        $schemaDesc .= "- `{$qId}`: \"{$qLabel}\"\n";
    }
    $schemaDesc .= "\nExample query: SELECT JSON_EXTRACT(responses_json, '$.user_school') as school, COUNT(*) FROM survey_responses WHERE survey_id = {$surveyId} GROUP BY school;";

    $systemPrompt = "You are an AI Analyst for Nivasity's survey system. You have access to a database of survey responses via the `run_read_query` tool.
Instead of guessing, USE the `run_read_query` tool to execute SQL SELECT queries to fetch data before answering.
Only use `run_read_query` for SELECT queries against `surveys` or `survey_responses`.

$schemaDesc

Always formulate an accurate SQL query based on the user's question, run it using `run_read_query`, and then provide a clear, concise markdown answer using the returned data. If a query fails, adjust and retry. Do NOT return raw JSON unless asked.";

    $messages = [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $question]
            ]
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $tools = [
        [
            'functionDeclarations' => [
                [
                    'name' => 'run_read_query',
                    'description' => 'Executes a SQL SELECT query against the survey database.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'sql_query' => [
                                'type' => 'STRING',
                                'description' => 'The complete SQL SELECT query to execute.'
                            ]
                        ],
                        'required' => ['sql_query']
                    ]
                ]
            ]
        ]
    ];

    $maxLoops = 5;
    $loopCount = 0;
    
    global $conn;

    while ($loopCount < $maxLoops) {
        $loopCount++;

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $messages,
            'tools' => $tools,
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 2048,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
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
        $candidate = $decoded['candidates'][0] ?? null;
        if (!$candidate) {
            return ['success' => false, 'answer' => '', 'error' => 'No candidate returned.'];
        }

        $parts = $candidate['content']['parts'] ?? [];
        $modelMessageParts = [];
        $hasFunctionCall = false;
        $functionCallName = '';
        $functionCallArgs = [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $hasFunctionCall = true;
                $functionCallName = $part['functionCall']['name'];
                $functionCallArgs = $part['functionCall']['args'] ?? [];
                $modelMessageParts[] = ['functionCall' => $part['functionCall']];
            } elseif (isset($part['text'])) {
                $modelMessageParts[] = ['text' => $part['text']];
            }
        }

        if (!$hasFunctionCall) {
            // Final text response
            $text = '';
            foreach ($parts as $p) {
                if (isset($p['text'])) $text .= $p['text'];
            }
            return ['success' => true, 'answer' => $text, 'error' => null];
        }

        // Add model's function call to history
        $messages[] = [
            'role' => 'model',
            'parts' => $modelMessageParts
        ];

        // Execute function
        $funcResult = null;
        if ($functionCallName === 'run_read_query') {
            $sql = trim((string) ($functionCallArgs['sql_query'] ?? ''));
            if (stripos($sql, 'SELECT') !== 0) {
                $funcResult = ['error' => 'Only SELECT queries are allowed for security.'];
            } else {
                $res = mysqli_query($conn, $sql);
                if (!$res) {
                    $funcResult = ['error' => mysqli_error($conn)];
                } else {
                    $rows = [];
                    while ($r = mysqli_fetch_assoc($res)) {
                        unset($r['first_name'], $r['last_name'], $r['email'], $r['phone']);
                        $rows[] = $r;
                    }
                    $funcResult = ['rows' => $rows];
                }
            }
        } else {
            $funcResult = ['error' => 'Unknown function call'];
        }

        // Add function response to history
        $messages[] = [
            'role' => 'function',
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $functionCallName,
                        'response' => $funcResult
                    ]
                ]
            ]
        ];
    }

    return ['success' => false, 'answer' => '', 'error' => 'Agent exceeded maximum execution loops.'];
}
