<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/transactions_helpers.php');

function ccWalletPreCreditTableExists($conn, $tableName) {
  static $cache = [];

  $tableName = trim((string) $tableName);
  if ($tableName === '') {
    return false;
  }

  if (array_key_exists($tableName, $cache)) {
    return $cache[$tableName];
  }

  $safeTableName = mysqli_real_escape_string($conn, $tableName);
  $result = mysqli_query($conn, "SHOW TABLES LIKE '$safeTableName'");
  $cache[$tableName] = $result && mysqli_num_rows($result) > 0;

  return $cache[$tableName];
}

function ccWalletPreCreditFundingHasColumn($conn, $columnName) {
  static $cache = [];

  $columnName = trim((string) $columnName);
  if ($columnName === '') {
    return false;
  }

  if (array_key_exists($columnName, $cache)) {
    return $cache[$columnName];
  }

  $safeColumnName = mysqli_real_escape_string($conn, $columnName);
  $result = mysqli_query($conn, "SHOW COLUMNS FROM wallet_funding_transactions LIKE '$safeColumnName'");
  $cache[$columnName] = $result && mysqli_num_rows($result) > 0;

  return $cache[$columnName];
}

function ccWalletPreCreditRequiredTables() {
  return [
    'admins',
    'users',
    'schools',
    'depts',
    'faculties',
    'user_wallets',
    'wallet_virtual_accounts',
    'wallet_funding_transactions',
    'wallet_ledger_entries',
    'transactions',
    'wallet_pre_credits',
  ];
}

function ccWalletPreCreditGetMissingTables($conn) {
  $missingTables = [];

  foreach (ccWalletPreCreditRequiredTables() as $tableName) {
    if (!ccWalletPreCreditTableExists($conn, $tableName)) {
      $missingTables[] = $tableName;
    }
  }

  return $missingTables;
}

function ccWalletPreCreditFormatDateTime($value) {
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

function ccWalletPreCreditNormalizeDate($value) {
  $value = trim((string) $value);
  if ($value === '') {
    return '';
  }

  $date = DateTime::createFromFormat('Y-m-d', $value);
  if (!$date || $date->format('Y-m-d') !== $value) {
    return '';
  }

  return $value;
}

function ccWalletPreCreditGetFilters(array $source) {
  $allowedStatuses = ['all', 'pending_confirmation', 'confirmed', 'amount_disputed'];
  $allowedDateRanges = ['today', 'yesterday', '7', '30', '90', 'all', 'custom'];

  $status = strtolower(trim((string) ($source['status'] ?? 'all')));
  if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
  }

  $dateRange = trim((string) ($source['date_range'] ?? '30'));
  if (!in_array($dateRange, $allowedDateRanges, true)) {
    $dateRange = '30';
  }

  $startDate = ccWalletPreCreditNormalizeDate($source['start_date'] ?? '');
  $endDate = ccWalletPreCreditNormalizeDate($source['end_date'] ?? '');

  if ($dateRange !== 'custom') {
    $startDate = '';
    $endDate = '';
  }

  return [
    'status' => $status,
    'date_range' => $dateRange,
    'start_date' => $startDate,
    'end_date' => $endDate,
  ];
}

function ccWalletPreCreditStatusTone($status) {
  $status = strtolower(trim((string) $status));

  if ($status === 'confirmed') {
    return 'success';
  }

  if ($status === 'amount_disputed') {
    return 'danger';
  }

  if ($status === 'pending_confirmation') {
    return 'warning';
  }

  return 'secondary';
}

function ccWalletPreCreditBuildScopeSql($adminRole, $adminSchool, $adminFaculty, $userAlias = 'u', $deptAlias = 'd') {
  $clauses = [];
  $adminRole = (int) $adminRole;
  $adminSchool = (int) $adminSchool;
  $adminFaculty = (int) $adminFaculty;

  if ($adminRole === 5 && $adminSchool > 0) {
    $clauses[] = $userAlias . '.school = ' . $adminSchool;
  }

  if ($adminRole === 5 && $adminFaculty > 0) {
    $clauses[] = $deptAlias . '.faculty_id = ' . $adminFaculty;
  }

  return $clauses === [] ? '' : (' AND ' . implode(' AND ', $clauses));
}

function ccWalletPreCreditGetAdminScope($conn, $adminId) {
  $adminId = (int) $adminId;
  $scope = [
    'school' => 0,
    'faculty' => 0,
  ];

  if ($adminId <= 0 || !ccWalletPreCreditTableExists($conn, 'admins')) {
    return $scope;
  }

  $result = mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $adminId LIMIT 1");
  if ($result && ($row = mysqli_fetch_assoc($result))) {
    $scope['school'] = (int) ($row['school'] ?? 0);
    $scope['faculty'] = (int) ($row['faculty'] ?? 0);
  }

  return $scope;
}

function ccWalletPreCreditNormalizeWalletRecord($wallet) {
  $studentName = trim((string) (($wallet['first_name'] ?? '') . ' ' . ($wallet['last_name'] ?? '')));
  $userStatus = trim((string) ($wallet['user_status'] ?? ''));
  $walletStatus = trim((string) ($wallet['wallet_status'] ?? ''));

  return [
    'wallet_id' => (int) ($wallet['wallet_id'] ?? 0),
    'user_id' => (int) ($wallet['user_id'] ?? 0),
    'student_name' => $studentName !== '' ? $studentName : 'Unknown User',
    'email' => (string) ($wallet['email'] ?? ''),
    'matric_no' => (string) ($wallet['matric_no'] ?? ''),
    'school_name' => (string) ($wallet['school_name'] ?? ''),
    'faculty_name' => (string) ($wallet['faculty_name'] ?? ''),
    'dept_name' => (string) ($wallet['dept_name'] ?? ''),
    'account_name' => (string) ($wallet['account_name'] ?? ''),
    'account_number' => (string) ($wallet['account_number'] ?? ''),
    'bank_name' => (string) ($wallet['bank_name'] ?? ''),
    'provider' => (string) ($wallet['provider'] ?? ''),
    'provider_account_id' => (string) ($wallet['provider_account_id'] ?? ''),
    'provider_customer_code' => (string) ($wallet['provider_customer_code'] ?? ''),
    'wallet_status' => $walletStatus,
    'wallet_status_tone' => ccWalletPreCreditStatusTone($walletStatus),
    'wallet_balance' => (int) ($wallet['balance'] ?? 0),
    'currency' => (string) ($wallet['currency'] ?? 'NGN'),
    'user_status' => $userStatus,
    'user_status_tone' => ccWalletPreCreditStatusTone($userStatus),
  ];
}

function ccWalletPreCreditFindWalletByAccountNumber($conn, $accountNumber, $adminRole, $adminSchool, $adminFaculty) {
  $accountNumber = preg_replace('/\D+/', '', (string) $accountNumber);
  if ($accountNumber === '') {
    return null;
  }

  $accountNumberSafe = mysqli_real_escape_string($conn, $accountNumber);
  $scopeSql = ccWalletPreCreditBuildScopeSql($adminRole, $adminSchool, $adminFaculty, 'u', 'd');

  $sql = "SELECT
            w.id AS wallet_id,
            w.user_id,
            w.status AS wallet_status,
            w.balance,
            w.currency,
            u.first_name,
            u.last_name,
            u.email,
            u.matric_no,
            u.status AS user_status,
            s.name AS school_name,
            f.name AS faculty_name,
            d.name AS dept_name,
            va.provider,
            va.provider_account_id,
            va.provider_customer_code,
            va.account_name,
            va.account_number,
            va.bank_name
          FROM wallet_virtual_accounts va
          INNER JOIN user_wallets w ON w.id = va.wallet_id
          INNER JOIN users u ON u.id = w.user_id
          LEFT JOIN schools s ON s.id = u.school
          LEFT JOIN depts d ON d.id = u.dept
          LEFT JOIN faculties f ON f.id = d.faculty_id
          WHERE va.account_number = '$accountNumberSafe'
            AND LOWER(COALESCE(va.status, 'active')) = 'active'
            AND LOWER(COALESCE(w.status, 'active')) <> 'closed'" . $scopeSql . "
          LIMIT 1";

  $result = mysqli_query($conn, $sql);
  if (!$result || mysqli_num_rows($result) < 1) {
    return null;
  }

  return mysqli_fetch_assoc($result);
}

function ccWalletPreCreditNormalizeReference($reference) {
  $reference = trim((string) $reference);
  if ($reference === '') {
    return '';
  }

  return preg_replace('/\s+/', '', $reference);
}

function ccWalletPreCreditValidateReference($reference) {
  $reference = ccWalletPreCreditNormalizeReference($reference);
  if ($reference === '') {
    return 'Enter a reference ID.';
  }

  if (strlen($reference) > 50) {
    return 'Reference ID must not exceed 50 characters.';
  }

  if (!preg_match('/^[A-Za-z0-9._:-]+$/', $reference)) {
    return 'Reference ID can only contain letters, numbers, dot, underscore, colon, or hyphen.';
  }

  return '';
}

function ccWalletPreCreditNormalizeAmount($amount) {
  if (is_string($amount)) {
    $amount = str_replace(',', '', $amount);
  }

  $amount = (float) $amount;
  if ($amount <= 0) {
    return 0;
  }

  return (int) round($amount);
}

function ccWalletPreCreditCalculateProviderCharge($amount, $provider = 'paystack') {
  $amount = (int) round((float) $amount);
  if ($amount <= 0) {
    return 0;
  }

  $provider = strtolower(trim((string) $provider));
  if ($provider === 'paystack') {
    return min(300, (int) round($amount * 0.01));
  }

  return 0;
}

function ccWalletPreCreditReferenceExists($conn, $reference, $excludePreCreditId = 0) {
  $reference = ccWalletPreCreditNormalizeReference($reference);
  if ($reference === '') {
    return false;
  }

  $referenceSafe = mysqli_real_escape_string($conn, $reference);

  $checks = [];
  if (ccWalletPreCreditTableExists($conn, 'wallet_pre_credits')) {
    $sql = "SELECT id FROM wallet_pre_credits WHERE provider_reference = '$referenceSafe'";
    if ((int) $excludePreCreditId > 0) {
      $sql .= ' AND id <> ' . (int) $excludePreCreditId;
    }
    $sql .= ' LIMIT 1';
    $checks[] = $sql;
  }

  $tableChecks = [
    'wallet_funding_transactions' => 'provider_reference',
    'transactions' => 'ref_id',
    'cart' => 'ref_id',
    'manuals_bought' => 'ref_id',
    'event_tickets' => 'ref_id',
  ];

  foreach ($tableChecks as $tableName => $columnName) {
    if (!ccWalletPreCreditTableExists($conn, $tableName)) {
      continue;
    }

    $checks[] = "SELECT 1 FROM {$tableName} WHERE {$columnName} = '$referenceSafe' LIMIT 1";
  }

  foreach ($checks as $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
      return true;
    }
  }

  return false;
}

function ccWalletPreCreditAllowedReceiptMimeTypes() {
  return [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
  ];
}

function ccWalletPreCreditResolveReceiptMimeType($tmpName, $clientType = '') {
  $clientType = trim((string) $clientType);
  $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
  $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
  if ($finfo) {
    finfo_close($finfo);
  }

  if ($mimeType === '' && function_exists('mime_content_type')) {
    $mimeType = (string) mime_content_type($tmpName);
  }

  if ($mimeType === '') {
    $mimeType = $clientType;
  }

  return strtolower(trim($mimeType));
}

function ccWalletPreCreditStoreReceipt($file, $reference) {
  if (!is_array($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    throw new Exception('Upload the payment receipt as an image or PDF.');
  }

  $errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_OK;
  if ($errorCode !== UPLOAD_ERR_OK) {
    throw new Exception('Receipt upload failed. Please try again.');
  }

  $fileSize = isset($file['size']) ? (int) $file['size'] : 0;
  if ($fileSize <= 0) {
    throw new Exception('Receipt file is empty.');
  }

  $maxSize = 8 * 1024 * 1024;
  if ($fileSize > $maxSize) {
    throw new Exception('Receipt file must not exceed 8MB.');
  }

  $mimeType = ccWalletPreCreditResolveReceiptMimeType($file['tmp_name'], $file['type'] ?? '');
  $allowedMimeTypes = ccWalletPreCreditAllowedReceiptMimeTypes();
  if (!isset($allowedMimeTypes[$mimeType])) {
    throw new Exception('Receipt must be a JPG, PNG, WEBP, GIF image, or PDF.');
  }

  $extension = $allowedMimeTypes[$mimeType];
  $safeReference = preg_replace('/[^A-Za-z0-9_-]+/', '_', ccWalletPreCreditNormalizeReference($reference));
  if ($safeReference === '') {
    $safeReference = 'wallet_pre_credit';
  }

  $uploadDir = __DIR__ . '/../assets/images/wallet_pre_credits/';
  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
    throw new Exception('Could not prepare the receipt upload directory.');
  }

  $storedName = $safeReference . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
  $destination = $uploadDir . $storedName;
  if (!move_uploaded_file($file['tmp_name'], $destination)) {
    throw new Exception('Could not save the uploaded receipt.');
  }

  return [
    'receipt_path' => 'assets/images/wallet_pre_credits/' . $storedName,
    'receipt_name' => (string) ($file['name'] ?? $storedName),
    'receipt_mime_type' => $mimeType,
    'receipt_size' => $fileSize,
  ];
}

function ccWalletPreCreditEncodeJson($value) {
  $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($encoded === false) {
    throw new Exception('Failed to encode pre-credit metadata.');
  }

  return $encoded;
}

function ccWalletPreCreditHydrateRecord($row) {
  $status = (string) ($row['status'] ?? 'pending_confirmation');
  $studentName = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
  $receiptPath = trim((string) ($row['receipt_path'] ?? ''));
  $createdAt = (string) ($row['created_at'] ?? '');
  $overdue = $status === 'pending_confirmation' && $createdAt !== '' && strtotime($createdAt) !== false && strtotime($createdAt) <= strtotime('-48 hours');

  return [
    'id' => (int) ($row['id'] ?? 0),
    'wallet_id' => (int) ($row['wallet_id'] ?? 0),
    'user_id' => (int) ($row['user_id'] ?? 0),
    'student_name' => $studentName !== '' ? $studentName : 'Unknown User',
    'email' => (string) ($row['email'] ?? ''),
    'matric_no' => (string) ($row['matric_no'] ?? ''),
    'school_name' => (string) ($row['school_name'] ?? ''),
    'faculty_name' => (string) ($row['faculty_name'] ?? ''),
    'dept_name' => (string) ($row['dept_name'] ?? ''),
    'account_number' => (string) ($row['account_number'] ?? ''),
    'provider_reference' => (string) ($row['provider_reference'] ?? ''),
    'amount' => (int) ($row['amount'] ?? 0),
    'confirmed_amount' => isset($row['confirmed_amount']) && $row['confirmed_amount'] !== null ? (int) $row['confirmed_amount'] : null,
    'status' => $status,
    'status_label' => ucwords(str_replace('_', ' ', $status)),
    'status_tone' => ccWalletPreCreditStatusTone($status),
    'receipt_path' => $receiptPath,
    'receipt_url' => $receiptPath !== '' ? '/' . ltrim($receiptPath, '/') : '',
    'receipt_name' => (string) ($row['receipt_name'] ?? ''),
    'receipt_mime_type' => (string) ($row['receipt_mime_type'] ?? ''),
    'created_by_admin_id' => (int) ($row['created_by_admin_id'] ?? 0),
    'created_by_admin_name' => trim((string) (($row['created_by_first_name'] ?? '') . ' ' . ($row['created_by_last_name'] ?? ''))),
    'funding_transaction_id' => (int) ($row['funding_transaction_id'] ?? 0),
    'ledger_entry_id' => (int) ($row['ledger_entry_id'] ?? 0),
    'transaction_id' => (int) ($row['transaction_id'] ?? 0),
    'provider_transaction_id' => (string) ($row['provider_transaction_id'] ?? ''),
    'admin_note' => (string) ($row['admin_note'] ?? ''),
    'reconciliation_note' => (string) ($row['reconciliation_note'] ?? ''),
    'created_at' => $createdAt,
    'created_at_display' => ccWalletPreCreditFormatDateTime($createdAt),
    'updated_at' => (string) ($row['updated_at'] ?? ''),
    'updated_at_display' => ccWalletPreCreditFormatDateTime((string) ($row['updated_at'] ?? '')),
    'confirmed_at' => (string) ($row['confirmed_at'] ?? ''),
    'confirmed_at_display' => ccWalletPreCreditFormatDateTime((string) ($row['confirmed_at'] ?? '')),
    'overdue' => $overdue,
  ];
}

function ccWalletPreCreditFetchList($conn, array $filters, $adminRole, $adminSchool, $adminFaculty) {
  $statusFilter = strtolower(trim((string) ($filters['status'] ?? 'all')));
  $scopeSql = ccWalletPreCreditBuildScopeSql($adminRole, $adminSchool, $adminFaculty, 'u', 'd');
  $statusSql = '';

  if (in_array($statusFilter, ['pending_confirmation', 'confirmed', 'amount_disputed'], true)) {
    $statusSafe = mysqli_real_escape_string($conn, $statusFilter);
    $statusSql = " AND p.status = '$statusSafe'";
  }

  $dateSql = buildDateFilter(
    $conn,
    (string) ($filters['date_range'] ?? '30'),
    (string) ($filters['start_date'] ?? ''),
    (string) ($filters['end_date'] ?? ''),
    'p'
  );

  $summary = [
    'total_count' => 0,
    'pending_count' => 0,
    'confirmed_count' => 0,
    'disputed_count' => 0,
    'overdue_count' => 0,
    'total_amount' => 0,
  ];
  $rows = [];
  $tableNotice = '';

  $summarySql = "SELECT
                  COUNT(*) AS total_count,
                  COALESCE(SUM(CASE WHEN p.status = 'pending_confirmation' THEN 1 ELSE 0 END), 0) AS pending_count,
                  COALESCE(SUM(CASE WHEN p.status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_count,
                  COALESCE(SUM(CASE WHEN p.status = 'amount_disputed' THEN 1 ELSE 0 END), 0) AS disputed_count,
                  COALESCE(SUM(CASE WHEN p.status = 'pending_confirmation' AND p.created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR) THEN 1 ELSE 0 END), 0) AS overdue_count,
                  COALESCE(SUM(p.amount), 0) AS total_amount
                FROM wallet_pre_credits p
                INNER JOIN users u ON u.id = p.user_id
                LEFT JOIN depts d ON d.id = u.dept
                WHERE 1 = 1" . $statusSql . $dateSql . $scopeSql;
  $summaryResult = mysqli_query($conn, $summarySql);
  if ($summaryResult && ($summaryRow = mysqli_fetch_assoc($summaryResult))) {
    $summary = [
      'total_count' => (int) ($summaryRow['total_count'] ?? 0),
      'pending_count' => (int) ($summaryRow['pending_count'] ?? 0),
      'confirmed_count' => (int) ($summaryRow['confirmed_count'] ?? 0),
      'disputed_count' => (int) ($summaryRow['disputed_count'] ?? 0),
      'overdue_count' => (int) ($summaryRow['overdue_count'] ?? 0),
      'total_amount' => (int) ($summaryRow['total_amount'] ?? 0),
    ];
  }

  $dataSql = "SELECT
                p.*,
                u.first_name,
                u.last_name,
                u.email,
                u.matric_no,
                s.name AS school_name,
                f.name AS faculty_name,
                d.name AS dept_name,
                a.first_name AS created_by_first_name,
                a.last_name AS created_by_last_name
              FROM wallet_pre_credits p
              INNER JOIN users u ON u.id = p.user_id
              LEFT JOIN schools s ON s.id = u.school
              LEFT JOIN depts d ON d.id = u.dept
              LEFT JOIN faculties f ON f.id = d.faculty_id
              LEFT JOIN admins a ON a.id = p.created_by_admin_id
              WHERE 1 = 1" . $statusSql . $dateSql . $scopeSql . "
              ORDER BY p.created_at DESC, p.id DESC
              LIMIT 500";
  $dataResult = mysqli_query($conn, $dataSql);
  if ($dataResult) {
    while ($row = mysqli_fetch_assoc($dataResult)) {
      $rows[] = ccWalletPreCreditHydrateRecord($row);
    }
  }

  if ($summary['total_count'] > count($rows)) {
    $tableNotice = 'Showing the most recent 500 pre-credit records for the current filters.';
  }

  return [
    'summary' => $summary,
    'rows' => $rows,
    'table_notice' => $tableNotice,
  ];
}

function ccWalletPreCreditFetchOne($conn, $preCreditId, $adminRole, $adminSchool, $adminFaculty) {
  $preCreditId = (int) $preCreditId;
  if ($preCreditId <= 0) {
    return null;
  }

  $scopeSql = ccWalletPreCreditBuildScopeSql($adminRole, $adminSchool, $adminFaculty, 'u', 'd');
  $sql = "SELECT
            p.*,
            u.first_name,
            u.last_name,
            u.email,
            u.matric_no,
            s.name AS school_name,
            f.name AS faculty_name,
            d.name AS dept_name,
            a.first_name AS created_by_first_name,
            a.last_name AS created_by_last_name
          FROM wallet_pre_credits p
          INNER JOIN users u ON u.id = p.user_id
          LEFT JOIN schools s ON s.id = u.school
          LEFT JOIN depts d ON d.id = u.dept
          LEFT JOIN faculties f ON f.id = d.faculty_id
          LEFT JOIN admins a ON a.id = p.created_by_admin_id
          WHERE p.id = $preCreditId" . $scopeSql . "
          LIMIT 1";
  $result = mysqli_query($conn, $sql);
  if (!$result || mysqli_num_rows($result) < 1) {
    return null;
  }

  return ccWalletPreCreditHydrateRecord(mysqli_fetch_assoc($result));
}

function ccWalletPreCreditCreate($conn, $adminId, $wallet, array $payload) {
  $adminId = (int) $adminId;
  if ($adminId <= 0) {
    throw new Exception('Unauthorized access.');
  }

  $walletId = (int) ($wallet['wallet_id'] ?? 0);
  $userId = (int) ($wallet['user_id'] ?? 0);
  if ($walletId <= 0 || $userId <= 0) {
    throw new Exception('Wallet lookup returned an invalid record.');
  }

  $reference = ccWalletPreCreditNormalizeReference($payload['provider_reference'] ?? '');
  $referenceError = ccWalletPreCreditValidateReference($reference);
  if ($referenceError !== '') {
    throw new Exception($referenceError);
  }

  if (ccWalletPreCreditReferenceExists($conn, $reference)) {
    throw new Exception('That reference already exists in the database.');
  }

  $amount = ccWalletPreCreditNormalizeAmount($payload['amount'] ?? 0);
  if ($amount <= 0) {
    throw new Exception('Amount must be greater than zero.');
  }

  $receipt = ccWalletPreCreditStoreReceipt($payload['receipt'] ?? null, $reference);
  $adminNote = trim((string) ($payload['admin_note'] ?? ''));
  $providerChargeAmount = ccWalletPreCreditCalculateProviderCharge($amount, 'paystack');
  $description = 'Wallet pre-credit awaiting Paystack confirmation';

  $manualPayload = [
    'type' => 'manual_wallet_pre_credit',
    'provider_reference' => $reference,
    'amount' => $amount,
    'source' => 'manual_pre_credit',
    'account_number' => (string) ($wallet['account_number'] ?? ''),
    'wallet_id' => $walletId,
    'user_id' => $userId,
    'created_by_admin_id' => $adminId,
    'admin_note' => $adminNote,
    'receipt' => $receipt,
    'created_at' => date('Y-m-d H:i:s'),
  ];
  $rawPayload = ccWalletPreCreditEncodeJson($manualPayload);
  $rawPayloadSafe = mysqli_real_escape_string($conn, $rawPayload);
  $referenceSafe = mysqli_real_escape_string($conn, $reference);
  $descriptionSafe = mysqli_real_escape_string($conn, $description);
  $accountNumberSafe = mysqli_real_escape_string($conn, (string) ($wallet['account_number'] ?? ''));
  $providerAccountIdSafe = mysqli_real_escape_string($conn, (string) ($wallet['provider_account_id'] ?? ''));
  $providerCustomerCodeSafe = mysqli_real_escape_string($conn, (string) ($wallet['provider_customer_code'] ?? ''));
  $receiptPathSafe = mysqli_real_escape_string($conn, (string) $receipt['receipt_path']);
  $receiptNameSafe = mysqli_real_escape_string($conn, (string) $receipt['receipt_name']);
  $receiptMimeTypeSafe = mysqli_real_escape_string($conn, (string) $receipt['receipt_mime_type']);
  $adminNoteSafe = mysqli_real_escape_string($conn, $adminNote);

  mysqli_begin_transaction($conn);
  try {
    $walletLockSql = "SELECT balance FROM user_wallets WHERE id = $walletId LIMIT 1 FOR UPDATE";
    $walletLockResult = mysqli_query($conn, $walletLockSql);
    if (!$walletLockResult || mysqli_num_rows($walletLockResult) < 1) {
      throw new Exception('Unable to lock wallet for crediting.');
    }

    $walletRow = mysqli_fetch_assoc($walletLockResult);
    $balanceBefore = (int) ($walletRow['balance'] ?? 0);
    $balanceAfter = $balanceBefore + $amount;

    if (ccWalletPreCreditReferenceExists($conn, $reference)) {
      throw new Exception('That reference already exists in the database.');
    }

    $insertFundingColumns = [
      'wallet_id',
      'user_id',
      'provider',
      'provider_reference',
      'provider_event',
      'provider_transaction_id',
      'provider_account_id',
      'account_number',
      'amount',
      'status',
      'source',
      'description',
      'raw_payload',
      'posted_at',
    ];
    $insertFundingValues = [
      (string) $walletId,
      (string) $userId,
      "'paystack'",
      "'$referenceSafe'",
      "'manual_pre_credit'",
      'NULL',
      ($providerAccountIdSafe !== '' ? "'$providerAccountIdSafe'" : 'NULL'),
      ($accountNumberSafe !== '' ? "'$accountNumberSafe'" : 'NULL'),
      (string) $amount,
      "'posted'",
      "'manual_pre_credit'",
      "'$descriptionSafe'",
      "'$rawPayloadSafe'",
      'NOW()',
    ];

    if (ccWalletPreCreditFundingHasColumn($conn, 'provider_charge_amount')) {
      array_splice($insertFundingColumns, 9, 0, ['provider_charge_amount']);
      array_splice($insertFundingValues, 9, 0, [(string) $providerChargeAmount]);
    }
    if (ccWalletPreCreditFundingHasColumn($conn, 'consumed_charge_amount')) {
      array_splice($insertFundingColumns, 10, 0, ['consumed_charge_amount']);
      array_splice($insertFundingValues, 10, 0, ['0']);
    }
    if (ccWalletPreCreditFundingHasColumn($conn, 'remaining_charge_amount')) {
      $offset = ccWalletPreCreditFundingHasColumn($conn, 'provider_charge_amount') && ccWalletPreCreditFundingHasColumn($conn, 'consumed_charge_amount') ? 11 : (ccWalletPreCreditFundingHasColumn($conn, 'provider_charge_amount') ? 10 : 9);
      array_splice($insertFundingColumns, $offset, 0, ['remaining_charge_amount']);
      array_splice($insertFundingValues, $offset, 0, [(string) $providerChargeAmount]);
    }

    $insertFundingSql = 'INSERT INTO wallet_funding_transactions (' . implode(', ', $insertFundingColumns) . ') VALUES (' . implode(', ', $insertFundingValues) . ')';
    if (!mysqli_query($conn, $insertFundingSql)) {
      throw new Exception('Failed to create the funding transaction record: ' . mysqli_error($conn));
    }
    $fundingTransactionId = (int) mysqli_insert_id($conn);

    $ledgerReferenceSafe = mysqli_real_escape_string($conn, 'wallet_funding:' . $reference);
    $insertLedgerSql = "INSERT INTO wallet_ledger_entries (
                          wallet_id, entry_type, amount, balance_before, balance_after, status,
                          reference, provider_reference, description, metadata
                        ) VALUES (
                          $walletId, 'credit', $amount, $balanceBefore, $balanceAfter, 'posted',
                          '$ledgerReferenceSafe', '$referenceSafe', '$descriptionSafe', '$rawPayloadSafe'
                        )";
    if (!mysqli_query($conn, $insertLedgerSql)) {
      throw new Exception('Failed to create the wallet ledger entry: ' . mysqli_error($conn));
    }
    $ledgerEntryId = (int) mysqli_insert_id($conn);

    $updateWalletSql = "UPDATE user_wallets SET balance = $balanceAfter, updated_at = NOW() WHERE id = $walletId";
    if (!mysqli_query($conn, $updateWalletSql)) {
      throw new Exception('Failed to update the wallet balance: ' . mysqli_error($conn));
    }

    $insertTransactionSql = "INSERT INTO transactions (
                              ref_id, user_id, amount, charge, profit, refund, status, medium, payment_channel, transaction_context
                            ) VALUES (
                              '$referenceSafe', $userId, $amount, $providerChargeAmount, 0, 0, 'successful', 'PAYSTACK', 'wallet', 'wallet_funding'
                            )";
    if (!mysqli_query($conn, $insertTransactionSql)) {
      throw new Exception('Failed to create the linked transaction row: ' . mysqli_error($conn));
    }
    $transactionId = (int) mysqli_insert_id($conn);

    $insertPreCreditSql = "INSERT INTO wallet_pre_credits (
                            wallet_id, user_id, account_number, provider_reference, amount,
                            receipt_path, receipt_name, receipt_mime_type, receipt_size,
                            created_by_admin_id, funding_transaction_id, ledger_entry_id, transaction_id,
                            status, provider_customer_code, admin_note
                          ) VALUES (
                            $walletId, $userId, '$accountNumberSafe', '$referenceSafe', $amount,
                            '$receiptPathSafe', '$receiptNameSafe', '$receiptMimeTypeSafe', " . (int) $receipt['receipt_size'] . ",
                            $adminId, $fundingTransactionId, $ledgerEntryId, $transactionId,
                            'pending_confirmation', " . ($providerCustomerCodeSafe !== '' ? "'$providerCustomerCodeSafe'" : 'NULL') . ", '$adminNoteSafe'
                          )";
    if (!mysqli_query($conn, $insertPreCreditSql)) {
      throw new Exception('Failed to create the wallet pre-credit audit row: ' . mysqli_error($conn));
    }
    $preCreditId = (int) mysqli_insert_id($conn);

    mysqli_commit($conn);

    log_audit_event($conn, $adminId, 'create', 'wallet_pre_credits', $preCreditId, [
      'after' => [
        'id' => $preCreditId,
        'wallet_id' => $walletId,
        'user_id' => $userId,
        'provider_reference' => $reference,
        'amount' => $amount,
        'status' => 'pending_confirmation',
      ],
    ]);

    $createdRecord = ccWalletPreCreditFetchOne($conn, $preCreditId, 1, 0, 0);
    if ($createdRecord === null) {
      $createdRecord = [
        'id' => $preCreditId,
        'provider_reference' => $reference,
        'amount' => $amount,
        'status' => 'pending_confirmation',
      ];
    }

    return [
      'pre_credit_id' => $preCreditId,
      'record' => $createdRecord,
    ];
  } catch (Throwable $throwable) {
    mysqli_rollback($conn);
    throw $throwable;
  }
}

function ccWalletPreCreditUpdate($conn, $adminId, $preCreditId, $adminRole, $adminSchool, $adminFaculty, array $payload) {
  $adminId = (int) $adminId;
  $preCreditId = (int) $preCreditId;
  if ($adminId <= 0 || $preCreditId <= 0) {
    throw new Exception('Invalid pre-credit update request.');
  }

  $existing = ccWalletPreCreditFetchOne($conn, $preCreditId, $adminRole, $adminSchool, $adminFaculty);
  if ($existing === null) {
    throw new Exception('Pre-credit record not found.');
  }

  $allowedStatuses = ['pending_confirmation', 'confirmed', 'amount_disputed'];
  $status = strtolower(trim((string) ($payload['status'] ?? $existing['status'])));
  if (!in_array($status, $allowedStatuses, true)) {
    throw new Exception('Select a valid reconciliation status.');
  }

  if ($existing['status'] === 'confirmed' && $status !== 'confirmed') {
    throw new Exception('Confirmed pre-credits can no longer be moved out of the confirmed state.');
  }

  $adminNote = trim((string) ($payload['admin_note'] ?? $existing['admin_note'] ?? ''));
  $reconciliationNote = trim((string) ($payload['reconciliation_note'] ?? $existing['reconciliation_note'] ?? ''));
  $statusSafe = mysqli_real_escape_string($conn, $status);
  $adminNoteSafe = mysqli_real_escape_string($conn, $adminNote);
  $reconciliationNoteSafe = mysqli_real_escape_string($conn, $reconciliationNote);

  $setParts = [
    "status = '$statusSafe'",
    "admin_note = '$adminNoteSafe'",
    "reconciliation_note = '$reconciliationNoteSafe'",
    'updated_at = NOW()',
  ];

  if ($status === 'confirmed') {
    $setParts[] = 'confirmed_amount = COALESCE(confirmed_amount, amount)';
    $setParts[] = 'confirmed_at = COALESCE(confirmed_at, NOW())';
  }

  if ($status === 'pending_confirmation') {
    $setParts[] = 'confirmed_at = NULL';
    $setParts[] = 'confirmed_amount = NULL';
  }

  $updateSql = 'UPDATE wallet_pre_credits SET ' . implode(', ', $setParts) . ' WHERE id = ' . $preCreditId . ' LIMIT 1';
  if (!mysqli_query($conn, $updateSql)) {
    throw new Exception('Failed to update the pre-credit record: ' . mysqli_error($conn));
  }

  $updated = ccWalletPreCreditFetchOne($conn, $preCreditId, $adminRole, $adminSchool, $adminFaculty);
  log_audit_event($conn, $adminId, 'update', 'wallet_pre_credits', $preCreditId, [
    'before' => $existing,
    'after' => $updated,
  ]);

  return $updated;
}