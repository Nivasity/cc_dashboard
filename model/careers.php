<?php

function ccCareersTableExists(mysqli $conn, $tableName)
{
  static $cache = [];

  $tableName = trim((string) $tableName);
  if ($tableName === '') {
    return false;
  }

  if (array_key_exists($tableName, $cache)) {
    return $cache[$tableName];
  }

  $tableNameSafe = mysqli_real_escape_string($conn, $tableName);
  $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableNameSafe'");
  $cache[$tableName] = $result && mysqli_num_rows($result) > 0;

  return $cache[$tableName];
}

function ccCareersOpeningsTableExists(mysqli $conn)
{
  return ccCareersTableExists($conn, 'career_openings');
}

function ccCareersApplicationsTableExists(mysqli $conn)
{
  return ccCareersTableExists($conn, 'career_applications');
}

function ccCareersHistoryTableExists(mysqli $conn)
{
  return ccCareersTableExists($conn, 'career_application_status_history');
}

function ccCareersTablesReady(mysqli $conn)
{
  return ccCareersOpeningsTableExists($conn)
    && ccCareersApplicationsTableExists($conn)
    && ccCareersHistoryTableExists($conn);
}

function ccCareersTrimmed($value, $maxLength = 0)
{
  $value = trim((string) $value);
  if ($maxLength > 0 && strlen($value) > $maxLength) {
    return substr($value, 0, $maxLength);
  }

  return $value;
}

function ccCareersOpeningStatuses()
{
  return ['draft', 'published', 'archived'];
}

function ccCareersNormalizeOpeningStatus($value)
{
  $value = strtolower(trim((string) $value));
  return in_array($value, ccCareersOpeningStatuses(), true) ? $value : 'draft';
}

function ccCareersApplicationStatuses()
{
  return ['submitted', 'shortlisted', 'interview', 'offer', 'hired', 'rejected'];
}

function ccCareersNormalizeApplicationStatus($value)
{
  $value = strtolower(trim((string) $value));
  return in_array($value, ccCareersApplicationStatuses(), true) ? $value : 'submitted';
}

function ccCareersEmploymentTypes()
{
  return ['internship', 'part_time', 'full_time', 'contract', 'volunteer'];
}

function ccCareersNormalizeEmploymentType($value)
{
  $value = strtolower(trim((string) $value));
  return in_array($value, ccCareersEmploymentTypes(), true) ? $value : 'internship';
}

function ccCareersCampusOptions()
{
  return ['funaab', 'fummsa', 'other'];
}

function ccCareersNormalizeCampus($value)
{
  $value = strtolower(trim((string) $value));
  return in_array($value, ccCareersCampusOptions(), true) ? $value : 'other';
}

function ccCareersCampusLabel($value)
{
  $value = ccCareersNormalizeCampus($value);
  if ($value === 'funaab') {
    return 'FUNAAB';
  }
  if ($value === 'fummsa') {
    return 'FUMMSA';
  }

  return 'Other School';
}

function ccCareersSlugify($value)
{
  $value = strtolower(trim((string) $value));
  $value = preg_replace('/[^a-z0-9]+/', '-', $value);
  $value = trim((string) $value, '-');

  return $value !== '' ? $value : 'career-opening';
}

function ccCareersEnsureUniqueSlug(mysqli $conn, $slug, $excludeId = 0)
{
  $excludeId = (int) $excludeId;
  $baseSlug = ccCareersSlugify($slug);
  $candidate = $baseSlug;
  $suffix = 2;

  while (true) {
    $candidateSafe = mysqli_real_escape_string($conn, $candidate);
    $excludeSql = $excludeId > 0 ? 'AND id != ' . $excludeId : '';
    $result = mysqli_query(
      $conn,
      "SELECT id FROM career_openings WHERE slug = '$candidateSafe' $excludeSql LIMIT 1"
    );

    if (!$result || mysqli_num_rows($result) === 0) {
      return $candidate;
    }

    $candidate = $baseSlug . '-' . $suffix;
    $suffix++;
  }
}

function ccCareersNormalizeQuestionsFromText($value)
{
  $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
  $lines = explode("\n", $value);
  $questions = [];

  foreach ($lines as $line) {
    $line = trim((string) $line);
    if ($line === '') {
      continue;
    }

    if (strlen($line) > 255) {
      $line = substr($line, 0, 255);
    }

    $questions[] = $line;
  }

  return array_values(array_unique($questions));
}

function ccCareersEncodeQuestionsJson(array $questions)
{
  $normalized = [];
  foreach ($questions as $question) {
    $question = trim((string) $question);
    if ($question === '') {
      continue;
    }
    $normalized[] = $question;
  }

  return json_encode(array_values(array_unique($normalized)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function ccCareersDecodeQuestionsJson($value)
{
  if ($value === null || trim((string) $value) === '') {
    return [];
  }

  $decoded = json_decode((string) $value, true);
  if (!is_array($decoded)) {
    return [];
  }

  $questions = [];
  foreach ($decoded as $question) {
    $question = trim((string) $question);
    if ($question !== '') {
      $questions[] = $question;
    }
  }

  return array_values(array_unique($questions));
}

function ccCareersDecodeResponsesJson($value)
{
  if ($value === null || trim((string) $value) === '') {
    return [];
  }

  $decoded = json_decode((string) $value, true);
  if (!is_array($decoded)) {
    return [];
  }

  $responses = [];
  foreach ($decoded as $item) {
    if (!is_array($item)) {
      continue;
    }

    $question = trim((string) ($item['question'] ?? ''));
    $answer = trim((string) ($item['answer'] ?? ''));
    if ($question === '' && $answer === '') {
      continue;
    }

    $responses[] = [
      'question' => $question,
      'answer' => $answer,
    ];
  }

  return $responses;
}

function ccCareersEncodeResponsesJson(array $responses)
{
  $normalized = [];
  foreach ($responses as $item) {
    if (!is_array($item)) {
      continue;
    }

    $question = ccCareersTrimmed($item['question'] ?? '', 255);
    $answer = trim((string) ($item['answer'] ?? ''));

    if ($question === '' && $answer === '') {
      continue;
    }

    $normalized[] = [
      'question' => $question,
      'answer' => $answer,
    ];
  }

  return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function ccCareersFormatDateTimeInput($value)
{
  if (!$value) {
    return '';
  }

  $timestamp = strtotime((string) $value);
  return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function ccCareersParseDateTimeOrNull($value)
{
  $value = trim((string) $value);
  if ($value === '') {
    return null;
  }

  $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
  foreach ($formats as $format) {
    $date = DateTime::createFromFormat($format, $value);
    if ($date instanceof DateTime) {
      return $date->format('Y-m-d H:i:s');
    }
  }

  return null;
}

function ccCareersOpeningIsPublic(array $opening, $nowTs = null)
{
  $nowTs = $nowTs === null ? time() : (int) $nowTs;
  $status = ccCareersNormalizeOpeningStatus($opening['status'] ?? 'draft');
  $isPublic = (int) ($opening['is_public'] ?? 0) === 1;

  if ($status !== 'published' || !$isPublic) {
    return false;
  }

  $openAt = trim((string) ($opening['application_open_at'] ?? ''));
  $closeAt = trim((string) ($opening['application_close_at'] ?? ''));
  $openTs = $openAt !== '' ? strtotime($openAt) : false;
  $closeTs = $closeAt !== '' ? strtotime($closeAt) : false;

  if ($openTs !== false && $nowTs < $openTs) {
    return false;
  }

  if ($closeTs !== false && $nowTs > $closeTs) {
    return false;
  }

  return true;
}

function ccCareersFetchOpenings(mysqli $conn, array $filters = [])
{
  if (!ccCareersOpeningsTableExists($conn)) {
    return [];
  }

  $whereParts = ['1 = 1'];
  if (!empty($filters['status'])) {
    $status = mysqli_real_escape_string($conn, ccCareersNormalizeOpeningStatus($filters['status']));
    $whereParts[] = "co.status = '$status'";
  }

  if (array_key_exists('is_public', $filters) && $filters['is_public'] !== '' && $filters['is_public'] !== null) {
    $whereParts[] = 'co.is_public = ' . ((int) $filters['is_public'] === 1 ? '1' : '0');
  }

  $whereSql = implode(' AND ', $whereParts);
  $rows = [];
  $sql = "SELECT
            co.*, 
            CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) AS created_by_name,
            CONCAT(COALESCE(ua.first_name, ''), ' ', COALESCE(ua.last_name, '')) AS updated_by_name
          FROM career_openings co
          LEFT JOIN admins a ON a.id = co.created_by_admin_id
          LEFT JOIN admins ua ON ua.id = co.updated_by_admin_id
          WHERE $whereSql
          ORDER BY co.sort_order ASC, co.updated_at DESC, co.id DESC";
  $result = mysqli_query($conn, $sql);
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $rows[] = $row;
    }
  }

  return $rows;
}

function ccCareersFetchOpeningById(mysqli $conn, $openingId)
{
  if (!ccCareersOpeningsTableExists($conn)) {
    return null;
  }

  $openingId = (int) $openingId;
  if ($openingId <= 0) {
    return null;
  }

  $result = mysqli_query(
    $conn,
    "SELECT co.*, CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) AS created_by_name
     FROM career_openings co
     LEFT JOIN admins a ON a.id = co.created_by_admin_id
     WHERE co.id = $openingId
     LIMIT 1"
  );

  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  return null;
}

function ccCareersFetchOpeningBySlug(mysqli $conn, $slug)
{
  if (!ccCareersOpeningsTableExists($conn)) {
    return null;
  }

  $slug = ccCareersSlugify($slug);
  if ($slug === '') {
    return null;
  }

  $slugSafe = mysqli_real_escape_string($conn, $slug);
  $result = mysqli_query($conn, "SELECT * FROM career_openings WHERE slug = '$slugSafe' LIMIT 1");
  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  return null;
}

function ccCareersGenerateApplicationCode(mysqli $conn)
{
  do {
    $code = 'CAR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $codeSafe = mysqli_real_escape_string($conn, $code);
    $result = mysqli_query($conn, "SELECT id FROM career_applications WHERE code = '$codeSafe' LIMIT 1");
    $exists = $result && mysqli_num_rows($result) > 0;
  } while ($exists);

  return $code;
}

function ccCareersInsertHistory(mysqli $conn, $applicationId, $eventType, $fromStatus, $toStatus, $note, $changedByAdminId = null, $assignedAdminId = null)
{
  if (!ccCareersHistoryTableExists($conn)) {
    return false;
  }

  $applicationId = (int) $applicationId;
  if ($applicationId <= 0) {
    return false;
  }

  $eventType = ccCareersTrimmed($eventType, 30);
  if ($eventType === '') {
    $eventType = 'note';
  }

  $fromStatusValue = $fromStatus !== null && trim((string) $fromStatus) !== ''
    ? "'" . mysqli_real_escape_string($conn, ccCareersNormalizeApplicationStatus($fromStatus)) . "'"
    : 'NULL';
  $toStatusValue = $toStatus !== null && trim((string) $toStatus) !== ''
    ? "'" . mysqli_real_escape_string($conn, ccCareersNormalizeApplicationStatus($toStatus)) . "'"
    : 'NULL';
  $noteValue = trim((string) $note) !== ''
    ? "'" . mysqli_real_escape_string($conn, trim((string) $note)) . "'"
    : 'NULL';
  $changedByValue = $changedByAdminId !== null ? (int) $changedByAdminId : 'NULL';
  $assignedValue = $assignedAdminId !== null ? (int) $assignedAdminId : 'NULL';
  $eventTypeSafe = mysqli_real_escape_string($conn, $eventType);

  $sql = "INSERT INTO career_application_status_history
            (application_id, event_type, from_status, to_status, note, changed_by_admin_id, assigned_admin_id, created_at)
          VALUES
            ($applicationId, '$eventTypeSafe', $fromStatusValue, $toStatusValue, $noteValue, $changedByValue, $assignedValue, NOW())";

  return mysqli_query($conn, $sql) ? true : false;
}

function ccCareersFetchApplications(mysqli $conn, array $filters = [])
{
  if (!ccCareersApplicationsTableExists($conn)) {
    return [];
  }

  $whereParts = ['1 = 1'];
  if (!empty($filters['status'])) {
    $status = mysqli_real_escape_string($conn, ccCareersNormalizeApplicationStatus($filters['status']));
    $whereParts[] = "ca.status = '$status'";
  }

  if (!empty($filters['opening_id'])) {
    $whereParts[] = 'ca.opening_id = ' . (int) $filters['opening_id'];
  }

  if (!empty($filters['campus_affiliation'])) {
    $campus = mysqli_real_escape_string($conn, ccCareersNormalizeCampus($filters['campus_affiliation']));
    $whereParts[] = "ca.campus_affiliation = '$campus'";
  }

  if (!empty($filters['assigned_admin_id'])) {
    $whereParts[] = 'ca.assigned_admin_id = ' . (int) $filters['assigned_admin_id'];
  }

  if (!empty($filters['search'])) {
    $search = mysqli_real_escape_string($conn, trim((string) $filters['search']));
    $whereParts[] = "(
      ca.full_name LIKE '%$search%'
      OR ca.email LIKE '%$search%'
      OR ca.code LIKE '%$search%'
    )";
  }

  $whereSql = implode(' AND ', $whereParts);
  $rows = [];
  $sql = "SELECT
            ca.*, 
            co.title AS opening_title,
            co.slug AS opening_slug,
            CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) AS assigned_admin_name,
            CONCAT(COALESCE(ra.first_name, ''), ' ', COALESCE(ra.last_name, '')) AS reviewed_by_name
          FROM career_applications ca
          JOIN career_openings co ON co.id = ca.opening_id
          LEFT JOIN admins a ON a.id = ca.assigned_admin_id
          LEFT JOIN admins ra ON ra.id = ca.reviewed_by_admin_id
          WHERE $whereSql
          ORDER BY ca.updated_at DESC, ca.id DESC";
  $result = mysqli_query($conn, $sql);
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $rows[] = $row;
    }
  }

  return $rows;
}

function ccCareersFetchApplicationById(mysqli $conn, $applicationId)
{
  if (!ccCareersApplicationsTableExists($conn)) {
    return null;
  }

  $applicationId = (int) $applicationId;
  if ($applicationId <= 0) {
    return null;
  }

  $sql = "SELECT
            ca.*, 
            co.title AS opening_title,
            co.slug AS opening_slug,
            co.team,
            co.questions_json,
            CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) AS assigned_admin_name,
            CONCAT(COALESCE(ra.first_name, ''), ' ', COALESCE(ra.last_name, '')) AS reviewed_by_name
          FROM career_applications ca
          JOIN career_openings co ON co.id = ca.opening_id
          LEFT JOIN admins a ON a.id = ca.assigned_admin_id
          LEFT JOIN admins ra ON ra.id = ca.reviewed_by_admin_id
          WHERE ca.id = $applicationId
          LIMIT 1";
  $result = mysqli_query($conn, $sql);

  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  return null;
}

function ccCareersFetchApplicationHistory(mysqli $conn, $applicationId)
{
  if (!ccCareersHistoryTableExists($conn)) {
    return [];
  }

  $applicationId = (int) $applicationId;
  if ($applicationId <= 0) {
    return [];
  }

  $rows = [];
  $sql = "SELECT
            h.*, 
            CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) AS changed_by_name,
            CONCAT(COALESCE(aa.first_name, ''), ' ', COALESCE(aa.last_name, '')) AS assigned_admin_name
          FROM career_application_status_history h
          LEFT JOIN admins a ON a.id = h.changed_by_admin_id
          LEFT JOIN admins aa ON aa.id = h.assigned_admin_id
          WHERE h.application_id = $applicationId
          ORDER BY h.created_at DESC, h.id DESC";
  $result = mysqli_query($conn, $sql);
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $rows[] = $row;
    }
  }

  return $rows;
}
