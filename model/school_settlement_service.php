<?php

require_once(__DIR__ . '/../config/fw.php');

if (!function_exists('ccSchoolSettlementAdminAllowed')) {
  function ccSchoolSettlementAdminAllowed($role)
  {
    return in_array((int) $role, [1, 2, 4], true);
  }
}

if (!function_exists('ccSchoolSettlementTableExists')) {
  function ccSchoolSettlementTableExists(mysqli $conn, string $tableName): bool
  {
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
      return $cache[$tableName];
    }

    $tableNameSafe = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableNameSafe'");
    $cache[$tableName] = $result instanceof mysqli_result && mysqli_num_rows($result) > 0;

    return $cache[$tableName];
  }
}

if (!function_exists('ccSchoolSettlementTablesReady')) {
  function ccSchoolSettlementTablesReady(mysqli $conn): bool
  {
    return ccSchoolSettlementTableExists($conn, 'schools')
      && ccSchoolSettlementTableExists($conn, 'school_internal_wallets')
      && ccSchoolSettlementTableExists($conn, 'school_payable_ledger')
      && ccSchoolSettlementTableExists($conn, 'settlement_accounts')
      && ccSchoolSettlementTableExists($conn, 'settlement_batches')
      && ccSchoolSettlementTableExists($conn, 'settlement_batch_items');
  }
}

if (!function_exists('ccSchoolSettlementCapPerSchool')) {
  function ccSchoolSettlementCapPerSchool(?mysqli $conn = null): int
  {
    $fallback = 6000000;

    if ($conn === null) {
      return $fallback;
    }

    $config = ccSchoolSettlementGetConfig($conn);
    $configuredCap = (int) ($config['max_settlement_cap_per_school'] ?? 0);

    return $configuredCap > 0 ? $configuredCap : $fallback;
  }
}

if (!function_exists('ccSchoolSettlementBuildBatchReference')) {
  function ccSchoolSettlementBuildBatchReference(int $schoolId, string $scheduledFor): string
  {
    $scheduledToken = preg_replace('/[^0-9]/', '', $scheduledFor);
    if ($scheduledToken === '') {
      $scheduledToken = date('Ymd');
    }

    try {
      $suffix = strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable $error) {
      $suffix = strtoupper(dechex(mt_rand(0x100000, 0xFFFFFF)));
    }

    return sprintf('cc_settle_%d_%s_%s', $schoolId, $scheduledToken, $suffix);
  }
}

if (!function_exists('ccSchoolSettlementDecodeJson')) {
  function ccSchoolSettlementDecodeJson($value): array
  {
    if (is_array($value)) {
      return $value;
    }

    $text = trim((string) $value);
    if ($text === '') {
      return [];
    }

    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : [];
  }
}

if (!function_exists('ccSchoolSettlementEncodeJson')) {
  function ccSchoolSettlementEncodeJson(mysqli $conn, array $payload): string
  {
    return mysqli_real_escape_string(
      $conn,
      json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
  }
}

if (!function_exists('ccSchoolSettlementAppendNotes')) {
  function ccSchoolSettlementAppendNotes(string $existing, string $addition): string
  {
    $parts = [];
    $existing = trim($existing);
    $addition = trim($addition);

    if ($existing !== '') {
      $parts[] = $existing;
    }
    if ($addition !== '') {
      $parts[] = $addition;
    }

    return implode("\n\n", $parts);
  }
}

if (!function_exists('ccSchoolSettlementNormalizeDate')) {
  function ccSchoolSettlementNormalizeDate(string $value): string
  {
    $value = trim($value);
    if ($value === '') {
      return date('Y-m-d');
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
      return date('Y-m-d');
    }

    return date('Y-m-d', $timestamp);
  }
}

if (!function_exists('ccSchoolSettlementGatewayPostJson')) {
  function ccSchoolSettlementGatewayPostJson(string $url, array $headers, array $payload): array
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error !== '') {
      return [
        'ok' => false,
        'http_code' => $httpCode,
        'error' => $error,
        'data' => null,
      ];
    }

    $decoded = json_decode((string) $response, true);
    return [
      'ok' => true,
      'http_code' => $httpCode,
      'error' => '',
      'data' => is_array($decoded) ? $decoded : [],
      'raw' => (string) $response,
    ];
  }
}

if (!function_exists('ccSchoolSettlementGatewayGetJson')) {
  function ccSchoolSettlementGatewayGetJson(string $url, array $headers): array
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error !== '') {
      return [
        'ok' => false,
        'http_code' => $httpCode,
        'error' => $error,
        'data' => null,
      ];
    }

    $decoded = json_decode((string) $response, true);
    return [
      'ok' => true,
      'http_code' => $httpCode,
      'error' => '',
      'data' => is_array($decoded) ? $decoded : [],
      'raw' => (string) $response,
    ];
  }
}

if (!function_exists('ccSchoolSettlementLookupPaystackTransfer')) {
  function ccSchoolSettlementLookupPaystackTransfer(string $reference): array
  {
    $reference = trim($reference);
    if ($reference === '') {
      return [
        'status' => 'invalid_reference',
        'message' => 'Enter a Paystack transfer reference.',
      ];
    }

    $secret = defined('PAYSTACK_SECRET_KEY') ? trim((string) PAYSTACK_SECRET_KEY) : '';
    if ($secret === '') {
      return [
        'status' => 'missing_secret',
        'message' => 'Paystack secret key is not configured in command center.',
      ];
    }

    $headers = [
      'Authorization: Bearer ' . $secret,
      'Cache-Control: no-cache',
    ];
    $encodedReference = rawurlencode($reference);
    $attempts = [];
    $urls = [
      'verify' => 'https://api.paystack.co/transfer/verify/' . $encodedReference,
      'fetch' => 'https://api.paystack.co/transfer/' . $encodedReference,
    ];

    foreach ($urls as $lookupType => $url) {
      $response = ccSchoolSettlementGatewayGetJson($url, $headers);
      if (!$response['ok']) {
        $attempts[] = [
          'lookup_type' => $lookupType,
          'http_code' => (int) ($response['http_code'] ?? 0),
          'error' => (string) ($response['error'] ?? 'Unknown error'),
        ];
        continue;
      }

      $payload = $response['data'] ?? [];
      if (($payload['status'] ?? false) !== true || !isset($payload['data']) || !is_array($payload['data'])) {
        $attempts[] = [
          'lookup_type' => $lookupType,
          'http_code' => (int) ($response['http_code'] ?? 0),
          'message' => (string) ($payload['message'] ?? 'Transfer reference not found on Paystack.'),
        ];
        continue;
      }

      $transferData = $payload['data'];
      $recipient = $transferData['recipient'] ?? [];
      $recipientDetails = is_array($recipient['details'] ?? null) ? $recipient['details'] : [];

      return [
        'status' => 'success',
        'lookup_type' => $lookupType,
        'message' => (string) ($payload['message'] ?? 'Transfer lookup succeeded.'),
        'transfer' => $transferData,
        'summary' => [
          'reference' => (string) ($transferData['reference'] ?? ''),
          'transfer_code' => (string) ($transferData['transfer_code'] ?? ''),
          'amount_kobo' => (int) ($transferData['amount'] ?? 0),
          'currency' => (string) ($transferData['currency'] ?? ''),
          'status' => strtolower(trim((string) ($transferData['status'] ?? ''))),
          'recipient_name' => (string) ($recipient['name'] ?? ''),
          'recipient_code' => (string) ($recipient['recipient_code'] ?? ''),
          'recipient_account_number' => (string) ($recipientDetails['account_number'] ?? ''),
          'recipient_bank_name' => (string) ($recipientDetails['bank_name'] ?? ''),
          'transferred_at' => (string) ($transferData['transferred_at'] ?? ($transferData['updatedAt'] ?? '')),
        ],
      ];
    }

    $firstAttempt = $attempts[0] ?? [];
    return [
      'status' => 'lookup_failed',
      'message' => (string) ($firstAttempt['message'] ?? $firstAttempt['error'] ?? 'Paystack could not verify that transfer reference.'),
      'attempts' => $attempts,
    ];
  }
}

if (!function_exists('ccSchoolSettlementCreatePaystackRecipient')) {
  function ccSchoolSettlementCreatePaystackRecipient(array $settlementAccount): array
  {
    $secret = defined('PAYSTACK_SECRET_KEY') ? trim((string) PAYSTACK_SECRET_KEY) : '';
    if ($secret === '') {
      return [
        'status' => 'missing_secret',
        'message' => 'Paystack secret key is not configured in command center.',
      ];
    }

    $payload = [
      'type' => 'nuban',
      'name' => (string) ($settlementAccount['acct_name'] ?? ''),
      'account_number' => (string) ($settlementAccount['acct_number'] ?? ''),
      'bank_code' => (string) ($settlementAccount['bank'] ?? ''),
      'currency' => 'NGN',
    ];

    if ($payload['name'] === '' || $payload['account_number'] === '' || $payload['bank_code'] === '') {
      return [
        'status' => 'invalid_account',
        'message' => 'Settlement account is missing an account name, number, or bank code.',
      ];
    }

    $response = ccSchoolSettlementGatewayPostJson(
      'https://api.paystack.co/transferrecipient',
      ['Authorization: Bearer ' . $secret],
      $payload
    );

    $responseData = $response['data'] ?? [];
    if (!$response['ok'] || empty($responseData['status']) || empty($responseData['data']['recipient_code'])) {
      $message = (string) ($responseData['message'] ?? ($response['error'] !== '' ? $response['error'] : 'Unable to create Paystack transfer recipient.'));
      return [
        'status' => 'recipient_failed',
        'message' => $message,
        'raw_response' => $responseData,
      ];
    }

    return [
      'status' => 'success',
      'recipient_code' => (string) $responseData['data']['recipient_code'],
      'raw_response' => $responseData,
    ];
  }
}

if (!function_exists('ccSchoolSettlementInitiatePaystackTransfer')) {
  function ccSchoolSettlementInitiatePaystackTransfer(array $settlementAccount, int $amount, string $reference, string $narration): array
  {
    $secret = defined('PAYSTACK_SECRET_KEY') ? trim((string) PAYSTACK_SECRET_KEY) : '';
    if ($secret === '') {
      return [
        'status' => 'missing_secret',
        'message' => 'Paystack secret key is not configured in command center.',
      ];
    }

    if ($amount <= 0) {
      return [
        'status' => 'invalid_amount',
        'message' => 'Transfer amount must be greater than zero.',
      ];
    }

    $recipient = ccSchoolSettlementCreatePaystackRecipient($settlementAccount);
    if (($recipient['status'] ?? '') !== 'success') {
      return $recipient;
    }

    $payload = [
      'source' => 'balance',
      'amount' => $amount * 100,
      'recipient' => $recipient['recipient_code'],
      'reason' => $narration,
      'reference' => $reference,
    ];

    $response = ccSchoolSettlementGatewayPostJson(
      'https://api.paystack.co/transfer',
      ['Authorization: Bearer ' . $secret],
      $payload
    );

    $responseData = $response['data'] ?? [];
    if (!$response['ok'] || empty($responseData['status'])) {
      $message = (string) ($responseData['message'] ?? ($response['error'] !== '' ? $response['error'] : 'Unable to initiate Paystack transfer.'));
      return [
        'status' => 'transfer_failed',
        'message' => $message,
        'raw_response' => $responseData,
      ];
    }

    $transferData = $responseData['data'] ?? [];

    return [
      'status' => 'success',
      'provider_reference' => (string) ($transferData['reference'] ?? $reference),
      'transfer_code' => (string) ($transferData['transfer_code'] ?? ''),
      'provider_status' => strtolower(trim((string) ($transferData['status'] ?? 'pending'))),
      'raw_response' => $responseData,
    ];
  }
}

if (!function_exists('ccSchoolSettlementGetAccount')) {
  function ccSchoolSettlementGetAccount(mysqli $conn, int $schoolId): ?array
  {
    if ($schoolId <= 0) {
      return null;
    }

    $sql = "SELECT sa.*, s.name AS school_name
            FROM settlement_accounts sa
            LEFT JOIN schools s ON s.id = sa.school_id
            WHERE sa.school_id = $schoolId
              AND sa.type = 'school'
              AND (sa.status = 'active' OR sa.status IS NULL OR sa.status = '')
            ORDER BY sa.id DESC
            LIMIT 1";
    $rs = mysqli_query($conn, $sql);
    if ($rs && mysqli_num_rows($rs) > 0) {
      return mysqli_fetch_assoc($rs) ?: null;
    }

    return null;
  }
}

if (!function_exists('ccSchoolSettlementGetProvider')) {
  function ccSchoolSettlementGetProvider(array $settlementAccount): string
  {
    $gateway = strtolower(trim((string) ($settlementAccount['gateway'] ?? '')));
    if (in_array($gateway, ['paystack', 'flutterwave'], true)) {
      return $gateway;
    }

    if (trim((string) ($settlementAccount['flw_id'] ?? '')) !== '') {
      return 'flutterwave';
    }

    return 'paystack';
  }
}

if (!function_exists('ccSchoolSettlementGetActiveBatch')) {
  function ccSchoolSettlementGetActiveBatch(mysqli $conn, int $schoolId, bool $forUpdate = false): ?array
  {
    if ($schoolId <= 0) {
      return null;
    }

    $sql = "SELECT *
            FROM settlement_batches
            WHERE school_id = $schoolId
              AND status IN ('pending', 'processing')
            ORDER BY id DESC
            LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $rs = mysqli_query($conn, $sql);
    if ($rs && mysqli_num_rows($rs) > 0) {
      return mysqli_fetch_assoc($rs) ?: null;
    }

    return null;
  }
}

if (!function_exists('ccSchoolSettlementSanitizeDateTimeToken')) {
  function ccSchoolSettlementSanitizeDateTimeToken(string $value): string
  {
    return preg_replace('/[^0-9:\- ]/', '', $value);
  }
}

if (!function_exists('ccSchoolSettlementBuildEligibleLedgerSql')) {
  function ccSchoolSettlementBuildEligibleLedgerSql(int $schoolId, bool $forUpdate = false, ?string $windowStart = null, ?string $windowEnd = null): string
  {
    $windowClause = '';
    if ($windowStart !== null && $windowStart !== '') {
      $windowClause .= " AND spl.created_at >= '" . ccSchoolSettlementSanitizeDateTimeToken($windowStart) . "'";
    }
    if ($windowEnd !== null && $windowEnd !== '') {
      $windowClause .= " AND spl.created_at <= '" . ccSchoolSettlementSanitizeDateTimeToken($windowEnd) . "'";
    }

    $sql = "SELECT spl.id,
                   spl.source_ref_id,
                   spl.payable_amount,
                   spl.settled_amount,
                   spl.status,
                   spl.created_at
            FROM school_payable_ledger spl
            WHERE spl.school_id = $schoolId
              AND spl.status IN ('pending', 'partially_settled')
              AND spl.payable_amount > spl.settled_amount
              $windowClause
              AND NOT EXISTS (
                SELECT 1
                FROM settlement_batch_items sbi
                INNER JOIN settlement_batches sb ON sb.id = sbi.settlement_batch_id
                WHERE sbi.school_payable_ledger_id = spl.id
                  AND sb.status IN ('pending', 'processing')
                  AND sbi.status IN ('pending', 'processing')
              )
            ORDER BY spl.created_at ASC, spl.id ASC";

    if ($forUpdate) {
      $sql .= ' FOR UPDATE';
    }

    return $sql;
  }
}

if (!function_exists('ccSchoolSettlementBuildAllocations')) {
  function ccSchoolSettlementBuildAllocations(mysqli $conn, int $schoolId, int $targetAmount, bool $forUpdate = false, ?string $ledgerWindowStart = null, ?string $ledgerWindowEnd = null): array
  {
    $allocations = [];
    $allocatedAmount = 0;
    $targetAmount = max(0, $targetAmount);

    if ($schoolId <= 0 || $targetAmount <= 0) {
      return [
        'total_amount' => 0,
        'total_records' => 0,
        'items' => [],
      ];
    }

    $ledgerRs = mysqli_query($conn, ccSchoolSettlementBuildEligibleLedgerSql($schoolId, $forUpdate, $ledgerWindowStart, $ledgerWindowEnd));
    if (!$ledgerRs) {
      throw new RuntimeException('Failed to load eligible school payable ledger rows: ' . mysqli_error($conn));
    }

    while ($ledgerRow = mysqli_fetch_assoc($ledgerRs)) {
      $outstanding = max(0, (int) ($ledgerRow['payable_amount'] ?? 0) - (int) ($ledgerRow['settled_amount'] ?? 0));
      if ($outstanding <= 0) {
        continue;
      }

      $remaining = $targetAmount - $allocatedAmount;
      if ($remaining <= 0) {
        break;
      }

      $allocation = min($outstanding, $remaining);
      $allocations[] = [
        'ledger_id' => (int) ($ledgerRow['id'] ?? 0),
        'source_ref_id' => (string) ($ledgerRow['source_ref_id'] ?? ''),
        'payable_amount' => (int) ($ledgerRow['payable_amount'] ?? 0),
        'settled_amount' => (int) ($ledgerRow['settled_amount'] ?? 0),
        'outstanding_amount' => $outstanding,
        'allocated_amount' => (int) $allocation,
        'status' => (string) ($ledgerRow['status'] ?? ''),
        'created_at' => (string) ($ledgerRow['created_at'] ?? ''),
      ];
      $allocatedAmount += $allocation;
    }

    return [
      'total_amount' => $allocatedAmount,
      'total_records' => count($allocations),
      'items' => $allocations,
    ];
  }
}

if (!function_exists('ccSchoolSettlementGetBatchDetails')) {
  function ccSchoolSettlementGetBatchDetails(mysqli $conn, int $batchId): ?array
  {
    if ($batchId <= 0) {
      return null;
    }

    $batchSql = "SELECT sb.*, s.name AS school_name
                 FROM settlement_batches sb
                 LEFT JOIN schools s ON s.id = sb.school_id
                 WHERE sb.id = $batchId
                 LIMIT 1";
    $batchRs = mysqli_query($conn, $batchSql);
    if (!$batchRs || mysqli_num_rows($batchRs) < 1) {
      return null;
    }

    $batch = mysqli_fetch_assoc($batchRs);
    $items = [];
    $itemSql = "SELECT sbi.id,
                       sbi.source_ref_id,
                       sbi.allocated_amount,
                       sbi.status,
                       sbi.notes,
                       sbi.created_at,
                       spl.payable_amount,
                       spl.settled_amount,
                       spl.status AS ledger_status,
                       spl.created_at AS ledger_created_at
                FROM settlement_batch_items sbi
                INNER JOIN school_payable_ledger spl ON spl.id = sbi.school_payable_ledger_id
                WHERE sbi.settlement_batch_id = $batchId
                ORDER BY sbi.id ASC";
    $itemRs = mysqli_query($conn, $itemSql);
    if ($itemRs) {
      while ($itemRow = mysqli_fetch_assoc($itemRs)) {
        $payableAmount = (int) ($itemRow['payable_amount'] ?? 0);
        $settledAmount = (int) ($itemRow['settled_amount'] ?? 0);
        $currentOutstanding = max(0, $payableAmount - $settledAmount);
        $itemRow['allocated_amount'] = (int) ($itemRow['allocated_amount'] ?? 0);
        $itemRow['payable_amount'] = $payableAmount;
        $itemRow['settled_amount'] = $settledAmount;
        $itemRow['current_outstanding'] = $currentOutstanding;
        $itemRow['over_allocated'] = $currentOutstanding < $itemRow['allocated_amount'] ? 1 : 0;
        $items[] = $itemRow;
      }
    }

    $batch['items'] = $items;
    $batch['provider_response_data'] = ccSchoolSettlementDecodeJson($batch['provider_response'] ?? '');

    return $batch;
  }
}

if (!function_exists('ccSchoolSettlementListRecentBatches')) {
  function ccSchoolSettlementListRecentBatches(mysqli $conn, int $schoolId, int $limit = 10): array
  {
    if ($schoolId <= 0) {
      return [];
    }

    $limit = max(1, min(50, $limit));
    $sql = "SELECT sb.*,
                   COUNT(sbi.id) AS item_count,
                   COALESCE(SUM(sbi.allocated_amount), 0) AS allocated_total,
                   SUM(CASE WHEN sbi.status = 'settled' THEN 1 ELSE 0 END) AS settled_item_count,
                   SUM(CASE WHEN sbi.status = 'failed' THEN 1 ELSE 0 END) AS failed_item_count
            FROM settlement_batches sb
            LEFT JOIN settlement_batch_items sbi ON sbi.settlement_batch_id = sb.id
            WHERE sb.school_id = $schoolId
            GROUP BY sb.id
            ORDER BY sb.id DESC
            LIMIT $limit";
    $rs = mysqli_query($conn, $sql);
    if (!$rs) {
      throw new RuntimeException('Failed to load recent settlement batches: ' . mysqli_error($conn));
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($rs)) {
      $row['provider_response_data'] = ccSchoolSettlementDecodeJson($row['provider_response'] ?? '');
      $rows[] = $row;
    }

    return $rows;
  }
}

if (!function_exists('ccSchoolSettlementGetSnapshot')) {
  function ccSchoolSettlementGetSnapshot(mysqli $conn, int $schoolId): array
  {
    if (!ccSchoolSettlementTablesReady($conn)) {
      return [
        'status' => 'missing_tables',
        'message' => 'Settlement tables are not available in command center.',
      ];
    }

    if ($schoolId <= 0) {
      return [
        'status' => 'invalid_school',
        'message' => 'Select a school to continue.',
      ];
    }

    $schoolSql = "SELECT id, name FROM schools WHERE id = $schoolId LIMIT 1";
    $schoolRs = mysqli_query($conn, $schoolSql);
    if (!$schoolRs || mysqli_num_rows($schoolRs) < 1) {
      return [
        'status' => 'missing_school',
        'message' => 'School not found.',
      ];
    }

    $school = mysqli_fetch_assoc($schoolRs);
    $walletSql = "SELECT * FROM school_internal_wallets WHERE school_id = $schoolId LIMIT 1";
    $walletRs = mysqli_query($conn, $walletSql);
    $wallet = $walletRs && mysqli_num_rows($walletRs) > 0 ? mysqli_fetch_assoc($walletRs) : null;
    $settlementAccount = ccSchoolSettlementGetAccount($conn, $schoolId);

    $allOutstandingSql = "SELECT COUNT(*) AS record_count,
                                 COALESCE(SUM(payable_amount - settled_amount), 0) AS outstanding_amount
                          FROM school_payable_ledger
                          WHERE school_id = $schoolId
                            AND status IN ('pending', 'partially_settled')
                            AND payable_amount > settled_amount";
    $allOutstandingRs = mysqli_query($conn, $allOutstandingSql);
    $allOutstanding = $allOutstandingRs ? (mysqli_fetch_assoc($allOutstandingRs) ?: []) : [];

    $stageableSql = "SELECT COUNT(*) AS record_count,
                            COALESCE(SUM(spl.payable_amount - spl.settled_amount), 0) AS outstanding_amount
                     FROM school_payable_ledger spl
                     WHERE spl.school_id = $schoolId
                       AND spl.status IN ('pending', 'partially_settled')
                       AND spl.payable_amount > spl.settled_amount
                       AND NOT EXISTS (
                         SELECT 1
                         FROM settlement_batch_items sbi
                         INNER JOIN settlement_batches sb ON sb.id = sbi.settlement_batch_id
                         WHERE sbi.school_payable_ledger_id = spl.id
                           AND sb.status IN ('pending', 'processing')
                           AND sbi.status IN ('pending', 'processing')
                       )";
    $stageableRs = mysqli_query($conn, $stageableSql);
    $stageable = $stageableRs ? (mysqli_fetch_assoc($stageableRs) ?: []) : [];

    $pendingBalance = (int) ($wallet['pending_payout_balance'] ?? 0);
    $stageableAmount = (int) ($stageable['outstanding_amount'] ?? 0);
    $previewTarget = min($pendingBalance, ccSchoolSettlementCapPerSchool($conn), $stageableAmount);

    $activeBatch = ccSchoolSettlementGetActiveBatch($conn, $schoolId, false);
    $activeBatchDetails = $activeBatch ? ccSchoolSettlementGetBatchDetails($conn, (int) ($activeBatch['id'] ?? 0)) : null;
    $preview = $activeBatchDetails ? ['total_amount' => 0, 'total_records' => 0, 'items' => []] : ccSchoolSettlementBuildAllocations($conn, $schoolId, $previewTarget, false);

    return [
      'status' => 'success',
      'school' => $school,
      'wallet' => $wallet ?: [
        'current_balance' => 0,
        'pending_payout_balance' => 0,
        'carry_forward_balance' => 0,
        'status' => 'missing',
      ],
      'settlement_account' => $settlementAccount,
      'summary' => [
        'all_outstanding_records' => (int) ($allOutstanding['record_count'] ?? 0),
        'all_outstanding_amount' => (int) ($allOutstanding['outstanding_amount'] ?? 0),
        'stageable_records' => (int) ($stageable['record_count'] ?? 0),
        'stageable_amount' => $stageableAmount,
        'preview_target_amount' => $previewTarget,
        'cap_per_school' => ccSchoolSettlementCapPerSchool($conn),
        'has_active_batch' => $activeBatchDetails ? 1 : 0,
      ],
      'active_batch' => $activeBatchDetails,
      'preview' => $preview,
      'recent_batches' => ccSchoolSettlementListRecentBatches($conn, $schoolId, 12),
    ];
  }
}

if (!function_exists('ccSchoolSettlementStageBatch')) {
  function ccSchoolSettlementStageBatch(mysqli $conn, int $schoolId, string $scheduledFor, int $adminId, int $adminRole, string $notes = '', ?string $ledgerWindowStart = null, ?string $ledgerWindowEnd = null): array
  {
    if (!ccSchoolSettlementTablesReady($conn)) {
      return [
        'status' => 'missing_tables',
        'message' => 'Settlement tables are not available in command center.',
      ];
    }

    $schoolId = (int) $schoolId;
    $adminId = (int) $adminId;
    $adminRole = (int) $adminRole;
    $scheduledFor = ccSchoolSettlementNormalizeDate($scheduledFor);
    $notes = trim($notes);

    if ($schoolId <= 0) {
      return [
        'status' => 'invalid_school',
        'message' => 'Select a valid school to stage settlement.',
      ];
    }

    mysqli_begin_transaction($conn);
    try {
      $walletSql = "SELECT siw.*, s.name AS school_name
                    FROM school_internal_wallets siw
                    LEFT JOIN schools s ON s.id = siw.school_id
                    WHERE siw.school_id = $schoolId
                    LIMIT 1 FOR UPDATE";
      $walletRs = mysqli_query($conn, $walletSql);
      if (!$walletRs || mysqli_num_rows($walletRs) < 1) {
        mysqli_commit($conn);
        return [
          'status' => 'no_wallet',
          'message' => 'School internal wallet not found.',
        ];
      }

      $wallet = mysqli_fetch_assoc($walletRs);
      $pendingBalance = (int) ($wallet['pending_payout_balance'] ?? 0);
      $schoolName = (string) ($wallet['school_name'] ?? ('School #' . $schoolId));
      if ((string) ($wallet['status'] ?? 'active') !== 'active' || $pendingBalance <= 0) {
        mysqli_commit($conn);
        return [
          'status' => 'no_funds',
          'school_id' => $schoolId,
          'school_name' => $schoolName,
          'message' => 'No pending settlement balance is available for this school.',
        ];
      }

      $activeBatch = ccSchoolSettlementGetActiveBatch($conn, $schoolId, true);
      if ($activeBatch) {
        mysqli_commit($conn);
        return [
          'status' => 'active_batch_exists',
          'message' => 'This school already has a pending settlement batch.',
          'batch' => ccSchoolSettlementGetBatchDetails($conn, (int) ($activeBatch['id'] ?? 0)),
        ];
      }

      $settlementAccount = ccSchoolSettlementGetAccount($conn, $schoolId);
      if (!$settlementAccount) {
        mysqli_commit($conn);
        return [
          'status' => 'missing_account',
          'school_id' => $schoolId,
          'school_name' => $schoolName,
          'message' => 'School settlement account is missing.',
        ];
      }

      $allocationTarget = min($pendingBalance, ccSchoolSettlementCapPerSchool($conn));
      $allocations = ccSchoolSettlementBuildAllocations($conn, $schoolId, $allocationTarget, true, $ledgerWindowStart, $ledgerWindowEnd);
      if ((int) ($allocations['total_amount'] ?? 0) <= 0 || empty($allocations['items'])) {
        mysqli_commit($conn);
        return [
          'status' => 'no_eligible_records',
          'school_id' => $schoolId,
          'school_name' => $schoolName,
          'message' => 'No outstanding ledger rows are eligible for settlement staging.',
        ];
      }

      $provider = 'paystack';
      $accountProvider = ccSchoolSettlementGetProvider($settlementAccount);
      $batchReference = ccSchoolSettlementBuildBatchReference($schoolId, $scheduledFor);
      $batchReferenceSafe = mysqli_real_escape_string($conn, $batchReference);
      $providerSafe = mysqli_real_escape_string($conn, $provider);

      $stageNote = sprintf(
        'Staged manually from cc_dashboard by admin #%d (role %d) for %s.',
        $adminId,
        $adminRole,
        $scheduledFor
      );
      if ($notes !== '') {
        $stageNote = ccSchoolSettlementAppendNotes($stageNote, $notes);
      }
      $notesSafe = mysqli_real_escape_string($conn, $stageNote);

      $providerResponseSafe = ccSchoolSettlementEncodeJson($conn, [
        'source' => 'cc_dashboard',
        'workflow' => 'manual_school_settlement',
        'transfer_provider' => 'paystack',
        'settlement_account_provider' => $accountProvider,
        'staged' => [
          'admin_id' => $adminId,
          'admin_role' => $adminRole,
          'scheduled_for' => $scheduledFor,
          'created_at' => date('c'),
          'notes' => $notes,
        ],
      ]);

      $totalAmount = (int) ($allocations['total_amount'] ?? 0);
      $totalRecords = (int) ($allocations['total_records'] ?? 0);
      $insertBatchSql = "INSERT INTO settlement_batches (
                          school_id, scheduled_for, batch_reference, status, total_amount, total_records,
                          transfer_provider, provider_response, notes
                        ) VALUES (
                          $schoolId, '$scheduledFor', '$batchReferenceSafe', 'pending', $totalAmount, $totalRecords,
                          '$providerSafe', '$providerResponseSafe', '$notesSafe'
                        )";
      if (!mysqli_query($conn, $insertBatchSql)) {
        throw new RuntimeException('Failed to create settlement batch: ' . mysqli_error($conn));
      }

      $batchId = (int) mysqli_insert_id($conn);
      foreach ($allocations['items'] as $allocation) {
        $ledgerId = (int) ($allocation['ledger_id'] ?? 0);
        $sourceRefSafe = mysqli_real_escape_string($conn, (string) ($allocation['source_ref_id'] ?? ''));
        $allocatedAmount = (int) ($allocation['allocated_amount'] ?? 0);
        $insertItemSql = "INSERT INTO settlement_batch_items (
                            settlement_batch_id, school_payable_ledger_id, source_ref_id, allocated_amount, status, notes
                          ) VALUES (
                            $batchId, $ledgerId, '$sourceRefSafe', $allocatedAmount, 'pending', NULL
                          )";
        if (!mysqli_query($conn, $insertItemSql)) {
          throw new RuntimeException('Failed to insert settlement batch item: ' . mysqli_error($conn));
        }
      }

      mysqli_commit($conn);

      return [
        'status' => 'success',
        'message' => 'Settlement batch staged successfully.',
        'batch' => ccSchoolSettlementGetBatchDetails($conn, $batchId),
      ];
    } catch (Throwable $error) {
      mysqli_rollback($conn);
      throw $error;
    }
  }
}

if (!function_exists('ccSchoolSettlementDispatchBatchTransfer')) {
  /**
   * Sends a staged ('pending') batch to Paystack for a real transfer and
   * moves it to 'processing'. Does NOT mark the batch completed or touch
   * the wallet/ledger — that only happens once the transfer is confirmed
   * successful via ccSchoolSettlementReconcileProcessingBatches(), which
   * reuses ccSchoolSettlementCompleteBatch()'s existing verify-then-complete
   * safety checks (status + amount match against Paystack).
   */
  function ccSchoolSettlementDispatchBatchTransfer(mysqli $conn, int $batchId): array
  {
    if (!ccSchoolSettlementTablesReady($conn)) {
      return [
        'status' => 'missing_tables',
        'message' => 'Settlement tables are not available in command center.',
      ];
    }

    $batchId = (int) $batchId;
    if ($batchId <= 0) {
      return [
        'status' => 'invalid_batch',
        'message' => 'Select a valid settlement batch.',
      ];
    }

    mysqli_begin_transaction($conn);
    try {
      $batchSql = "SELECT * FROM settlement_batches WHERE id = $batchId LIMIT 1 FOR UPDATE";
      $batchRs = mysqli_query($conn, $batchSql);
      if (!$batchRs || mysqli_num_rows($batchRs) < 1) {
        mysqli_rollback($conn);
        return [
          'status' => 'missing_batch',
          'message' => 'Settlement batch not found.',
        ];
      }

      $batch = mysqli_fetch_assoc($batchRs);
      $currentStatus = (string) ($batch['status'] ?? '');
      if ($currentStatus !== 'pending') {
        mysqli_rollback($conn);
        return [
          'status' => 'invalid_state',
          'message' => 'Only pending batches can be dispatched for transfer.',
          'batch' => $batch,
        ];
      }

      $transferProvider = strtolower(trim((string) ($batch['transfer_provider'] ?? 'paystack')));
      if ($transferProvider !== 'paystack') {
        mysqli_rollback($conn);
        return [
          'status' => 'unsupported_provider',
          'message' => 'Automated dispatch currently only supports Paystack as the settlement transfer provider.',
        ];
      }

      $schoolId = (int) ($batch['school_id'] ?? 0);
      $batchReference = (string) ($batch['batch_reference'] ?? '');
      $totalAmount = (int) ($batch['total_amount'] ?? 0);

      $settlementAccount = ccSchoolSettlementGetAccount($conn, $schoolId);
      if (!$settlementAccount) {
        mysqli_rollback($conn);
        return [
          'status' => 'missing_account',
          'school_id' => $schoolId,
          'message' => 'School settlement account is missing.',
        ];
      }

      $schoolName = trim((string) ($settlementAccount['school_name'] ?? ('School #' . $schoolId)));
      $narration = sprintf('Nivasity school settlement for %s (%s)', $schoolName, $batchReference);

      $transfer = ccSchoolSettlementInitiatePaystackTransfer($settlementAccount, $totalAmount, $batchReference, $narration);

      if (($transfer['status'] ?? '') !== 'success') {
        $errorMessage = (string) ($transfer['message'] ?? 'Failed to initiate Paystack transfer.');
        $errorSafe = mysqli_real_escape_string($conn, $errorMessage);
        $updateFailedAttemptSql = "UPDATE settlement_batches
                                   SET last_error = '$errorSafe',
                                       updated_at = NOW()
                                   WHERE id = $batchId";
        mysqli_query($conn, $updateFailedAttemptSql);
        mysqli_commit($conn);

        return [
          'status' => 'dispatch_failed',
          'message' => $errorMessage,
          'batch' => ccSchoolSettlementGetBatchDetails($conn, $batchId),
        ];
      }

      $providerReference = (string) ($transfer['provider_reference'] ?? $batchReference);
      $providerReferenceSafe = mysqli_real_escape_string($conn, $providerReference);

      $providerResponse = ccSchoolSettlementDecodeJson($batch['provider_response'] ?? '');
      $providerResponse['dispatch'] = [
        'transfer_code' => (string) ($transfer['transfer_code'] ?? ''),
        'provider_status' => (string) ($transfer['provider_status'] ?? 'pending'),
        'dispatched_at' => date('c'),
        'raw_response' => $transfer['raw_response'] ?? [],
      ];
      $providerResponseSafe = ccSchoolSettlementEncodeJson($conn, $providerResponse);

      $updateBatchSql = "UPDATE settlement_batches
                         SET status = 'processing',
                             provider_reference = '$providerReferenceSafe',
                             provider_response = '$providerResponseSafe',
                             started_at = IFNULL(started_at, NOW()),
                             last_error = NULL,
                             updated_at = NOW()
                         WHERE id = $batchId";
      if (!mysqli_query($conn, $updateBatchSql)) {
        throw new RuntimeException('Failed to update settlement batch after dispatch: ' . mysqli_error($conn));
      }

      mysqli_commit($conn);

      return [
        'status' => 'success',
        'message' => 'Transfer dispatched to Paystack and is awaiting confirmation.',
        'batch' => ccSchoolSettlementGetBatchDetails($conn, $batchId),
      ];
    } catch (Throwable $error) {
      mysqli_rollback($conn);
      throw $error;
    }
  }
}

if (!function_exists('ccSchoolSettlementReconcileProcessingBatches')) {
  /**
   * Finds every batch currently 'processing' (dispatched to Paystack but
   * not yet confirmed) and checks its real status. Successful transfers are
   * completed via the existing verify-then-complete logic in
   * ccSchoolSettlementCompleteBatch(); failed/reversed transfers are
   * released via ccSchoolSettlementFailBatch(); anything still pending on
   * Paystack's side is left as 'processing' to be checked again later.
   */
  function ccSchoolSettlementReconcileProcessingBatches(mysqli $conn, int $adminId = 0): array
  {
    $results = [
      'checked' => 0,
      'completed' => 0,
      'failed' => 0,
      'still_pending' => 0,
      'errors' => [],
    ];

    if (!ccSchoolSettlementTablesReady($conn)) {
      return $results;
    }

    $rs = mysqli_query($conn, "SELECT id, provider_reference FROM settlement_batches WHERE status = 'processing' ORDER BY id ASC");
    if (!$rs) {
      return $results;
    }

    $processingBatches = [];
    while ($row = mysqli_fetch_assoc($rs)) {
      $processingBatches[] = $row;
    }

    foreach ($processingBatches as $row) {
      $batchId = (int) ($row['id'] ?? 0);
      $providerReference = trim((string) ($row['provider_reference'] ?? ''));
      $results['checked']++;

      if ($batchId <= 0 || $providerReference === '') {
        continue;
      }

      try {
        $lookup = ccSchoolSettlementLookupPaystackTransfer($providerReference);
        if (($lookup['status'] ?? '') !== 'success') {
          $results['still_pending']++;
          continue;
        }

        $transferStatus = strtolower(trim((string) ($lookup['summary']['status'] ?? '')));

        if (in_array($transferStatus, ['success', 'successful'], true)) {
          $completion = ccSchoolSettlementCompleteBatch($conn, $batchId, $providerReference, $adminId, 1, 'Auto-completed after Paystack confirmed the transfer.');
          if (($completion['status'] ?? '') === 'success') {
            $results['completed']++;
          } else {
            $results['still_pending']++;
            $results['errors'][] = [
              'batch_id' => $batchId,
              'reason' => (string) ($completion['message'] ?? 'Unable to complete after confirmation.'),
            ];
          }
          continue;
        }

        if (in_array($transferStatus, ['failed', 'reversed'], true)) {
          ccSchoolSettlementFailBatch($conn, $batchId, $adminId, 1, 'Paystack reported transfer status: ' . $transferStatus);
          $results['failed']++;
          continue;
        }

        // otp, pending, or any other in-flight status: check again next run.
        $results['still_pending']++;
      } catch (Throwable $error) {
        $results['errors'][] = [
          'batch_id' => $batchId,
          'reason' => $error->getMessage(),
        ];
      }
    }

    return $results;
  }
}

if (!function_exists('ccSchoolSettlementCompleteBatch')) {
  function ccSchoolSettlementCompleteBatch(mysqli $conn, int $batchId, string $providerReference, int $adminId, int $adminRole, string $notes = ''): array
  {
    if (!ccSchoolSettlementTablesReady($conn)) {
      return [
        'status' => 'missing_tables',
        'message' => 'Settlement tables are not available in command center.',
      ];
    }

    $batchId = (int) $batchId;
    $adminId = (int) $adminId;
    $adminRole = (int) $adminRole;
    $providerReference = trim($providerReference);
    $notes = trim($notes);

    if ($batchId <= 0) {
      return [
        'status' => 'invalid_batch',
        'message' => 'Select a valid settlement batch.',
      ];
    }

    if ($providerReference === '') {
      return [
        'status' => 'missing_provider_reference',
        'message' => 'Enter the Paystack transfer reference before completion.',
      ];
    }

    $paystackLookup = ccSchoolSettlementLookupPaystackTransfer($providerReference);
    if (($paystackLookup['status'] ?? '') !== 'success') {
      return [
        'status' => 'paystack_lookup_failed',
        'message' => (string) ($paystackLookup['message'] ?? 'Paystack could not verify that transfer reference.'),
        'lookup' => $paystackLookup,
      ];
    }

    mysqli_begin_transaction($conn);
    try {
      $batchSql = "SELECT * FROM settlement_batches WHERE id = $batchId LIMIT 1 FOR UPDATE";
      $batchRs = mysqli_query($conn, $batchSql);
      if (!$batchRs || mysqli_num_rows($batchRs) < 1) {
        mysqli_rollback($conn);
        return [
          'status' => 'missing_batch',
          'message' => 'Settlement batch not found.',
        ];
      }

      $batch = mysqli_fetch_assoc($batchRs);
      $currentStatus = (string) ($batch['status'] ?? '');
      if (!in_array($currentStatus, ['pending', 'processing'], true)) {
        mysqli_commit($conn);
        return [
          'status' => 'invalid_state',
          'message' => 'Only pending or processing batches can be completed.',
          'batch' => $batch,
        ];
      }

      $transferProvider = strtolower(trim((string) ($batch['transfer_provider'] ?? 'paystack')));
      if ($transferProvider !== 'paystack') {
        mysqli_rollback($conn);
        return [
          'status' => 'unsupported_provider',
          'message' => 'This manual completion flow requires Paystack as the settlement transfer provider.',
        ];
      }

      $providerReferenceSafe = mysqli_real_escape_string($conn, $providerReference);
      $duplicateSql = "SELECT id
                       FROM settlement_batches
                       WHERE provider_reference = '$providerReferenceSafe'
                         AND id <> $batchId
                         AND provider_reference IS NOT NULL
                         AND provider_reference <> ''
                       LIMIT 1 FOR UPDATE";
      $duplicateRs = mysqli_query($conn, $duplicateSql);
      if ($duplicateRs && mysqli_num_rows($duplicateRs) > 0) {
        mysqli_rollback($conn);
        return [
          'status' => 'duplicate_provider_reference',
          'message' => 'That transfer reference is already attached to another settlement batch.',
        ];
      }

      $itemsSql = "SELECT sbi.id AS item_id,
                          sbi.source_ref_id,
                          sbi.allocated_amount,
                          sbi.status AS item_status,
                          spl.id AS ledger_id,
                          spl.payable_amount,
                          spl.settled_amount,
                          spl.status AS ledger_status
                   FROM settlement_batch_items sbi
                   INNER JOIN school_payable_ledger spl ON spl.id = sbi.school_payable_ledger_id
                   WHERE sbi.settlement_batch_id = $batchId
                   ORDER BY sbi.id ASC
                   FOR UPDATE";
      $itemsRs = mysqli_query($conn, $itemsSql);
      if (!$itemsRs) {
        throw new RuntimeException('Failed to load settlement batch items: ' . mysqli_error($conn));
      }

      $items = [];
      $mismatches = [];
      $appliedTotal = 0;
      while ($item = mysqli_fetch_assoc($itemsRs)) {
        $allocatedAmount = (int) ($item['allocated_amount'] ?? 0);
        $payableAmount = (int) ($item['payable_amount'] ?? 0);
        $settledAmount = (int) ($item['settled_amount'] ?? 0);
        $currentOutstanding = max(0, $payableAmount - $settledAmount);

        if ($currentOutstanding < $allocatedAmount) {
          $mismatches[] = [
            'item_id' => (int) ($item['item_id'] ?? 0),
            'ledger_id' => (int) ($item['ledger_id'] ?? 0),
            'source_ref_id' => (string) ($item['source_ref_id'] ?? ''),
            'allocated_amount' => $allocatedAmount,
            'current_outstanding' => $currentOutstanding,
          ];
        }

        $items[] = $item;
        $appliedTotal += $allocatedAmount;
      }

      if (empty($items)) {
        mysqli_rollback($conn);
        return [
          'status' => 'empty_batch',
          'message' => 'This settlement batch has no items to complete.',
        ];
      }

      $paystackSummary = is_array($paystackLookup['summary'] ?? null) ? $paystackLookup['summary'] : [];
      $paystackTransferStatus = strtolower(trim((string) ($paystackSummary['status'] ?? '')));
      if (!in_array($paystackTransferStatus, ['success', 'successful'], true)) {
        mysqli_rollback($conn);
        return [
          'status' => 'paystack_transfer_not_successful',
          'message' => 'Paystack found the transfer reference, but the transfer is not marked successful yet.',
          'lookup' => $paystackLookup,
        ];
      }

      $transferAmountKobo = (int) ($paystackSummary['amount_kobo'] ?? 0);
      $expectedAmountKobo = $appliedTotal * 100;
      if ($transferAmountKobo !== $expectedAmountKobo) {
        mysqli_rollback($conn);
        return [
          'status' => 'paystack_amount_mismatch',
          'message' => 'Paystack verified the transfer, but its amount does not match the staged settlement total.',
          'lookup' => $paystackLookup,
          'expected_amount_kobo' => $expectedAmountKobo,
          'received_amount_kobo' => $transferAmountKobo,
        ];
      }

      if (!empty($mismatches)) {
        mysqli_rollback($conn);
        return [
          'status' => 'outstanding_mismatch',
          'message' => 'One or more staged rows now have lower outstanding amounts than their staged allocation. Review refunds or ledger adjustments before completing this batch.',
          'mismatches' => $mismatches,
          'batch' => ccSchoolSettlementGetBatchDetails($conn, $batchId),
        ];
      }

      $schoolId = (int) ($batch['school_id'] ?? 0);
      $walletSql = "SELECT * FROM school_internal_wallets WHERE school_id = $schoolId LIMIT 1 FOR UPDATE";
      $walletRs = mysqli_query($conn, $walletSql);
      if (!$walletRs || mysqli_num_rows($walletRs) < 1) {
        mysqli_rollback($conn);
        return [
          'status' => 'no_wallet',
          'message' => 'School wallet is missing for this settlement batch.',
        ];
      }

      $wallet = mysqli_fetch_assoc($walletRs);
      $currentBalance = (int) ($wallet['current_balance'] ?? 0);
      $pendingBalance = (int) ($wallet['pending_payout_balance'] ?? 0);
      if ($currentBalance < $appliedTotal || $pendingBalance < $appliedTotal) {
        mysqli_rollback($conn);
        return [
          'status' => 'wallet_balance_mismatch',
          'message' => 'School wallet balances are lower than the staged settlement amount. Refresh the ledger before completing this batch.',
          'wallet' => $wallet,
          'required_amount' => $appliedTotal,
        ];
      }

      foreach ($items as $item) {
        $ledgerId = (int) ($item['ledger_id'] ?? 0);
        $itemId = (int) ($item['item_id'] ?? 0);
        $allocatedAmount = (int) ($item['allocated_amount'] ?? 0);
        $payableAmount = (int) ($item['payable_amount'] ?? 0);
        $settledAmount = (int) ($item['settled_amount'] ?? 0);
        $newSettledAmount = $settledAmount + $allocatedAmount;
        $newStatus = $newSettledAmount >= $payableAmount ? 'settled' : 'partially_settled';

        $updateLedgerSql = "UPDATE school_payable_ledger
                            SET settled_amount = $newSettledAmount,
                                status = '$newStatus',
                                updated_at = NOW()
                            WHERE id = $ledgerId";
        if (!mysqli_query($conn, $updateLedgerSql)) {
          throw new RuntimeException('Failed to update school payable ledger row: ' . mysqli_error($conn));
        }

        $itemNote = mysqli_real_escape_string(
          $conn,
          sprintf('Completed manually in cc_dashboard with provider reference %s.', $providerReference)
        );
        $updateItemSql = "UPDATE settlement_batch_items
                          SET status = 'settled',
                              notes = '$itemNote',
                              updated_at = NOW()
                          WHERE id = $itemId";
        if (!mysqli_query($conn, $updateItemSql)) {
          throw new RuntimeException('Failed to update settlement batch item: ' . mysqli_error($conn));
        }
      }

      $newCurrentBalance = max(0, $currentBalance - $appliedTotal);
      $newPendingBalance = max(0, $pendingBalance - $appliedTotal);
      $updateWalletSql = "UPDATE school_internal_wallets
                          SET current_balance = $newCurrentBalance,
                              pending_payout_balance = $newPendingBalance,
                              updated_at = NOW()
                          WHERE school_id = $schoolId";
      if (!mysqli_query($conn, $updateWalletSql)) {
        throw new RuntimeException('Failed to update school wallet after settlement completion: ' . mysqli_error($conn));
      }

      $providerResponse = ccSchoolSettlementDecodeJson($batch['provider_response'] ?? '');
      $providerResponse['source'] = 'cc_dashboard';
      $providerResponse['workflow'] = 'manual_school_settlement';
      $providerResponse['transfer_provider'] = 'paystack';
      $providerResponse['completed'] = [
        'admin_id' => $adminId,
        'admin_role' => $adminRole,
        'provider_reference' => $providerReference,
        'completed_at' => date('c'),
        'notes' => $notes,
        'paystack_lookup' => [
          'lookup_type' => (string) ($paystackLookup['lookup_type'] ?? ''),
          'summary' => $paystackSummary,
        ],
      ];

      $completionNote = sprintf(
        'Completed manually from cc_dashboard by admin #%d (role %d). Provider reference: %s.',
        $adminId,
        $adminRole,
        $providerReference
      );
      if ($notes !== '') {
        $completionNote = ccSchoolSettlementAppendNotes($completionNote, $notes);
      }
      $mergedNotes = ccSchoolSettlementAppendNotes((string) ($batch['notes'] ?? ''), $completionNote);
      $providerResponseSafe = ccSchoolSettlementEncodeJson($conn, $providerResponse);
      $mergedNotesSafe = mysqli_real_escape_string($conn, $mergedNotes);
      $updateBatchSql = "UPDATE settlement_batches
                         SET status = 'completed',
                             provider_reference = '$providerReferenceSafe',
                             provider_response = '$providerResponseSafe',
                             total_amount = $appliedTotal,
                             started_at = IFNULL(started_at, NOW()),
                             completed_at = NOW(),
                             last_error = NULL,
                             notes = '$mergedNotesSafe',
                             updated_at = NOW()
                         WHERE id = $batchId";
      if (!mysqli_query($conn, $updateBatchSql)) {
        throw new RuntimeException('Failed to update settlement batch state: ' . mysqli_error($conn));
      }

      mysqli_commit($conn);

      return [
        'status' => 'success',
        'message' => 'Settlement batch completed successfully.',
        'batch' => ccSchoolSettlementGetBatchDetails($conn, $batchId),
      ];
    } catch (Throwable $error) {
      mysqli_rollback($conn);
      throw $error;
    }
  }
}

if (!function_exists('ccSchoolSettlementFailBatch')) {
  function ccSchoolSettlementFailBatch(mysqli $conn, int $batchId, int $adminId, int $adminRole, string $reason = ''): array
  {
    if (!ccSchoolSettlementTablesReady($conn)) {
      return [
        'status' => 'missing_tables',
        'message' => 'Settlement tables are not available in command center.',
      ];
    }

    $batchId = (int) $batchId;
    $adminId = (int) $adminId;
    $adminRole = (int) $adminRole;
    $reason = trim($reason);

    if ($batchId <= 0) {
      return [
        'status' => 'invalid_batch',
        'message' => 'Select a valid settlement batch.',
      ];
    }

    if ($reason === '') {
      $reason = 'Settlement batch was cancelled before payout confirmation.';
    }

    mysqli_begin_transaction($conn);
    try {
      $batchSql = "SELECT * FROM settlement_batches WHERE id = $batchId LIMIT 1 FOR UPDATE";
      $batchRs = mysqli_query($conn, $batchSql);
      if (!$batchRs || mysqli_num_rows($batchRs) < 1) {
        mysqli_rollback($conn);
        return [
          'status' => 'missing_batch',
          'message' => 'Settlement batch not found.',
        ];
      }

      $batch = mysqli_fetch_assoc($batchRs);
      $currentStatus = (string) ($batch['status'] ?? '');
      if (!in_array($currentStatus, ['pending', 'processing'], true)) {
        mysqli_commit($conn);
        return [
          'status' => 'invalid_state',
          'message' => 'Only pending or processing batches can be failed or cancelled.',
          'batch' => $batch,
        ];
      }

      $providerResponse = ccSchoolSettlementDecodeJson($batch['provider_response'] ?? '');
      $providerResponse['source'] = 'cc_dashboard';
      $providerResponse['workflow'] = 'manual_school_settlement';
      $providerResponse['failed'] = [
        'admin_id' => $adminId,
        'admin_role' => $adminRole,
        'reason' => $reason,
        'failed_at' => date('c'),
      ];

      $failureNote = sprintf(
        'Marked failed from cc_dashboard by admin #%d (role %d). Reason: %s',
        $adminId,
        $adminRole,
        $reason
      );
      $mergedNotes = ccSchoolSettlementAppendNotes((string) ($batch['notes'] ?? ''), $failureNote);
      $reasonSafe = mysqli_real_escape_string($conn, $reason);
      $mergedNotesSafe = mysqli_real_escape_string($conn, $mergedNotes);
      $providerResponseSafe = ccSchoolSettlementEncodeJson($conn, $providerResponse);

      $updateBatchSql = "UPDATE settlement_batches
                         SET status = 'failed',
                             provider_response = '$providerResponseSafe',
                             last_error = '$reasonSafe',
                             failed_at = NOW(),
                             notes = '$mergedNotesSafe',
                             updated_at = NOW()
                         WHERE id = $batchId";
      if (!mysqli_query($conn, $updateBatchSql)) {
        throw new RuntimeException('Failed to update settlement batch failure state: ' . mysqli_error($conn));
      }

      $itemNoteSafe = mysqli_real_escape_string($conn, $reason);
      $updateItemsSql = "UPDATE settlement_batch_items
                         SET status = 'failed',
                             notes = '$itemNoteSafe',
                             updated_at = NOW()
                         WHERE settlement_batch_id = $batchId";
      if (!mysqli_query($conn, $updateItemsSql)) {
        throw new RuntimeException('Failed to update settlement batch items: ' . mysqli_error($conn));
      }

      mysqli_commit($conn);

      return [
        'status' => 'success',
        'message' => 'Settlement batch marked as failed and released for restaging.',
        'batch' => ccSchoolSettlementGetBatchDetails($conn, $batchId),
      ];
    } catch (Throwable $error) {
      mysqli_rollback($conn);
      throw $error;
    }
  }
}

if (!function_exists('ccSchoolSettlementParseNotifyEmails')) {
  /**
   * Splits a comma-separated notify_email string into a de-duplicated list
   * of valid, trimmed email addresses. Invalid entries are silently dropped.
   */
  function ccSchoolSettlementParseNotifyEmails(string $notifyEmail): array
  {
    $emails = [];
    foreach (explode(',', $notifyEmail) as $candidate) {
      $candidate = trim($candidate);
      if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
        $emails[] = $candidate;
      }
    }

    return array_values(array_unique($emails));
  }
}

if (!function_exists('ccSchoolSettlementGetConfig')) {
  function ccSchoolSettlementGetConfig(mysqli $conn): array
  {
    $defaultConfig = [
      'id' => 1,
      'is_auto_settlement_enabled' => 1,
      'min_settlement_amount' => 1000,
      'max_settlement_cap_per_school' => 5000000,
      'execution_time' => '02:00',
      'notify_email' => 'finance@nivasity.com',
      'automation_cutoff_at' => date('Y-m-d H:i:s'),
      'updated_by' => null,
      'updated_at' => date('Y-m-d H:i:s'),
    ];

    if (!ccSchoolSettlementTableExists($conn, 'school_settlement_configs')) {
      return $defaultConfig;
    }

    $query = mysqli_query($conn, "SELECT * FROM school_settlement_configs WHERE id = 1 LIMIT 1");
    if ($query && ($row = mysqli_fetch_assoc($query))) {
      return [
        'id' => (int) $row['id'],
        'is_auto_settlement_enabled' => (int) ($row['is_auto_settlement_enabled'] ?? 1),
        'min_settlement_amount' => (int) ($row['min_settlement_amount'] ?? 1000),
        'max_settlement_cap_per_school' => (int) ($row['max_settlement_cap_per_school'] ?? 5000000),
        'execution_time' => (string) ($row['execution_time'] ?? '02:00'),
        'notify_email' => (string) ($row['notify_email'] ?? 'finance@nivasity.com'),
        'automation_cutoff_at' => (string) ($row['automation_cutoff_at'] ?? date('Y-m-d H:i:s')),
        'updated_by' => $row['updated_by'] ? (int) $row['updated_by'] : null,
        'updated_at' => (string) ($row['updated_at'] ?? date('Y-m-d H:i:s')),
      ];
    }

    return $defaultConfig;
  }
}

if (!function_exists('ccSchoolSettlementUpdateConfig')) {
  function ccSchoolSettlementUpdateConfig(mysqli $conn, array $settings, int $adminId): array
  {
    if (!ccSchoolSettlementTableExists($conn, 'school_settlement_configs')) {
      throw new RuntimeException('school_settlement_configs table does not exist. Please run migration.');
    }

    $isEnabled = isset($settings['is_auto_settlement_enabled']) ? (int) $settings['is_auto_settlement_enabled'] : 1;
    $minAmount = max(0, (int) ($settings['min_settlement_amount'] ?? 1000));
    $maxCap = max(1000, (int) ($settings['max_settlement_cap_per_school'] ?? 5000000));
    $notifyEmailInput = trim((string) ($settings['notify_email'] ?? 'finance@nivasity.com'));
    $validNotifyEmails = ccSchoolSettlementParseNotifyEmails($notifyEmailInput);
    $notifyEmail = !empty($validNotifyEmails) ? implode(',', $validNotifyEmails) : 'finance@nivasity.com';
    $executionTime = trim((string) ($settings['execution_time'] ?? '02:00'));
    if (!preg_match('/^\d{2}:\d{2}$/', $executionTime)) {
      $executionTime = '02:00';
    }

    $notifyEmailSafe = mysqli_real_escape_string($conn, $notifyEmail);
    $executionTimeSafe = mysqli_real_escape_string($conn, $executionTime);

    $sql = "INSERT INTO school_settlement_configs (id, is_auto_settlement_enabled, min_settlement_amount, max_settlement_cap_per_school, execution_time, notify_email, updated_by, updated_at)
            VALUES (1, $isEnabled, $minAmount, $maxCap, '$executionTimeSafe', '$notifyEmailSafe', $adminId, NOW())
            ON DUPLICATE KEY UPDATE
              is_auto_settlement_enabled = VALUES(is_auto_settlement_enabled),
              min_settlement_amount = VALUES(min_settlement_amount),
              max_settlement_cap_per_school = VALUES(max_settlement_cap_per_school),
              execution_time = VALUES(execution_time),
              notify_email = VALUES(notify_email),
              updated_by = VALUES(updated_by),
              updated_at = NOW()";

    if (!mysqli_query($conn, $sql)) {
      throw new RuntimeException('Failed to update settlement configs: ' . mysqli_error($conn));
    }

    return [
      'status' => 'success',
      'message' => 'Settlement configuration updated successfully.',
      'config' => ccSchoolSettlementGetConfig($conn),
    ];
  }
}

if (!function_exists('ccSchoolSettlementListCronLogs')) {
  function ccSchoolSettlementListCronLogs(mysqli $conn, int $limit = 20): array
  {
    if (!ccSchoolSettlementTableExists($conn, 'settlement_cron_logs')) {
      return [];
    }

    $limit = max(1, min(100, $limit));
    $sql = "SELECT * FROM settlement_cron_logs ORDER BY id DESC LIMIT $limit";
    $query = mysqli_query($conn, $sql);
    $logs = [];

    if ($query) {
      while ($row = mysqli_fetch_assoc($query)) {
        $logs[] = [
          'id' => (int) $row['id'],
          'run_reference' => (string) $row['run_reference'],
          'started_at' => (string) $row['started_at'],
          'completed_at' => $row['completed_at'] ? (string) $row['completed_at'] : null,
          'status' => (string) $row['status'],
          'schools_count' => (int) $row['schools_count'],
          'total_amount_settled' => (int) $row['total_amount_settled'],
          'total_students_count' => (int) ($row['total_students_count'] ?? 0),
          'total_materials_count' => (int) ($row['total_materials_count'] ?? 0),
          'triggered_by' => (string) $row['triggered_by'],
          'created_at' => (string) $row['created_at'],
        ];
      }
    }

    return $logs;
  }
}

if (!function_exists('ccSchoolSettlementGetCronLogDetails')) {
  function ccSchoolSettlementGetCronLogDetails(mysqli $conn, int $logId): ?array
  {
    if (!ccSchoolSettlementTableExists($conn, 'settlement_cron_logs')) {
      return null;
    }

    $sql = "SELECT * FROM settlement_cron_logs WHERE id = $logId LIMIT 1";
    $query = mysqli_query($conn, $sql);
    if ($query && ($row = mysqli_fetch_assoc($query))) {
      $summary = ccSchoolSettlementDecodeJson($row['summary_json'] ?? '');
      return [
        'id' => (int) $row['id'],
        'run_reference' => (string) $row['run_reference'],
        'started_at' => (string) $row['started_at'],
        'completed_at' => $row['completed_at'] ? (string) $row['completed_at'] : null,
        'status' => (string) $row['status'],
        'schools_count' => (int) $row['schools_count'],
        'total_amount_settled' => (int) $row['total_amount_settled'],
        'total_students_count' => (int) ($row['total_students_count'] ?? 0),
        'total_materials_count' => (int) ($row['total_materials_count'] ?? 0),
        'triggered_by' => (string) $row['triggered_by'],
        'created_at' => (string) $row['created_at'],
        'summary' => $summary,
      ];
    }

    return null;
  }
}

if (!function_exists('ccSchoolSettlementGetBatchFacultyBreakdown')) {
  function ccSchoolSettlementGetBatchFacultyBreakdown(mysqli $conn, int $batchId): array
  {
    $sql = "SELECT 
              COALESCE(NULLIF(TRIM(f.faculty), ''), 'General / Department') AS faculty_name,
              COUNT(DISTINCT mb.buyer) AS unique_students,
              COUNT(mb.id) AS materials_count,
              COALESCE(SUM(sbi.allocated_amount), 0) AS faculty_amount
            FROM settlement_batch_items sbi
            JOIN school_payable_ledger spl ON spl.id = sbi.school_payable_ledger_id
            LEFT JOIN manuals_bought mb ON mb.ref_id = spl.source_ref_id
            LEFT JOIN manuals m ON m.id = mb.manual_id
            LEFT JOIN faculties f ON f.id = m.faculty
            WHERE sbi.settlement_batch_id = $batchId
            GROUP BY COALESCE(NULLIF(TRIM(f.faculty), ''), 'General / Department')
            ORDER BY faculty_amount DESC";

    $query = mysqli_query($conn, $sql);
    $faculties = [];

    if ($query) {
      while ($row = mysqli_fetch_assoc($query)) {
        $faculties[] = [
          'faculty_name' => (string) $row['faculty_name'],
          'unique_students' => (int) $row['unique_students'],
          'materials_count' => (int) $row['materials_count'],
          'faculty_amount' => (int) $row['faculty_amount'],
        ];
      }
    }

    return $faculties;
  }
}

if (!function_exists('ccSchoolSettlementHumanizeStatusLabel')) {
  /**
   * Turns a SCREAMING_SNAKE_CASE or lowercase status token into readable
   * Title Case for display (e.g. "partial_failure" -> "Partial Failure").
   */
  function ccSchoolSettlementHumanizeStatusLabel(string $value): string
  {
    $spaced = str_replace(['_', '-'], ' ', trim($value));
    return $spaced === '' ? '' : ucwords(strtolower($spaced));
  }
}

if (!function_exists('ccSchoolSettlementBuildSummaryEmailHtml')) {
  function ccSchoolSettlementBuildSummaryEmailHtml(array $runResult): string
  {
    $runRef = htmlspecialchars($runResult['run_reference'] ?? 'RUN');
    $statusRaw = strtoupper((string) ($runResult['status'] ?? 'SUCCESS'));
    $status = ccSchoolSettlementHumanizeStatusLabel($statusRaw);
    $totalAmount = number_format((float) ($runResult['total_amount_settled'] ?? 0), 2);
    $totalStaged = 0;
    foreach (($runResult['schools'] ?? []) as $__schoolForTotal) {
      $totalStaged += (int) ($__schoolForTotal['amount_staged'] ?? ($__schoolForTotal['amount_settled'] ?? 0));
    }
    $totalStagedFormatted = number_format((float) $totalStaged, 2);
    $totalStudents = number_format((int) ($runResult['total_students_count'] ?? 0));
    $totalMaterials = number_format((int) ($runResult['total_materials_count'] ?? 0));
    $schoolsCount = (int) ($runResult['schools_count'] ?? 0);
    $triggeredBy = htmlspecialchars($runResult['triggered_by'] ?? 'CRON_MIDNIGHT');
    $dateStr = date('l, d F Y - h:i A');

    $statusColor = $statusRaw === 'SUCCESS' ? '#10b981' : ($statusRaw === 'PAUSED' ? '#f59e0b' : '#ef4444');

    $html = '
    <div style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 25px; color: #1e293b;">
      <div style="max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        
        <div style="background: #1e1b4b; padding: 24px; color: #ffffff;">
          <h2 style="margin: 0 0 6px 0; font-size: 20px; font-weight: 700; color: #ffffff;">Nivasity Daily Settlement Report</h2>
          <p style="margin: 0; font-size: 13px; color: #cbd5e1;">Execution Reference: <strong>' . $runRef . '</strong> | ' . $dateStr . '</p>
        </div>

        <div style="padding: 20px 24px;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px; border-collapse: separate; border-spacing: 8px 8px;">
            <tr>
              <td width="50%" style="background: #f1f5f9; padding: 14px; border-radius: 8px; vertical-align: top;">
                <span style="display: block; font-size: 11px; color: #64748b; font-weight: 600;">Status</span>
                <strong style="font-size: 16px; color: ' . $statusColor . ';">' . $status . '</strong>
              </td>
              <td width="50%" style="background: #f1f5f9; padding: 14px; border-radius: 8px; vertical-align: top;">
                <span style="display: block; font-size: 11px; color: #64748b; font-weight: 600;">Confirmed Settled</span>
                <strong style="font-size: 16px; color: #0f172a;">&#8358;' . $totalAmount . '</strong>
              </td>
            </tr>
            <tr>
              <td width="50%" style="background: #f1f5f9; padding: 14px; border-radius: 8px; vertical-align: top;">
                <span style="display: block; font-size: 11px; color: #64748b; font-weight: 600;">Total Staged / Dispatched</span>
                <strong style="font-size: 16px; color: #0f172a;">&#8358;' . $totalStagedFormatted . '</strong>
              </td>
              <td width="50%" style="background: #f1f5f9; padding: 14px; border-radius: 8px; vertical-align: top;">
                <span style="display: block; font-size: 11px; color: #64748b; font-weight: 600;">Unique Students</span>
                <strong style="font-size: 16px; color: #0f172a;">' . $totalStudents . '</strong>
              </td>
            </tr>
            <tr>
              <td width="50%" style="background: #f1f5f9; padding: 14px; border-radius: 8px; vertical-align: top;">
                <span style="display: block; font-size: 11px; color: #64748b; font-weight: 600;">Materials Paid</span>
                <strong style="font-size: 16px; color: #0f172a;">' . $totalMaterials . '</strong>
              </td>
              <td width="50%"></td>
            </tr>
          </table>';

    if (!empty($runResult['schools'])) {
      $html .= '<h3 style="font-size: 16px; color: #0f172a; margin-top: 25px; margin-bottom: 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">School & Faculty Breakdown</h3>';

      foreach ($runResult['schools'] as $schoolData) {
        $schoolName = htmlspecialchars($schoolData['school_name'] ?? 'School');
        $amountSettled = (int) ($schoolData['amount_settled'] ?? 0);
        $amountStaged = (int) ($schoolData['amount_staged'] ?? $amountSettled);
        $isConfirmed = $amountSettled > 0;
        $displayAmount = number_format((float) ($isConfirmed ? $amountSettled : $amountStaged), 2);
        $statusLabel = $isConfirmed ? 'Confirmed' : 'Awaiting Paystack Confirmation';
        $statusColor = $isConfirmed ? '#10b981' : '#f59e0b';
        $schoolStudents = number_format((int) ($schoolData['unique_students'] ?? 0));
        $schoolMaterials = number_format((int) ($schoolData['materials_count'] ?? 0));
        $batchRef = htmlspecialchars($schoolData['batch_reference'] ?? '');

        $html .= '
        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 18px; overflow: hidden;">
          <div style="background: #f8fafc; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
              <strong style="font-size: 15px; color: #1e293b;">' . $schoolName . '</strong>
              <div style="font-size: 12px; color: #64748b;">Batch: ' . $batchRef . ' &middot; <span style="color: ' . $statusColor . '; font-weight: 700;">' . $statusLabel . '</span></div>
            </div>
            <div style="text-align: right;">
              <span style="font-size: 15px; font-weight: 700; color: ' . $statusColor . ';">&#8358;' . $displayAmount . '</span>
              <div style="font-size: 12px; color: #64748b;">' . $schoolStudents . ' Students | ' . $schoolMaterials . ' Materials</div>
            </div>
          </div>';

        if (!empty($schoolData['faculties'])) {
          $html .= '
          <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
              <tr style="background: #f1f5f9; text-align: left; color: #475569;">
                <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">Faculty</th>
                <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;">Students</th>
                <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;">Materials</th>
                <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;">Amount</th>
              </tr>
            </thead>
            <tbody>';

          foreach ($schoolData['faculties'] as $fac) {
            $fName = htmlspecialchars($fac['faculty_name'] ?? 'General');
            $fStudents = number_format((int) ($fac['unique_students'] ?? 0));
            $fMats = number_format((int) ($fac['materials_count'] ?? 0));
            $fAmt = number_format((float) ($fac['faculty_amount'] ?? 0), 2);

            $html .= '
              <tr>
                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #334155;">' . $fName . '</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #64748b;">' . $fStudents . '</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #64748b;">' . $fMats . '</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 600; color: #0f172a;">&#8358;' . $fAmt . '</td>
              </tr>';
          }

          $html .= '
            </tbody>
          </table>';
        }

        $html .= '</div>';
      }
    }

    if (!empty($runResult['skipped_schools'])) {
      $html .= '<h4 style="font-size: 14px; color: #64748b; margin-top: 20px; margin-bottom: 8px;">Skipped Schools / Below Threshold</h4><ul style="font-size: 12px; color: #64748b; margin: 0; padding-left: 20px;">';
      foreach ($runResult['skipped_schools'] as $skipped) {
        $html .= '<li>' . htmlspecialchars($skipped['school_name'] ?? '') . ': ' . htmlspecialchars($skipped['reason'] ?? '') . ' (Pending: &#8358;' . number_format((float) ($skipped['pending_balance'] ?? 0), 2) . ')</li>';
      }
      $html .= '</ul>';
    }

    $html .= '
          <div style="margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 15px;">
            This is an automated notification from the Nivasity Command Center.
          </div>
        </div>
      </div>
    </div>';

    return $html;
  }
}

if (!function_exists('ccSchoolSettlementExecuteMidnightRun')) {
  function ccSchoolSettlementExecuteMidnightRun(
    mysqli $conn,
    string $triggeredBy = 'CRON_MIDNIGHT',
    int $adminId = 0
  ): array {
    $startedAt = date('Y-m-d H:i:s');
    $scheduledFor = date('Y-m-d');
    $runRef = sprintf('run_%s_%s', date('Ymd_His'), substr(md5(uniqid('', true)), 0, 6));

    // Automation only ever considers ledger rows from the automation cutoff (set when this
    // feature went live) through the end of yesterday. Today's still-in-progress transactions
    // and anything predating the cutoff are excluded and must be settled manually. Rows that
    // roll over across nights (held back by the min/max settlement limits) keep their original
    // created_at, so they remain eligible on every subsequent run until fully settled.
    $ledgerWindowEnd = date('Y-m-d 23:59:59', strtotime('yesterday'));

    $config = ccSchoolSettlementGetConfig($conn);
    $ledgerWindowStart = (string) ($config['automation_cutoff_at'] ?? '');

    if (empty($config['is_auto_settlement_enabled']) && $triggeredBy === 'CRON_MIDNIGHT') {
      $logSql = "INSERT INTO settlement_cron_logs (run_reference, started_at, completed_at, status, schools_count, total_amount_settled, summary_json, triggered_by, created_at)
                 VALUES ('$runRef', '$startedAt', NOW(), 'paused', 0, 0, '{\"message\":\"Automated settlements PAUSED by configuration\"}', '$triggeredBy', NOW())";
      if (ccSchoolSettlementTableExists($conn, 'settlement_cron_logs')) {
        mysqli_query($conn, $logSql);
      }
      return [
        'status' => 'paused',
        'run_reference' => $runRef,
        'message' => 'Automated settlement is currently PAUSED.',
      ];
    }

    $minThreshold = (int) ($config['min_settlement_amount'] ?? 1000);
    $maxCap = (int) ($config['max_settlement_cap_per_school'] ?? 5000000);
    $notifyEmail = (string) ($config['notify_email'] ?? 'finance@nivasity.com');

    // Reconcile any batches left 'processing' from a previous run (Paystack
    // transfer dispatched but not yet confirmed) before staging new work, so
    // slow-to-confirm transfers get finalized and their wallet/ledger state
    // is settled before tonight's numbers are computed.
    $reconciliation = ccSchoolSettlementReconcileProcessingBatches($conn, $adminId);

    $schoolsQuery = mysqli_query($conn, "SELECT id, name AS school_name FROM schools ORDER BY id ASC");
    $allSchools = [];
    if ($schoolsQuery) {
      while ($s = mysqli_fetch_assoc($schoolsQuery)) {
        $allSchools[] = $s;
      }
    }

    $processedSchools = [];
    $skippedSchools = [];
    $errors = [];
    $totalSettledAmount = 0;
    $totalStudentsCount = 0;
    $totalMaterialsCount = 0;

    foreach ($allSchools as $school) {
      $schoolId = (int) $school['id'];
      $schoolName = (string) $school['school_name'];

      try {
        $snapshot = ccSchoolSettlementGetSnapshot($conn, $schoolId);
        if (($snapshot['status'] ?? '') !== 'success') {
          continue;
        }

        $walletBalance = (int) ($snapshot['wallet']['pending_payout_balance'] ?? 0);
        if ($walletBalance <= 0) {
          continue;
        }

        $windowedTarget = min($walletBalance, $maxCap);
        $windowedAllocations = ccSchoolSettlementBuildAllocations($conn, $schoolId, $windowedTarget, false, $ledgerWindowStart, $ledgerWindowEnd);
        $pendingAmount = (int) ($windowedAllocations['total_amount'] ?? 0);

        if ($pendingAmount <= 0) {
          continue;
        }

        if ($pendingAmount < $minThreshold) {
          $skippedSchools[] = [
            'school_id' => $schoolId,
            'school_name' => $schoolName,
            'pending_balance' => $pendingAmount,
            'reason' => "Pending balance (N$pendingAmount) is below minimum threshold (N$minThreshold)",
          ];
          continue;
        }

        $activeBatch = ccSchoolSettlementGetActiveBatch($conn, $schoolId);
        if ($activeBatch) {
          $skippedSchools[] = [
            'school_id' => $schoolId,
            'school_name' => $schoolName,
            'pending_balance' => $pendingAmount,
            'reason' => "School has an active staged batch #{$activeBatch['id']} ({$activeBatch['batch_reference']}) in progress.",
          ];
          continue;
        }

        $notes = sprintf('Automated midnight settlement run: %s', $runRef);
        $stageResult = ccSchoolSettlementStageBatch(
          $conn,
          $schoolId,
          $scheduledFor,
          $adminId,
          1,
          $notes,
          $ledgerWindowStart,
          $ledgerWindowEnd
        );

        $batch = $stageResult['batch'] ?? null;
        if (!$batch || empty($batch['id'])) {
          throw new RuntimeException('Failed to stage settlement batch for school: ' . $schoolName);
        }

        $batchId = (int) $batch['id'];
        $batchRef = (string) ($batch['batch_reference'] ?? '');
        $batchAmount = (int) ($batch['total_amount'] ?? 0);

        $facultyBreakdown = ccSchoolSettlementGetBatchFacultyBreakdown($conn, $batchId);

        $schoolUniqueStudents = 0;
        $schoolMaterials = 0;
        foreach ($facultyBreakdown as $fb) {
          $schoolUniqueStudents += $fb['unique_students'];
          $schoolMaterials += $fb['materials_count'];
        }

        $totalStudentsCount += $schoolUniqueStudents;
        $totalMaterialsCount += $schoolMaterials;

        // Send the staged batch to Paystack right away. This only moves it
        // to 'processing' with a provider reference attached — it does NOT
        // deduct the wallet or mark anything settled. Confirmation happens
        // on this run's reconciliation pass (or the next run's, if Paystack
        // hasn't confirmed yet by the time this run finishes).
        $dispatch = ccSchoolSettlementDispatchBatchTransfer($conn, $batchId);
        $dispatchStatus = (string) ($dispatch['status'] ?? 'unknown');

        $processedSchools[] = [
          'school_id' => $schoolId,
          'school_name' => $schoolName,
          'batch_id' => $batchId,
          'batch_reference' => $batchRef,
          'amount_settled' => 0, // set once reconciliation confirms the transfer, not at staging/dispatch time
          'amount_staged' => $batchAmount,
          'dispatch_status' => $dispatchStatus,
          'dispatch_message' => (string) ($dispatch['message'] ?? ''),
          'unique_students' => $schoolUniqueStudents,
          'materials_count' => $schoolMaterials,
          'faculties' => $facultyBreakdown,
        ];

        if ($dispatchStatus !== 'success') {
          $errors[] = [
            'school_id' => $schoolId,
            'school_name' => $schoolName,
            'error' => 'Batch staged but Paystack dispatch failed: ' . (string) ($dispatch['message'] ?? 'Unknown error'),
          ];
        }
      } catch (Throwable $e) {
        $errors[] = [
          'school_id' => $schoolId,
          'school_name' => $schoolName,
          'error' => $e->getMessage(),
        ];
      }
    }

    // Give transfers dispatched moments ago a brief chance to confirm before
    // this run's report is built, so same-run completions are reflected
    // accurately instead of always deferring to the next run.
    $postDispatchReconciliation = ccSchoolSettlementReconcileProcessingBatches($conn, $adminId);

    if ($postDispatchReconciliation['completed'] > 0) {
      foreach ($processedSchools as &$processedSchool) {
        $refreshedBatch = ccSchoolSettlementGetBatchDetails($conn, (int) ($processedSchool['batch_id'] ?? 0));
        if ($refreshedBatch && (string) ($refreshedBatch['status'] ?? '') === 'completed') {
          $processedSchool['amount_settled'] = (int) ($refreshedBatch['total_amount'] ?? $processedSchool['amount_staged']);
        }
      }
      unset($processedSchool);
    }

    foreach ($processedSchools as $processedSchool) {
      $totalSettledAmount += (int) ($processedSchool['amount_settled'] ?? 0);
    }

    $finalStatus = empty($errors) ? 'success' : (!empty($processedSchools) ? 'partial_failure' : 'failed');
    $completedAt = date('Y-m-d H:i:s');

    $runPayload = [
      'run_reference' => $runRef,
      'started_at' => $startedAt,
      'completed_at' => $completedAt,
      'status' => $finalStatus,
      'schools_count' => count($processedSchools),
      'total_amount_settled' => $totalSettledAmount,
      'total_students_count' => $totalStudentsCount,
      'total_materials_count' => $totalMaterialsCount,
      'triggered_by' => $triggeredBy,
      'schools' => $processedSchools,
      'skipped_schools' => $skippedSchools,
      'errors' => $errors,
      'reconciliation' => [
        'before_run' => $reconciliation,
        'after_dispatch' => $postDispatchReconciliation,
      ],
    ];

    if (ccSchoolSettlementTableExists($conn, 'settlement_cron_logs')) {
      $summaryJsonSafe = ccSchoolSettlementEncodeJson($conn, $runPayload);
      $logSql = "INSERT INTO settlement_cron_logs (
                   run_reference, started_at, completed_at, status, schools_count, 
                   total_amount_settled, total_students_count, total_materials_count, 
                   summary_json, triggered_by, created_at
                 ) VALUES (
                   '$runRef', '$startedAt', '$completedAt', '$finalStatus', " . count($processedSchools) . ", 
                   $totalSettledAmount, $totalStudentsCount, $totalMaterialsCount, 
                   '$summaryJsonSafe', '$triggeredBy', NOW()
                 )";
      mysqli_query($conn, $logSql);
    }

    $notifyEmailRecipients = ccSchoolSettlementParseNotifyEmails($notifyEmail);
    if (!empty($notifyEmailRecipients) && file_exists(__DIR__ . '/mail.php')) {
      require_once(__DIR__ . '/mail.php');
      if (function_exists('sendMailBatch')) {
        $emailSubject = sprintf('Daily Settlement Report - %s [N%s]', date('d M Y'), number_format($totalSettledAmount));
        $emailHtml = ccSchoolSettlementBuildSummaryEmailHtml($runPayload);
        try {
          sendMailBatch($emailSubject, $emailHtml, $notifyEmailRecipients);
        } catch (Throwable $mailErr) {
          error_log('Settlement email notification failed: ' . $mailErr->getMessage());
        }
      }
    }

    return $runPayload;
  }
}