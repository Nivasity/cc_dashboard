<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/transactions_helpers.php');

function ccWalletTransactionsTableExists($conn, $tableName) {
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

function ccWalletTransactionsFormatAmount($amount) {
  return '&#8358;' . number_format((float) $amount, 2);
}

function ccWalletTransactionsFormatDateTime($value) {
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

function ccWalletTransactionsDirectionForEntryType($entryType) {
  $entryType = strtolower(trim((string) $entryType));

  if (in_array($entryType, ['credit', 'refund'], true)) {
    return 'credit';
  }

  if (in_array($entryType, ['debit', 'fee'], true)) {
    return 'debit';
  }

  return 'neutral';
}

function ccWalletTransactionsNormalizeDate($value) {
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

function ccWalletTransactionsGetFilters(array $source) {
  $allowedDirections = ['all', 'credit', 'debit'];
  $direction = strtolower(trim((string) ($source['direction'] ?? 'all')));
  if (!in_array($direction, $allowedDirections, true)) {
    $direction = 'all';
  }

  $allowedDateRanges = ['today', 'yesterday', '7', '30', '90', 'all', 'custom'];
  $dateRange = trim((string) ($source['date_range'] ?? 'today'));
  if (!in_array($dateRange, $allowedDateRanges, true)) {
    $dateRange = 'today';
  }

  $startDate = ccWalletTransactionsNormalizeDate($source['start_date'] ?? '');
  $endDate = ccWalletTransactionsNormalizeDate($source['end_date'] ?? '');

  if ($dateRange !== 'custom') {
    $startDate = '';
    $endDate = '';
  }

  return [
    'direction' => $direction,
    'date_range' => $dateRange,
    'start_date' => $startDate,
    'end_date' => $endDate,
  ];
}

function ccWalletTransactionsFetchData($conn, array $filters) {
  $requiredTables = ['users', 'schools', 'depts', 'faculties', 'user_wallets', 'wallet_ledger_entries'];
  $missingTables = [];

  foreach ($requiredTables as $requiredTable) {
    if (!ccWalletTransactionsTableExists($conn, $requiredTable)) {
      $missingTables[] = $requiredTable;
    }
  }

  $walletTablesReady = empty($missingTables);
  $summary = [
    'entries_count' => 0,
    'credit_total' => 0,
    'debit_total' => 0,
  ];
  $transactions = [];
  $tableNotice = '';

  if ($walletTablesReady) {
    $directionSql = '';
    if (($filters['direction'] ?? 'all') === 'credit') {
      $directionSql = " AND l.entry_type IN ('credit', 'refund')";
    } elseif (($filters['direction'] ?? 'all') === 'debit') {
      $directionSql = " AND l.entry_type IN ('debit', 'fee')";
    }

    $dateSql = buildDateFilter(
      $conn,
      (string) ($filters['date_range'] ?? 'today'),
      (string) ($filters['start_date'] ?? ''),
      (string) ($filters['end_date'] ?? ''),
      'l'
    );

    $summarySql = "SELECT COUNT(*) AS entries_count,
                          COALESCE(SUM(CASE WHEN l.entry_type IN ('credit', 'refund') THEN l.amount ELSE 0 END), 0) AS credit_total,
                          COALESCE(SUM(CASE WHEN l.entry_type IN ('debit', 'fee') THEN l.amount ELSE 0 END), 0) AS debit_total
                   FROM wallet_ledger_entries l
                   INNER JOIN user_wallets w ON w.id = l.wallet_id
                   INNER JOIN users u ON u.id = w.user_id
                   WHERE 1 = 1" . $directionSql . $dateSql;
    $summaryResult = mysqli_query($conn, $summarySql);
    if ($summaryResult) {
      $summaryRow = mysqli_fetch_assoc($summaryResult);
      if ($summaryRow) {
        $summary['entries_count'] = (int) ($summaryRow['entries_count'] ?? 0);
        $summary['credit_total'] = (float) ($summaryRow['credit_total'] ?? 0);
        $summary['debit_total'] = (float) ($summaryRow['debit_total'] ?? 0);
      }
    }

    $transactionsSql = "SELECT l.id, l.entry_type, l.amount, l.balance_after,
                               l.reference, l.provider_reference, l.created_at,
                               u.first_name, u.last_name, u.email, u.matric_no,
                               s.code AS school_code, d.name AS dept_name, f.name AS faculty_name
                        FROM wallet_ledger_entries l
                        INNER JOIN user_wallets w ON w.id = l.wallet_id
                        INNER JOIN users u ON u.id = w.user_id
                        LEFT JOIN schools s ON s.id = u.school
                        LEFT JOIN depts d ON d.id = u.dept
                        LEFT JOIN faculties f ON f.id = d.faculty_id
                        WHERE 1 = 1" . $directionSql . $dateSql . "
                        ORDER BY l.created_at DESC, l.id DESC
                        LIMIT 500";
    $transactionsResult = mysqli_query($conn, $transactionsSql);
    if ($transactionsResult) {
      while ($row = mysqli_fetch_assoc($transactionsResult)) {
        $entryType = strtolower((string) ($row['entry_type'] ?? 'adjustment'));
        $direction = ccWalletTransactionsDirectionForEntryType($entryType);
        $referenceDisplay = trim((string) ($row['provider_reference'] ?? ''));
        if ($referenceDisplay === '') {
          $referenceDisplay = trim((string) ($row['reference'] ?? ''));
        }
        if ($referenceDisplay === '') {
          $referenceDisplay = '-';
        }

        $transactions[] = [
          'id' => (int) ($row['id'] ?? 0),
          'student_name' => trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))),
          'email' => (string) ($row['email'] ?? ''),
          'matric_no' => (string) ($row['matric_no'] ?? ''),
          'school_code' => (string) ($row['school_code'] ?? ''),
          'faculty_name' => (string) ($row['faculty_name'] ?? ''),
          'dept_name' => (string) ($row['dept_name'] ?? ''),
          'direction' => $direction,
          'direction_label' => ucfirst($direction),
          'amount' => (float) ($row['amount'] ?? 0),
          'amount_sign' => $direction === 'credit' ? '+' : ($direction === 'debit' ? '-' : ''),
          'balance_after' => (float) ($row['balance_after'] ?? 0),
          'reference_display' => $referenceDisplay,
          'created_at' => (string) ($row['created_at'] ?? ''),
          'created_at_display' => ccWalletTransactionsFormatDateTime((string) ($row['created_at'] ?? '')),
        ];
      }
    }

    if ($summary['entries_count'] > count($transactions)) {
      $tableNotice = 'Showing the most recent 500 ledger entries for the selected filters.';
    }
  }

  return [
    'wallet_tables_ready' => $walletTablesReady,
    'missing_tables' => $missingTables,
    'summary' => $summary,
    'transactions' => $transactions,
    'table_notice' => $tableNotice,
    'has_transaction_rows' => $walletTablesReady && $transactions !== [],
    'filters' => $filters,
    'message' => $walletTablesReady ? '' : 'Wallet transaction tables are not available in this environment yet.',
  ];
}