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
  function ccSchoolSettlementCapPerSchool(): int
  {
    return 8000000;
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

if (!function_exists('ccSchoolSettlementBuildEligibleLedgerSql')) {
  function ccSchoolSettlementBuildEligibleLedgerSql(int $schoolId, bool $forUpdate = false): string
  {
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
  function ccSchoolSettlementBuildAllocations(mysqli $conn, int $schoolId, int $targetAmount, bool $forUpdate = false): array
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

    $ledgerRs = mysqli_query($conn, ccSchoolSettlementBuildEligibleLedgerSql($schoolId, $forUpdate));
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
    $previewTarget = min($pendingBalance, ccSchoolSettlementCapPerSchool(), $stageableAmount);

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
        'cap_per_school' => ccSchoolSettlementCapPerSchool(),
        'has_active_batch' => $activeBatchDetails ? 1 : 0,
      ],
      'active_batch' => $activeBatchDetails,
      'preview' => $preview,
      'recent_batches' => ccSchoolSettlementListRecentBatches($conn, $schoolId, 12),
    ];
  }
}

if (!function_exists('ccSchoolSettlementStageBatch')) {
  function ccSchoolSettlementStageBatch(mysqli $conn, int $schoolId, string $scheduledFor, int $adminId, int $adminRole, string $notes = ''): array
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

      $allocationTarget = min($pendingBalance, ccSchoolSettlementCapPerSchool());
      $allocations = ccSchoolSettlementBuildAllocations($conn, $schoolId, $allocationTarget, true);
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