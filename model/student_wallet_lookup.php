<?php
session_start();
require_once(__DIR__ . '/config.php');

header('Content-Type: application/json');

function ccStudentWalletRespondJson($code, $payload) {
  http_response_code($code);
  echo json_encode($payload);
  exit();
}

function ccStudentWalletTableExists($conn, $tableName) {
  static $cache = [];

  if (array_key_exists($tableName, $cache)) {
    return $cache[$tableName];
  }

  $tableName = trim((string) $tableName);
  if ($tableName === '') {
    $cache[$tableName] = false;
    return false;
  }

  $safeTableName = mysqli_real_escape_string($conn, $tableName);
  $result = mysqli_query($conn, "SHOW TABLES LIKE '$safeTableName'");
  $cache[$tableName] = $result && mysqli_num_rows($result) > 0;

  return $cache[$tableName];
}

function ccStudentWalletIsValidDate($value) {
  $value = trim((string) $value);
  if ($value === '') {
    return false;
  }

  $date = DateTime::createFromFormat('Y-m-d', $value);
  return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function ccStudentWalletFormatDate($value) {
  $value = trim((string) $value);
  if ($value === '') {
    return '-';
  }

  $timestamp = strtotime($value);
  if ($timestamp === false) {
    return $value;
  }

  return date('d M Y, h:i A', $timestamp);
}

function ccStudentWalletStatusBadge($status) {
  $status = strtolower(trim((string) $status));

  if (in_array($status, ['active', 'posted', 'successful', 'verified'], true)) {
    return 'success';
  }

  if (in_array($status, ['pending', 'processing'], true)) {
    return 'warning';
  }

  if (in_array($status, ['suspended', 'closed', 'failed', 'reversed', 'inactive'], true)) {
    return 'danger';
  }

  return 'secondary';
}

function ccStudentWalletEntryTypeBadge($entryType) {
  $entryType = strtolower(trim((string) $entryType));

  if (in_array($entryType, ['credit', 'refund'], true)) {
    return 'success';
  }

  if (in_array($entryType, ['debit', 'fee'], true)) {
    return 'danger';
  }

  return 'secondary';
}

function ccStudentWalletBindParams($stmt, $types, array &$params) {
  $bindValues = [$stmt, $types];

  foreach ($params as $index => $value) {
    $bindValues[] = &$params[$index];
  }

  return call_user_func_array('mysqli_stmt_bind_param', $bindValues);
}

function ccStudentWalletFindStudent($conn, $lookup, $adminRole, $adminSchool, $adminFaculty) {
  $lookup = trim((string) $lookup);
  if ($lookup === '') {
    return null;
  }

  $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender, u.status AS user_status,
                 u.matric_no, u.school, u.dept, u.role, u.adm_year,
                 s.name AS school_name, d.name AS dept_name, f.name AS faculty_name
          FROM users u
          LEFT JOIN schools s ON s.id = u.school
          LEFT JOIN depts d ON d.id = u.dept
          LEFT JOIN faculties f ON f.id = d.faculty_id
          WHERE (LOWER(u.email) = LOWER(?) OR u.matric_no = ?)";

  if ((int) $adminRole === 5 && $adminSchool > 0) {
    $sql .= " AND u.school = " . (int) $adminSchool;
  }

  if ((int) $adminRole === 5 && $adminFaculty > 0) {
    $sql .= " AND d.faculty_id = " . (int) $adminFaculty;
  }

  $sql .= " LIMIT 1";

  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return null;
  }

  mysqli_stmt_bind_param($stmt, 'ss', $lookup, $lookup);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $student = $result ? mysqli_fetch_assoc($result) : null;
  mysqli_stmt_close($stmt);

  return $student ?: null;
}

function ccStudentWalletFetchWallet($conn, $userId) {
  $userId = (int) $userId;
  if ($userId <= 0) {
    return null;
  }

  $sql = "SELECT w.*, va.provider, va.provider_account_id, va.provider_customer_code, va.account_name,
                 va.account_number, va.bank_name, va.bank_slug, va.status AS virtual_account_status
          FROM user_wallets w
          LEFT JOIN wallet_virtual_accounts va ON va.wallet_id = w.id
          WHERE w.user_id = ?
          LIMIT 1";
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return null;
  }

  mysqli_stmt_bind_param($stmt, 'i', $userId);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $wallet = $result ? mysqli_fetch_assoc($result) : null;
  mysqli_stmt_close($stmt);

  return $wallet ?: null;
}

function ccStudentWalletFetchOverview($conn, $walletId) {
  $walletId = (int) $walletId;
  $overview = [
    'entries_count' => 0,
    'credits_total' => 0,
    'debits_total' => 0,
    'refunds_total' => 0,
    'fees_total' => 0,
  ];

  if ($walletId <= 0) {
    return $overview;
  }

  $sql = "SELECT
            COUNT(*) AS entries_count,
            COALESCE(SUM(CASE WHEN entry_type IN ('credit', 'refund') THEN amount ELSE 0 END), 0) AS credits_total,
            COALESCE(SUM(CASE WHEN entry_type IN ('debit', 'fee') THEN amount ELSE 0 END), 0) AS debits_total,
            COALESCE(SUM(CASE WHEN entry_type = 'refund' THEN amount ELSE 0 END), 0) AS refunds_total,
            COALESCE(SUM(CASE WHEN entry_type = 'fee' THEN amount ELSE 0 END), 0) AS fees_total
          FROM wallet_ledger_entries
          WHERE wallet_id = ?";
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return $overview;
  }

  mysqli_stmt_bind_param($stmt, 'i', $walletId);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = $result ? mysqli_fetch_assoc($result) : null;
  mysqli_stmt_close($stmt);

  if ($row) {
    $overview = [
      'entries_count' => (int) ($row['entries_count'] ?? 0),
      'credits_total' => (int) ($row['credits_total'] ?? 0),
      'debits_total' => (int) ($row['debits_total'] ?? 0),
      'refunds_total' => (int) ($row['refunds_total'] ?? 0),
      'fees_total' => (int) ($row['fees_total'] ?? 0),
    ];
  }

  return $overview;
}

function ccStudentWalletFetchEntries($conn, $walletId, $filters = [], $limit = 50) {
  $walletId = (int) $walletId;
  $limit = max(1, min(100, (int) $limit));
  $entries = [];

  if ($walletId <= 0) {
    return $entries;
  }

  $entryType = strtolower(trim((string) ($filters['entry_type'] ?? 'all')));
  $dateFrom = trim((string) ($filters['date_from'] ?? ''));
  $dateTo = trim((string) ($filters['date_to'] ?? ''));
  $sql = "SELECT id, entry_type, amount, balance_before, balance_after, status, reference,
                 provider_reference, description, created_at
          FROM wallet_ledger_entries
          WHERE wallet_id = ?";
  $paramTypes = 'i';
  $params = [$walletId];

  if (in_array($entryType, ['credit', 'debit', 'refund', 'fee', 'adjustment'], true)) {
    $sql .= " AND entry_type = ?";
    $paramTypes .= 's';
    $params[] = $entryType;
  }

  if (ccStudentWalletIsValidDate($dateFrom)) {
    $sql .= " AND created_at >= ?";
    $paramTypes .= 's';
    $params[] = $dateFrom . ' 00:00:00';
  }

  if (ccStudentWalletIsValidDate($dateTo)) {
    $sql .= " AND created_at <= ?";
    $paramTypes .= 's';
    $params[] = $dateTo . ' 23:59:59';
  }

  $sql .= " ORDER BY created_at DESC, id DESC LIMIT ?";
  $paramTypes .= 'i';
  $params[] = $limit;
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return $entries;
  }

  ccStudentWalletBindParams($stmt, $paramTypes, $params);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);

  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $entryTypeValue = strtolower((string) ($row['entry_type'] ?? 'adjustment'));
      $isCredit = in_array($entryTypeValue, ['credit', 'refund'], true);
      $isDebit = in_array($entryTypeValue, ['debit', 'fee'], true);
      $referenceDisplay = trim((string) ($row['provider_reference'] ?? ''));
      if ($referenceDisplay === '') {
        $referenceDisplay = (string) ($row['reference'] ?? '-');
      }

      $entries[] = [
        'id' => (int) ($row['id'] ?? 0),
        'entry_type' => $entryTypeValue,
        'entry_type_label' => ucfirst($entryTypeValue),
        'entry_type_badge' => ccStudentWalletEntryTypeBadge($entryTypeValue),
        'direction' => $isCredit ? 'credit' : ($isDebit ? 'debit' : 'neutral'),
        'amount' => (int) ($row['amount'] ?? 0),
        'amount_sign' => $isCredit ? '+' : ($isDebit ? '-' : ''),
        'balance_before' => (int) ($row['balance_before'] ?? 0),
        'balance_after' => (int) ($row['balance_after'] ?? 0),
        'status' => (string) ($row['status'] ?? ''),
        'status_label' => ucfirst((string) ($row['status'] ?? 'unknown')),
        'status_badge' => ccStudentWalletStatusBadge((string) ($row['status'] ?? 'unknown')),
        'reference' => (string) ($row['reference'] ?? ''),
        'provider_reference' => (string) ($row['provider_reference'] ?? ''),
        'reference_display' => $referenceDisplay,
        'description' => (string) ($row['description'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'created_at_display' => ccStudentWalletFormatDate((string) ($row['created_at'] ?? '')),
      ];
    }
  }

  mysqli_stmt_close($stmt);
  return $entries;
}

function ccStudentWalletNormalizeStudent($student) {
  $firstName = trim((string) ($student['first_name'] ?? ''));
  $lastName = trim((string) ($student['last_name'] ?? ''));
  $fullName = trim($firstName . ' ' . $lastName);
  $status = (string) ($student['user_status'] ?? 'unknown');

  return [
    'id' => (int) ($student['id'] ?? 0),
    'first_name' => $firstName,
    'last_name' => $lastName,
    'full_name' => $fullName !== '' ? $fullName : '-',
    'email' => (string) ($student['email'] ?? ''),
    'phone' => (string) ($student['phone'] ?? ''),
    'matric_no' => (string) ($student['matric_no'] ?? ''),
    'school_name' => (string) ($student['school_name'] ?? ''),
    'faculty_name' => (string) ($student['faculty_name'] ?? ''),
    'dept_name' => (string) ($student['dept_name'] ?? ''),
    'adm_year' => (string) ($student['adm_year'] ?? ''),
    'status' => $status,
    'status_label' => ucfirst($status !== '' ? $status : 'unknown'),
    'status_badge' => ccStudentWalletStatusBadge($status),
  ];
}

function ccStudentWalletNormalizeWallet($wallet) {
  $walletStatus = (string) ($wallet['status'] ?? 'unknown');
  $virtualAccountStatus = (string) ($wallet['virtual_account_status'] ?? 'unknown');

  return [
    'id' => (int) ($wallet['id'] ?? 0),
    'status' => $walletStatus,
    'status_label' => ucfirst($walletStatus !== '' ? $walletStatus : 'unknown'),
    'status_badge' => ccStudentWalletStatusBadge($walletStatus),
    'balance' => (int) ($wallet['balance'] ?? 0),
    'requested_via' => (string) ($wallet['requested_via'] ?? ''),
    'currency' => (string) ($wallet['currency'] ?? 'NGN'),
    'account_name' => (string) ($wallet['account_name'] ?? ''),
    'account_number' => (string) ($wallet['account_number'] ?? ''),
    'bank_name' => (string) ($wallet['bank_name'] ?? ''),
    'provider' => (string) ($wallet['provider'] ?? ''),
    'virtual_account_status' => $virtualAccountStatus,
    'virtual_account_status_label' => ucfirst($virtualAccountStatus !== '' ? $virtualAccountStatus : 'unknown'),
    'created_at' => (string) ($wallet['created_at'] ?? ''),
    'created_at_display' => ccStudentWalletFormatDate((string) ($wallet['created_at'] ?? '')),
    'updated_at' => (string) ($wallet['updated_at'] ?? ''),
    'updated_at_display' => ccStudentWalletFormatDate((string) ($wallet['updated_at'] ?? '')),
  ];
}

$adminRole = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$adminId = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;
$allowedRoles = [1, 2, 3, 4, 5];

if (!in_array($adminRole, $allowedRoles, true) || $adminId <= 0) {
  ccStudentWalletRespondJson(403, ['success' => false, 'message' => 'Unauthorized access']);
}

$requiredTables = ['admins', 'users', 'schools', 'depts', 'faculties', 'user_wallets', 'wallet_virtual_accounts', 'wallet_ledger_entries'];
$missingTables = [];
foreach ($requiredTables as $requiredTable) {
  if (!ccStudentWalletTableExists($conn, $requiredTable)) {
    $missingTables[] = $requiredTable;
  }
}

if (!empty($missingTables)) {
  ccStudentWalletRespondJson(500, [
    'success' => false,
    'tables_ready' => false,
    'missing_tables' => $missingTables,
    'message' => 'Wallet tables are not available in this environment yet.'
  ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ccStudentWalletRespondJson(405, ['success' => false, 'message' => 'Method not allowed']);
}

$adminScopeRs = mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $adminId LIMIT 1");
$adminScope = $adminScopeRs ? mysqli_fetch_assoc($adminScopeRs) : null;
$adminSchool = isset($adminScope['school']) ? (int) $adminScope['school'] : 0;
$adminFaculty = isset($adminScope['faculty']) ? (int) $adminScope['faculty'] : 0;

$lookup = trim((string) ($_POST['lookup'] ?? ''));
if ($lookup === '') {
  ccStudentWalletRespondJson(400, ['success' => false, 'message' => 'Enter a matric number or email address.']);
}

$allowedEntryTypes = ['all', 'credit', 'debit', 'refund', 'fee', 'adjustment'];
$entryTypeFilter = strtolower(trim((string) ($_POST['entry_type'] ?? 'all')));
if (!in_array($entryTypeFilter, $allowedEntryTypes, true)) {
  $entryTypeFilter = 'all';
}

$dateFromFilter = trim((string) ($_POST['date_from'] ?? ''));
$dateToFilter = trim((string) ($_POST['date_to'] ?? ''));

if ($dateFromFilter !== '' && !ccStudentWalletIsValidDate($dateFromFilter)) {
  ccStudentWalletRespondJson(400, ['success' => false, 'message' => 'Start date must use the YYYY-MM-DD format.']);
}

if ($dateToFilter !== '' && !ccStudentWalletIsValidDate($dateToFilter)) {
  ccStudentWalletRespondJson(400, ['success' => false, 'message' => 'End date must use the YYYY-MM-DD format.']);
}

if ($dateFromFilter !== '' && $dateToFilter !== '' && $dateFromFilter > $dateToFilter) {
  ccStudentWalletRespondJson(400, ['success' => false, 'message' => 'Start date cannot be later than end date.']);
}

$student = ccStudentWalletFindStudent($conn, $lookup, $adminRole, $adminSchool, $adminFaculty);
if (!$student) {
  ccStudentWalletRespondJson(404, ['success' => false, 'message' => 'No student matched that matric number or email address.']);
}

$wallet = ccStudentWalletFetchWallet($conn, (int) ($student['id'] ?? 0));
if (!$wallet) {
  ccStudentWalletRespondJson(200, [
    'success' => true,
    'tables_ready' => true,
    'student_found' => true,
    'has_wallet' => false,
    'message' => 'Student found. No wallet has been created for this account yet.',
    'student' => ccStudentWalletNormalizeStudent($student),
    'wallet' => null,
    'overview' => [
      'entries_count' => 0,
      'credits_total' => 0,
      'debits_total' => 0,
      'refunds_total' => 0,
      'fees_total' => 0,
    ],
    'entries' => [],
    'filters' => [
      'entry_type' => $entryTypeFilter,
      'date_from' => $dateFromFilter,
      'date_to' => $dateToFilter,
      'has_active_filters' => $entryTypeFilter !== 'all' || $dateFromFilter !== '' || $dateToFilter !== '',
    ],
  ]);
}

$overview = ccStudentWalletFetchOverview($conn, (int) ($wallet['id'] ?? 0));
$entries = ccStudentWalletFetchEntries($conn, (int) ($wallet['id'] ?? 0), [
  'entry_type' => $entryTypeFilter,
  'date_from' => $dateFromFilter,
  'date_to' => $dateToFilter,
], 50);

ccStudentWalletRespondJson(200, [
  'success' => true,
  'tables_ready' => true,
  'student_found' => true,
  'has_wallet' => true,
  'message' => 'Wallet details loaded successfully.',
  'student' => ccStudentWalletNormalizeStudent($student),
  'wallet' => ccStudentWalletNormalizeWallet($wallet),
  'overview' => $overview,
  'entries' => $entries,
  'filters' => [
    'entry_type' => $entryTypeFilter,
    'date_from' => $dateFromFilter,
    'date_to' => $dateToFilter,
    'has_active_filters' => $entryTypeFilter !== 'all' || $dateFromFilter !== '' || $dateToFilter !== '',
  ],
]);