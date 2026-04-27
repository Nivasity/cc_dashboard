<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once(__DIR__ . '/model/transactions_helpers.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRoles = [1, 2, 3, 4];

if (!$finance_mgt_menu || !in_array($admin_role, $allowedRoles, true)) {
  header('Location: /');
  exit();
}

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

function ccWalletTransactionsBadgeClass($status) {
  $status = strtolower(trim((string) $status));

  if (in_array($status, ['active', 'posted', 'successful', 'verified', 'credit'], true)) {
    return 'success';
  }

  if (in_array($status, ['pending', 'processing', 'neutral'], true)) {
    return 'warning';
  }

  if (in_array($status, ['debit', 'failed', 'reversed', 'cancelled', 'inactive'], true)) {
    return 'danger';
  }

  return 'secondary';
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

$requiredTables = ['users', 'schools', 'depts', 'faculties', 'user_wallets', 'wallet_ledger_entries'];
$missingTables = [];

foreach ($requiredTables as $requiredTable) {
  if (!ccWalletTransactionsTableExists($conn, $requiredTable)) {
    $missingTables[] = $requiredTable;
  }
}

$walletTablesReady = empty($missingTables);

$allowedDirections = ['all', 'credit', 'debit'];
$directionFilter = strtolower(trim((string) ($_GET['direction'] ?? 'all')));
if (!in_array($directionFilter, $allowedDirections, true)) {
  $directionFilter = 'all';
}

$allowedDateRanges = ['7', '30', '90', 'all', 'custom'];
$dateRange = trim((string) ($_GET['date_range'] ?? '7'));
if (!in_array($dateRange, $allowedDateRanges, true)) {
  $dateRange = '7';
}

$startDate = ccWalletTransactionsNormalizeDate($_GET['start_date'] ?? '');
$endDate = ccWalletTransactionsNormalizeDate($_GET['end_date'] ?? '');

if ($dateRange !== 'custom') {
  $startDate = '';
  $endDate = '';
}

$summary = [
  'entries_count' => 0,
  'credit_total' => 0,
  'debit_total' => 0,
];
$transactions = [];
$tableNotice = '';

if ($walletTablesReady) {
  $directionSql = '';
  if ($directionFilter === 'credit') {
    $directionSql = " AND l.entry_type IN ('credit', 'refund')";
  } elseif ($directionFilter === 'debit') {
    $directionSql = " AND l.entry_type IN ('debit', 'fee')";
  }

  $dateSql = buildDateFilter($conn, $dateRange, $startDate, $endDate, 'l');
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

  $transactionsSql = "SELECT l.id, l.entry_type, l.amount, l.balance_before, l.balance_after, l.status,
                             l.reference, l.provider_reference, l.description, l.created_at,
                             u.first_name, u.last_name, u.email, u.matric_no,
                             s.name AS school_name, d.name AS dept_name, f.name AS faculty_name
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
        'school_name' => (string) ($row['school_name'] ?? ''),
        'faculty_name' => (string) ($row['faculty_name'] ?? ''),
        'dept_name' => (string) ($row['dept_name'] ?? ''),
        'entry_type' => $entryType,
        'entry_type_label' => ucfirst($entryType),
        'direction' => $direction,
        'direction_label' => ucfirst($direction),
        'amount' => (float) ($row['amount'] ?? 0),
        'amount_sign' => $direction === 'credit' ? '+' : ($direction === 'debit' ? '-' : ''),
        'balance_after' => (float) ($row['balance_after'] ?? 0),
        'status' => (string) ($row['status'] ?? ''),
        'status_label' => ucfirst((string) ($row['status'] ?? 'unknown')),
        'reference_display' => $referenceDisplay,
        'description' => (string) ($row['description'] ?? ''),
        'created_at_display' => ccWalletTransactionsFormatDateTime((string) ($row['created_at'] ?? '')),
      ];
    }
  }

  if ($summary['entries_count'] > count($transactions)) {
    $tableNotice = 'Showing the most recent 500 ledger entries for the selected filters.';
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Wallet Transactions | Nivasity Command Center</title>
    <meta name="description" content="Review wallet ledger entries with credit or debit direction filters and transaction date ranges." />
    <?php include('partials/_head.php') ?>
  </head>
  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <?php include('partials/_sidebar.php') ?>
        <div class="layout-page">
          <?php include('partials/_navbar.php') ?>
          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                  <h4 class="fw-bold py-3 mb-1"><span class="text-muted fw-light">Finances /</span> Wallet Transactions</h4>
                  <p class="mb-0 text-muted">Monitor wallet credits and debits across student wallets.</p>
                </div>
              </div>

              <?php if (!$walletTablesReady) { ?>
              <div class="alert alert-warning" role="alert">
                Wallet transaction tables are not available in this environment yet. Missing table(s): <strong><?php echo htmlspecialchars(implode(', ', $missingTables)); ?></strong>.
              </div>
              <?php } ?>

              <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Filtered Entries</span>
                      <h3 class="card-title mb-1"><?php echo number_format((int) $summary['entries_count']); ?></h3>
                      <small class="text-muted">Total wallet ledger rows matching the current filters.</small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Credits</span>
                      <h3 class="card-title mb-1 text-success"><?php echo ccWalletTransactionsFormatAmount($summary['credit_total']); ?></h3>
                      <small class="text-muted">Credit and refund entries in the selected range.</small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-12 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-1">Debits</span>
                      <h3 class="card-title mb-1 text-danger"><?php echo ccWalletTransactionsFormatAmount($summary['debit_total']); ?></h3>
                      <small class="text-muted">Debit and fee entries in the selected range.</small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-body">
                  <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                      <label for="direction" class="form-label">Direction</label>
                      <select name="direction" id="direction" class="form-select" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        <option value="all" <?php echo $directionFilter === 'all' ? 'selected' : ''; ?>>All Entries</option>
                        <option value="credit" <?php echo $directionFilter === 'credit' ? 'selected' : ''; ?>>Credits</option>
                        <option value="debit" <?php echo $directionFilter === 'debit' ? 'selected' : ''; ?>>Debits</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label for="dateRange" class="form-label">Date Range</label>
                      <select name="date_range" id="dateRange" class="form-select" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                      </select>
                    </div>
                    <div class="col-md-6 <?php echo $dateRange === 'custom' ? '' : 'd-none'; ?>" id="customDateRange">
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label for="startDate" class="form-label">Start Date</label>
                          <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                          <label for="endDate" class="form-label">End Date</label>
                          <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                      <button type="submit" class="btn btn-primary" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>Apply Filters</button>
                      <a href="wallet_transactions.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                  <div>
                    <h5 class="mb-1">Wallet Ledger Entries</h5>
                    <small class="text-muted">Results are ordered by most recent transaction date first.</small>
                  </div>
                  <?php if ($tableNotice !== '') { ?>
                  <small class="text-muted"><?php echo htmlspecialchars($tableNotice); ?></small>
                  <?php } ?>
                </div>
                <div class="card-body">
                  <div class="table-responsive text-nowrap">
                    <table class="table table-striped align-middle">
                      <thead class="table-secondary">
                        <tr>
                          <th>Date &amp; Time</th>
                          <th>Student</th>
                          <th>Direction</th>
                          <th>Entry Type</th>
                          <th>Amount</th>
                          <th>Balance After</th>
                          <th>Reference</th>
                          <th>Description</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!$walletTablesReady) { ?>
                        <tr>
                          <td colspan="9" class="text-center text-muted py-4">Wallet ledger tables are unavailable in this database.</td>
                        </tr>
                        <?php } elseif ($transactions === []) { ?>
                        <tr>
                          <td colspan="9" class="text-center text-muted py-4">No wallet transactions matched the current filters.</td>
                        </tr>
                        <?php } else { ?>
                          <?php foreach ($transactions as $transaction) { ?>
                          <tr>
                            <td><?php echo htmlspecialchars($transaction['created_at_display']); ?></td>
                            <td>
                              <div class="fw-semibold"><?php echo htmlspecialchars($transaction['student_name'] !== '' ? $transaction['student_name'] : 'Unknown User'); ?></div>
                              <small class="text-muted d-block"><?php echo htmlspecialchars($transaction['matric_no'] !== '' ? $transaction['matric_no'] : $transaction['email']); ?></small>
                              <small class="text-muted d-block">
                                <?php
                                  $locationParts = array_filter([
                                    $transaction['school_name'],
                                    $transaction['faculty_name'],
                                    $transaction['dept_name'],
                                  ]);
                                  echo htmlspecialchars($locationParts !== [] ? implode(' / ', $locationParts) : 'No academic scope');
                                ?>
                              </small>
                            </td>
                            <td><span class="badge bg-label-<?php echo htmlspecialchars(ccWalletTransactionsBadgeClass($transaction['direction'])); ?>"><?php echo htmlspecialchars($transaction['direction_label']); ?></span></td>
                            <td><span class="badge bg-label-<?php echo htmlspecialchars(ccWalletTransactionsBadgeClass($transaction['entry_type'])); ?>"><?php echo htmlspecialchars($transaction['entry_type_label']); ?></span></td>
                            <td class="fw-semibold <?php echo $transaction['direction'] === 'credit' ? 'text-success' : ($transaction['direction'] === 'debit' ? 'text-danger' : 'text-body'); ?>">
                              <?php echo htmlspecialchars($transaction['amount_sign']); ?><?php echo ccWalletTransactionsFormatAmount($transaction['amount']); ?>
                            </td>
                            <td><?php echo ccWalletTransactionsFormatAmount($transaction['balance_after']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['reference_display']); ?></td>
                            <td class="text-wrap" style="min-width: 220px;"><?php echo htmlspecialchars($transaction['description'] !== '' ? $transaction['description'] : '-'); ?></td>
                            <td><span class="badge bg-label-<?php echo htmlspecialchars(ccWalletTransactionsBadgeClass($transaction['status'])); ?>"><?php echo htmlspecialchars($transaction['status_label']); ?></span></td>
                          </tr>
                          <?php } ?>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      $(function() {
        function toggleCustomDateRange() {
          var showCustomRange = $('#dateRange').val() === 'custom';
          $('#customDateRange').toggleClass('d-none', !showCustomRange);
        }

        $('#dateRange').on('change', toggleCustomDateRange);
        toggleCustomDateRange();
      });
    </script>
  </body>
</html>