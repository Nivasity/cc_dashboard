<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRoles = [1, 2, 3, 4, 5];

if (!$finance_mgt_menu || !in_array($admin_role, $allowedRoles, true)) {
  header('Location: /');
  exit();
}

$admin_school = (int) ($admin_['school'] ?? 0);
$admin_faculty = (int) ($admin_['faculty'] ?? 0);
$lookup = trim((string) ($_GET['lookup'] ?? ''));
$lookupPerformed = $lookup !== '';
$allowedEntryTypes = ['all', 'credit', 'debit', 'refund', 'fee', 'adjustment'];
$entryTypeFilter = strtolower(trim((string) ($_GET['entry_type'] ?? 'all')));
if (!in_array($entryTypeFilter, $allowedEntryTypes, true)) {
  $entryTypeFilter = 'all';
}

$dateFromFilter = trim((string) ($_GET['date_from'] ?? ''));
$dateToFilter = trim((string) ($_GET['date_to'] ?? ''));

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

function ccStudentWalletFormatAmount($amount) {
  return number_format((int) $amount);
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

function ccStudentWalletIsValidDate($value) {
  $value = trim((string) $value);
  if ($value === '') {
    return false;
  }

  $date = DateTime::createFromFormat('Y-m-d', $value);
  return $date instanceof DateTime && $date->format('Y-m-d') === $value;
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
      $entryType = strtolower((string) ($row['entry_type'] ?? 'adjustment'));
      $isCredit = in_array($entryType, ['credit', 'refund'], true);
      $isDebit = in_array($entryType, ['debit', 'fee'], true);

      $row['direction'] = $isCredit ? 'credit' : ($isDebit ? 'debit' : 'neutral');
      $row['amount_sign'] = $isCredit ? '+' : ($isDebit ? '-' : '');
      $entries[] = $row;
    }
  }

  mysqli_stmt_close($stmt);
  return $entries;
}

$requiredTables = ['users', 'schools', 'depts', 'faculties', 'user_wallets', 'wallet_virtual_accounts', 'wallet_ledger_entries'];
$missingTables = [];

foreach ($requiredTables as $requiredTable) {
  if (!ccStudentWalletTableExists($conn, $requiredTable)) {
    $missingTables[] = $requiredTable;
  }
}

$walletTablesReady = empty($missingTables);
$student = null;
$wallet = null;
$walletOverview = [
  'entries_count' => 0,
  'credits_total' => 0,
  'debits_total' => 0,
  'refunds_total' => 0,
  'fees_total' => 0,
];
$walletEntries = [];
$lookupError = '';
$filterError = '';

if ($dateFromFilter !== '' && !ccStudentWalletIsValidDate($dateFromFilter)) {
  $filterError = 'Start date must use the YYYY-MM-DD format.';
}

if ($filterError === '' && $dateToFilter !== '' && !ccStudentWalletIsValidDate($dateToFilter)) {
  $filterError = 'End date must use the YYYY-MM-DD format.';
}

if ($filterError === '' && $dateFromFilter !== '' && $dateToFilter !== '' && $dateFromFilter > $dateToFilter) {
  $filterError = 'Start date cannot be later than end date.';
}

if ($lookupPerformed && $walletTablesReady) {
  $student = ccStudentWalletFindStudent($conn, $lookup, $admin_role, $admin_school, $admin_faculty);

  if ($student) {
    $wallet = ccStudentWalletFetchWallet($conn, (int) ($student['id'] ?? 0));
    if ($wallet) {
      $walletOverview = ccStudentWalletFetchOverview($conn, (int) ($wallet['id'] ?? 0));
      if ($filterError === '') {
        $walletEntries = ccStudentWalletFetchEntries($conn, (int) ($wallet['id'] ?? 0), [
          'entry_type' => $entryTypeFilter,
          'date_from' => $dateFromFilter,
          'date_to' => $dateToFilter,
        ], 50);
      }
    }
  } else {
    $lookupError = 'No student matched that matric number or email address.';
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Student Wallets | Nivasity Command Center</title>
    <meta name="description" content="Find a student wallet by matric number or email and review balance and ledger history." />
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
                  <h4 class="fw-bold py-3 mb-1"><span class="text-muted fw-light">Finances /</span> Student Wallets</h4>
                </div>
              </div>

              <?php if (!$walletTablesReady) { ?>
              <div class="alert alert-warning" role="alert">
                Wallet tables are not available in this environment yet. Missing table(s): <strong><?php echo htmlspecialchars(implode(', ', $missingTables)); ?></strong>.
              </div>
              <?php } ?>

              <div class="card mb-4">
                <div class="card-body">
                  <form method="get" class="row g-3 align-items-end">
                    <div class="col-lg-8">
                      <label for="lookup" class="form-label">Matric Number or Email Address</label>
                      <input
                        type="text"
                        class="form-control"
                        id="lookup"
                        name="lookup"
                        value="<?php echo htmlspecialchars($lookup); ?>"
                        placeholder="e.g. 19/1234 or student@example.com"
                        <?php echo !$walletTablesReady ? 'disabled' : ''; ?>
                      />
                    </div>
                    <div class="col-lg-4 d-flex gap-2">
                      <button type="submit" class="btn btn-primary" <?php echo !$walletTablesReady ? 'disabled' : ''; ?>>Find Wallet</button>
                      <?php if ($lookupPerformed) { ?>
                      <a href="student_wallets.php" class="btn btn-outline-secondary">Clear</a>
                      <?php } ?>
                    </div>
                  </form>
                </div>
              </div>

              <?php if ($lookupError !== '') { ?>
              <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($lookupError); ?>
              </div>
              <?php } ?>

              <?php if ($filterError !== '') { ?>
              <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($filterError); ?>
              </div>
              <?php } ?>

              <?php if ($student) { ?>
              <div class="row mb-4">
                <div class="col-xl-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Current Balance</span>
                      <h3 class="mb-0">&#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($wallet['balance'] ?? 0)); ?></h3>
                      <small class="text-muted">Stored in <strong>user_wallets.balance</strong></small>
                    </div>
                  </div>
                </div>
                <div class="col-xl-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Total Credits</span>
                      <h3 class="mb-0 text-success">&#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($walletOverview['credits_total'])); ?></h3>
                      <small class="text-muted">Credit + refund entries</small>
                    </div>
                  </div>
                </div>
                <div class="col-xl-4 col-md-12 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Total Debits</span>
                      <h3 class="mb-0 text-danger">&#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($walletOverview['debits_total'])); ?></h3>
                      <small class="text-muted"><?php echo (int) $walletOverview['entries_count']; ?> ledger entr<?php echo (int) $walletOverview['entries_count'] === 1 ? 'y' : 'ies'; ?></small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row g-4">
                <div class="col-xl-4">
                  <div class="card mb-4">
                    <div class="card-header">
                      <h5 class="mb-1">Student Details</h5>
                    </div>
                    <div class="card-body">
                      <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?: '-'); ?></dd>

                        <dt class="col-sm-4">Matric</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($student['matric_no'] ?? '-')); ?></dd>

                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($student['email'] ?? '-')); ?></dd>

                        <dt class="col-sm-4">Phone</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($student['phone'] ?? '-')); ?></dd>

                        <dt class="col-sm-4">School</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($student['school_name'] ?? '-')); ?></dd>

                        <dt class="col-sm-4">Faculty</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($student['faculty_name'] ?? '-')); ?></dd>

                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($student['dept_name'] ?? '-')); ?></dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                          <span class="badge bg-label-<?php echo htmlspecialchars(ccStudentWalletStatusBadge($student['user_status'] ?? '')); ?>">
                            <?php echo htmlspecialchars(ucfirst((string) ($student['user_status'] ?? 'unknown'))); ?>
                          </span>
                        </dd>
                      </dl>
                    </div>
                  </div>

                  <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="mb-1">Wallet Details</h5>
                      <?php if ($wallet) { ?>
                      <span class="badge bg-label-<?php echo htmlspecialchars(ccStudentWalletStatusBadge($wallet['status'] ?? '')); ?>">
                        <?php echo htmlspecialchars(ucfirst((string) ($wallet['status'] ?? 'unknown'))); ?>
                      </span>
                      <?php } ?>
                    </div>
                    <div class="card-body">
                      <?php if ($wallet) { ?>
                      <dl class="row mb-0">
                        <dt class="col-sm-5">Wallet ID</dt>
                        <dd class="col-sm-7"><?php echo (int) ($wallet['id'] ?? 0); ?></dd>

                        <dt class="col-sm-5">Requested Via</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($wallet['requested_via'] ?? '-')); ?></dd>

                        <dt class="col-sm-5">Currency</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($wallet['currency'] ?? 'NGN')); ?></dd>

                        <dt class="col-sm-5">Account Name</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($wallet['account_name'] ?? '-')); ?></dd>

                        <dt class="col-sm-5">Account Number</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($wallet['account_number'] ?? '-')); ?></dd>

                        <dt class="col-sm-5">Bank</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($wallet['bank_name'] ?? '-')); ?></dd>

                        <dt class="col-sm-5">Provider</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($wallet['provider'] ?? '-')); ?></dd>

                        <dt class="col-sm-5">VA Status</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($wallet['virtual_account_status'] ?? '-')); ?></dd>

                        <dt class="col-sm-5">Created</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars(ccStudentWalletFormatDate($wallet['created_at'] ?? '')); ?></dd>

                        <dt class="col-sm-5">Updated</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars(ccStudentWalletFormatDate($wallet['updated_at'] ?? '')); ?></dd>
                      </dl>
                      <?php } else { ?>
                      <div class="alert alert-info mb-0" role="alert">
                        This student exists, but no wallet has been created for the account yet.
                      </div>
                      <?php } ?>
                    </div>
                  </div>
                </div>

                <div class="col-xl-8">
                  <div class="card">
                    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                      <div>
                        <h5 class="mb-1">Wallet Ledger</h5>
                        <small class="text-muted">Most recent 50 entries from <strong>wallet_ledger_entries</strong>, with optional date and type filters.</small>
                      </div>
                      <?php if ($wallet) { ?>
                      <div class="text-muted small">
                        Refunds: &#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($walletOverview['refunds_total'])); ?>
                        <span class="mx-2">|</span>
                        Fees: &#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($walletOverview['fees_total'])); ?>
                      </div>
                      <?php } ?>
                    </div>
                    <div class="card-body">
                      <?php if (!$wallet) { ?>
                      <div class="text-muted">No wallet ledger is available because the student does not have a wallet yet.</div>
                      <?php } else { ?>
                      <form method="get" class="row g-3 align-items-end mb-4">
                        <input type="hidden" name="lookup" value="<?php echo htmlspecialchars($lookup); ?>" />
                        <div class="col-md-4">
                          <label for="entry_type" class="form-label">Entry Type</label>
                          <select class="form-select" id="entry_type" name="entry_type">
                            <option value="all" <?php echo $entryTypeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="credit" <?php echo $entryTypeFilter === 'credit' ? 'selected' : ''; ?>>Credit</option>
                            <option value="debit" <?php echo $entryTypeFilter === 'debit' ? 'selected' : ''; ?>>Debit</option>
                            <option value="refund" <?php echo $entryTypeFilter === 'refund' ? 'selected' : ''; ?>>Refund</option>
                            <option value="fee" <?php echo $entryTypeFilter === 'fee' ? 'selected' : ''; ?>>Fee</option>
                            <option value="adjustment" <?php echo $entryTypeFilter === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                          </select>
                        </div>
                        <div class="col-md-3">
                          <label for="date_from" class="form-label">From</label>
                          <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFromFilter); ?>" />
                        </div>
                        <div class="col-md-3">
                          <label for="date_to" class="form-label">To</label>
                          <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateToFilter); ?>" />
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                          <button type="submit" class="btn btn-primary w-100">Apply</button>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                          <small class="text-muted">
                            Showing <?php echo count($walletEntries); ?> result<?php echo count($walletEntries) === 1 ? '' : 's'; ?>
                            <?php if ($entryTypeFilter !== 'all' || $dateFromFilter !== '' || $dateToFilter !== '') { ?>
                            for the active filters.
                            <?php } else { ?>
                            without additional filters.
                            <?php } ?>
                          </small>
                          <?php if ($entryTypeFilter !== 'all' || $dateFromFilter !== '' || $dateToFilter !== '') { ?>
                          <a href="student_wallets.php?lookup=<?php echo urlencode($lookup); ?>" class="btn btn-sm btn-outline-secondary">Reset Filters</a>
                          <?php } ?>
                        </div>
                      </form>
                      <div class="table-responsive text-nowrap">
                        <table class="table table-striped align-middle">
                          <thead class="table-secondary">
                            <tr>
                              <th>Date</th>
                              <th>Type</th>
                              <th>Amount</th>
                              <th>Balance Before</th>
                              <th>Balance After</th>
                              <th>Status</th>
                              <th>Reference</th>
                              <th>Description</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($walletEntries)) { ?>
                            <tr>
                              <td colspan="8" class="text-center text-muted py-4">No wallet ledger entries found for this student.</td>
                            </tr>
                            <?php } ?>
                            <?php foreach ($walletEntries as $entry) { ?>
                            <?php
                              $entryType = strtolower((string) ($entry['entry_type'] ?? 'adjustment'));
                              $direction = (string) ($entry['direction'] ?? 'neutral');
                              $amountClass = $direction === 'credit' ? 'text-success' : ($direction === 'debit' ? 'text-danger' : 'text-muted');
                              $referenceDisplay = trim((string) ($entry['provider_reference'] ?? ''));
                              if ($referenceDisplay === '') {
                                $referenceDisplay = (string) ($entry['reference'] ?? '-');
                              }
                            ?>
                            <tr>
                              <td><?php echo htmlspecialchars(ccStudentWalletFormatDate($entry['created_at'] ?? '')); ?></td>
                              <td>
                                <span class="badge bg-label-<?php echo htmlspecialchars(ccStudentWalletEntryTypeBadge($entryType)); ?>">
                                  <?php echo htmlspecialchars(ucfirst($entryType)); ?>
                                </span>
                              </td>
                              <td class="<?php echo $amountClass; ?> fw-semibold"><?php echo htmlspecialchars((string) ($entry['amount_sign'] ?? '')); ?>&#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($entry['amount'] ?? 0)); ?></td>
                              <td>&#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($entry['balance_before'] ?? 0)); ?></td>
                              <td>&#8358;<?php echo htmlspecialchars(ccStudentWalletFormatAmount($entry['balance_after'] ?? 0)); ?></td>
                              <td>
                                <span class="badge bg-label-<?php echo htmlspecialchars(ccStudentWalletStatusBadge($entry['status'] ?? '')); ?>">
                                  <?php echo htmlspecialchars(ucfirst((string) ($entry['status'] ?? 'unknown'))); ?>
                                </span>
                              </td>
                              <td class="text-wrap" style="min-width: 180px;"><?php echo htmlspecialchars($referenceDisplay); ?></td>
                              <td class="text-wrap" style="min-width: 220px;"><?php echo htmlspecialchars((string) ($entry['description'] ?? '-')); ?></td>
                            </tr>
                            <?php } ?>
                          </tbody>
                        </table>
                      </div>
                      <?php } ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php } ?>
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
  </body>
</html>