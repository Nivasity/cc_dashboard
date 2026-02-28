<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');

header('Content-Type: application/json');

$adminRole = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$adminId = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;

if ($adminId <= 0 || $adminRole <= 0) {
  respond(401, [
    'status' => 'failed',
    'message' => 'Unauthorized',
  ]);
}

$allowedRoles = [1, 2, 3, 4];
if (!in_array($adminRole, $allowedRoles, true)) {
  respond(403, [
    'status' => 'failed',
    'message' => 'You are not allowed to access refund tools.',
  ]);
}

$adminScope = getAdminScope($conn, $adminRole, $adminId);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$request = getRequestPayload();
$action = strtolower(trim((string) ($request['action'] ?? $_GET['action'] ?? 'queue')));

switch ($action) {
  case 'queue':
    if ($method !== 'GET') {
      methodNotAllowed('GET');
    }
    refreshRefundStatuses($conn);
    handleQueue($conn, $adminScope, $request);
    break;

  case 'detail':
    if ($method !== 'GET') {
      methodNotAllowed('GET');
    }
    refreshRefundStatuses($conn);
    handleDetail($conn, $adminScope, $request);
    break;

  case 'monitoring_outstanding':
    if ($method !== 'GET') {
      methodNotAllowed('GET');
    }
    refreshRefundStatuses($conn);
    handleOutstandingMonitoring($conn, $adminScope, $request);
    break;

  case 'monitoring_daily':
    if ($method !== 'GET') {
      methodNotAllowed('GET');
    }
    handleDailyMonitoring($conn, $adminScope, $request);
    break;

  case 'lookup_student':
    if ($method !== 'GET') {
      methodNotAllowed('GET');
    }
    handleLookupStudent($conn, $adminScope, $request);
    break;

  case 'lookup_source':
    if ($method !== 'GET') {
      methodNotAllowed('GET');
    }
    handleLookupSource($conn, $adminScope, $request);
    break;

  case 'create':
    if (!in_array($method, ['POST', 'PUT'], true)) {
      methodNotAllowed('POST, PUT');
    }
    handleCreateRefund($conn, $adminScope, $request, $adminId);
    break;

  case 'cancel':
    if (!in_array($method, ['POST', 'PATCH'], true)) {
      methodNotAllowed('POST, PATCH');
    }
    handleCancelRefund($conn, $adminScope, $request, $adminId);
    break;

  default:
    respond(400, [
      'status' => 'failed',
      'message' => 'Invalid action supplied.',
    ]);
}

function handleQueue(mysqli $conn, array $adminScope, array $request): void
{
  $schoolId = isset($request['school_id']) ? toPositiveIntOrNull($request['school_id']) : null;
  if ($adminScope['school_id'] !== null) {
    $schoolId = $adminScope['school_id'];
  }

  $status = strtolower(trim((string) ($request['status'] ?? '')));
  $allowedStatuses = ['pending', 'partially_applied', 'applied', 'cancelled'];
  if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    badRequest('Invalid refund status filter.');
  }

  $createdFrom = normalizeDateOrNull((string) ($request['created_from'] ?? ''));
  $createdTo = normalizeDateOrNull((string) ($request['created_to'] ?? ''));
  if ($createdFrom !== null && $createdTo !== null && $createdFrom > $createdTo) {
    badRequest('created_from cannot be after created_to.');
  }

  $sourceRefSearch = trim((string) ($request['source_ref_id'] ?? $request['source_ref'] ?? ''));

  $limit = isset($request['limit']) ? (int) $request['limit'] : 100;
  if ($limit < 1) {
    $limit = 1;
  }
  if ($limit > 500) {
    $limit = 500;
  }

  $offset = isset($request['offset']) ? (int) $request['offset'] : 0;
  if ($offset < 0) {
    $offset = 0;
  }

  $where = [];
  $types = '';
  $params = [];

  if ($schoolId !== null) {
    $where[] = 'r.school_id = ?';
    $types .= 'i';
    $params[] = $schoolId;
  }

  if ($status !== '') {
    $where[] = 'r.status = ?';
    $types .= 's';
    $params[] = $status;
  }

  if ($createdFrom !== null) {
    $where[] = 'r.created_at >= ?';
    $types .= 's';
    $params[] = $createdFrom . ' 00:00:00';
  }

  if ($createdTo !== null) {
    $where[] = 'r.created_at <= ?';
    $types .= 's';
    $params[] = $createdTo . ' 23:59:59';
  }

  if ($sourceRefSearch !== '') {
    $where[] = 'r.ref_id LIKE ?';
    $types .= 's';
    $params[] = '%' . $sourceRefSearch . '%';
  }

  $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

  $countRows = dbFetchAll(
    $conn,
    "SELECT COUNT(*) AS total_rows FROM refunds r{$whereSql}",
    $types,
    $params
  );
  $totalRows = isset($countRows[0]['total_rows']) ? (int) $countRows[0]['total_rows'] : 0;

  $dataRows = dbFetchAll(
    $conn,
    "SELECT
       r.id,
       r.school_id,
       COALESCE(s.name, CONCAT('School #', r.school_id)) AS school_name,
       r.student_id,
       CASE
         WHEN u.id IS NULL THEN NULL
         ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
       END AS student_name,
       r.ref_id,
       r.amount,
       r.remaining_amount,
       (COALESCE(r.amount, 0) - COALESCE(r.remaining_amount, 0)) AS consumed_amount,
       r.status,
       r.reason,
       r.created_at,
       r.updated_at
     FROM refunds r
     LEFT JOIN schools s ON s.id = r.school_id
     LEFT JOIN users u ON u.id = r.student_id
     {$whereSql}
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT {$limit} OFFSET {$offset}",
    $types,
    $params
  );

  respond(200, [
    'status' => 'success',
    'message' => 'Refund queue loaded successfully.',
    'meta' => [
      'total' => $totalRows,
      'count' => count($dataRows),
      'limit' => $limit,
      'offset' => $offset,
    ],
    'refunds' => normalizeNumericRows($dataRows, ['amount', 'remaining_amount', 'consumed_amount']),
  ]);
}

function handleDetail(mysqli $conn, array $adminScope, array $request): void
{
  $refundId = toPositiveIntOrNull($request['refund_id'] ?? $request['id'] ?? null);
  if ($refundId === null) {
    badRequest('refund_id is required.');
  }

  $refundRows = dbFetchAll(
    $conn,
    "SELECT
       r.id,
       r.school_id,
       COALESCE(s.name, CONCAT('School #', r.school_id)) AS school_name,
       r.student_id,
       CASE
         WHEN u.id IS NULL THEN NULL
         ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
       END AS student_name,
       r.ref_id,
       r.amount,
       r.remaining_amount,
       (COALESCE(r.amount, 0) - COALESCE(r.remaining_amount, 0)) AS consumed_amount,
       r.status,
       r.reason,
       r.created_at,
       r.updated_at
     FROM refunds r
     LEFT JOIN schools s ON s.id = r.school_id
     LEFT JOIN users u ON u.id = r.student_id
     WHERE r.id = ?
     LIMIT 1",
    'i',
    [$refundId]
  );

  if ($refundRows === []) {
    respond(404, [
      'status' => 'failed',
      'message' => 'Refund not found.',
    ]);
  }

  $refund = $refundRows[0];
  enforceSchoolScope($adminScope, (int) $refund['school_id']);

  $reservationRows = dbFetchAll(
    $conn,
    'SELECT
       id,
       refund_id,
       ref_id,
       split_sequence,
       school_id,
       payer_user_id,
       gateway,
       amount,
       channel,
       status,
       reserved_at,
       consumed_at,
       released_at,
       release_reason
     FROM refund_reservations
     WHERE refund_id = ?
     ORDER BY split_sequence ASC, id ASC',
    'i',
    [$refundId]
  );

  $totalsRows = dbFetchAll(
    $conn,
    "SELECT
       COALESCE(SUM(CASE WHEN status = 'reserved' THEN amount ELSE 0 END), 0) AS total_reserved,
       COALESCE(SUM(CASE WHEN status = 'consumed' THEN amount ELSE 0 END), 0) AS total_consumed,
       COALESCE(SUM(CASE WHEN status = 'released' THEN amount ELSE 0 END), 0) AS total_released
     FROM refund_reservations
     WHERE refund_id = ?",
    'i',
    [$refundId]
  );

  $totals = $totalsRows[0] ?? [
    'total_reserved' => 0,
    'total_consumed' => 0,
    'total_released' => 0,
  ];
  $totals['outstanding'] = (float) ($refund['remaining_amount'] ?? 0);

  respond(200, [
    'status' => 'success',
    'message' => 'Refund detail loaded successfully.',
    'refund' => normalizeNumericRow($refund, ['amount', 'remaining_amount', 'consumed_amount']),
    'reservations' => normalizeNumericRows($reservationRows, ['amount']),
    'totals' => normalizeNumericRow($totals, ['total_reserved', 'total_consumed', 'total_released', 'outstanding']),
  ]);
}

function handleOutstandingMonitoring(mysqli $conn, array $adminScope, array $request): void
{
  $schoolId = isset($request['school_id']) ? toPositiveIntOrNull($request['school_id']) : null;
  if ($adminScope['school_id'] !== null) {
    $schoolId = $adminScope['school_id'];
  }

  $where = ["r.status IN ('pending', 'partially_applied')"];
  $refundedWhere = ["r.status IN ('pending', 'partially_applied', 'applied')"];
  $types = '';
  $params = [];

  if ($schoolId !== null) {
    $where[] = 'r.school_id = ?';
    $refundedWhere[] = 'r.school_id = ?';
    $types .= 'i';
    $params[] = $schoolId;
  }

  $whereSql = ' WHERE ' . implode(' AND ', $where);
  $refundedWhereSql = ' WHERE ' . implode(' AND ', $refundedWhere);

  $rows = dbFetchAll(
    $conn,
    "SELECT
       r.school_id,
       COALESCE(s.name, CONCAT('School #', r.school_id)) AS school_name,
       COALESCE(SUM(r.remaining_amount), 0) AS outstanding_amount,
       COUNT(*) AS refunds_count
     FROM refunds r
     LEFT JOIN schools s ON s.id = r.school_id
     {$whereSql}
     GROUP BY r.school_id, s.name
     ORDER BY outstanding_amount DESC, school_name ASC",
    $types,
    $params
  );

  $grandTotal = 0.0;
  foreach ($rows as $row) {
    $grandTotal += (float) ($row['outstanding_amount'] ?? 0);
  }

  $refundedRows = dbFetchAll(
    $conn,
    "SELECT
       COALESCE(SUM(GREATEST(COALESCE(r.amount, 0) - COALESCE(r.remaining_amount, 0), 0)), 0) AS total_refunded
     FROM refunds r
     {$refundedWhereSql}",
    $types,
    $params
  );

  $totalRefunded = isset($refundedRows[0]['total_refunded'])
    ? (float) $refundedRows[0]['total_refunded']
    : 0.0;

  respond(200, [
    'status' => 'success',
    'message' => 'Outstanding liability loaded successfully.',
    'total_outstanding' => (float) $grandTotal,
    'total_refunded' => $totalRefunded,
    'rows' => normalizeNumericRows($rows, ['outstanding_amount']),
  ]);
}

function handleDailyMonitoring(mysqli $conn, array $adminScope, array $request): void
{
  $schoolId = isset($request['school_id']) ? toPositiveIntOrNull($request['school_id']) : null;
  if ($adminScope['school_id'] !== null) {
    $schoolId = $adminScope['school_id'];
  }

  $fromDate = normalizeDateOrNull((string) ($request['from_date'] ?? ''));
  $toDate = normalizeDateOrNull((string) ($request['to_date'] ?? ''));

  if ($fromDate === null && $toDate === null) {
    $toDateObj = new DateTimeImmutable('today');
    $fromDateObj = $toDateObj->modify('-29 days');
    $fromDate = $fromDateObj->format('Y-m-d');
    $toDate = $toDateObj->format('Y-m-d');
  }

  if ($fromDate !== null && $toDate === null) {
    $toDate = $fromDate;
  }

  if ($toDate !== null && $fromDate === null) {
    $fromDate = $toDate;
  }

  if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
    badRequest('from_date cannot be after to_date.');
  }

  $where = ["rr.status = 'consumed'", 'rr.consumed_at IS NOT NULL'];
  $types = '';
  $params = [];

  if ($schoolId !== null) {
    $where[] = 'rr.school_id = ?';
    $types .= 'i';
    $params[] = $schoolId;
  }

  if ($fromDate !== null) {
    $where[] = 'rr.consumed_at >= ?';
    $types .= 's';
    $params[] = $fromDate . ' 00:00:00';
  }

  if ($toDate !== null) {
    $where[] = 'rr.consumed_at <= ?';
    $types .= 's';
    $params[] = $toDate . ' 23:59:59';
  }

  $whereSql = ' WHERE ' . implode(' AND ', $where);

  $rows = dbFetchAll(
    $conn,
    "SELECT
       DATE(rr.consumed_at) AS report_date,
       rr.school_id,
       COALESCE(s.name, CONCAT('School #', rr.school_id)) AS school_name,
       COALESCE(SUM(rr.amount), 0) AS total_consumed,
       COUNT(*) AS consumed_rows
     FROM refund_reservations rr
     LEFT JOIN schools s ON s.id = rr.school_id
     {$whereSql}
     GROUP BY DATE(rr.consumed_at), rr.school_id, s.name
     ORDER BY report_date DESC, school_name ASC",
    $types,
    $params
  );

  respond(200, [
    'status' => 'success',
    'message' => 'Daily consumption report loaded successfully.',
    'range' => [
      'from_date' => $fromDate,
      'to_date' => $toDate,
    ],
    'rows' => normalizeNumericRows($rows, ['total_consumed']),
  ]);
}

function handleLookupStudent(mysqli $conn, array $adminScope, array $request): void
{
  $studentEmail = normalizeEmail($request['student_email'] ?? $request['email'] ?? null);

  $rows = dbFetchAll(
    $conn,
    "SELECT
       u.id,
       u.first_name,
       u.last_name,
       u.email,
       u.school,
       COALESCE(s.name, CONCAT('School #', u.school)) AS school_name,
       u.status
     FROM users u
     LEFT JOIN schools s ON s.id = u.school
     WHERE u.email = ?
     LIMIT 1",
    's',
    [$studentEmail]
  );

  if ($rows === []) {
    respond(404, [
      'status' => 'failed',
      'message' => 'No student found for the provided email.',
    ]);
  }

  $student = $rows[0];
  enforceSchoolScope($adminScope, (int) ($student['school'] ?? 0));

  respond(200, [
    'status' => 'success',
    'message' => 'Student found.',
    'student' => $student,
  ]);
}

function handleLookupSource(mysqli $conn, array $adminScope, array $request): void
{
  $sourceRefId = trim((string) ($request['source_ref_id'] ?? $request['source_ref'] ?? $request['ref_id'] ?? ''));
  if ($sourceRefId === '') {
    badRequest('source_ref_id is required.');
  }

  $studentId = toPositiveIntOrNull($request['student_id'] ?? null);
  if ($studentId === null) {
    badRequest('student_id is required for source lookup.');
  }

  $studentRows = dbFetchAll(
    $conn,
    "SELECT
       u.id,
       u.school,
       u.email,
       u.first_name,
       u.last_name
     FROM users u
     WHERE u.id = ?
     LIMIT 1",
    'i',
    [$studentId]
  );

  if ($studentRows === []) {
    respond(404, [
      'status' => 'failed',
      'message' => 'Student not found.',
    ]);
  }

  $student = $studentRows[0];
  $studentSchool = isset($student['school']) ? (int) $student['school'] : 0;
  enforceSchoolScope($adminScope, $studentSchool);

  $transactionRows = dbFetchAll(
    $conn,
    "SELECT
       t.id,
       t.ref_id,
       t.user_id,
       t.status,
       t.amount,
       t.created_at
     FROM transactions t
     WHERE t.ref_id = ?
     LIMIT 1",
    's',
    [$sourceRefId]
  );

  if ($transactionRows === []) {
    respond(404, [
      'status' => 'failed',
      'message' => 'Source transaction not found.',
    ]);
  }

  $transaction = $transactionRows[0];
  $transactionStatus = strtolower((string) ($transaction['status'] ?? ''));
  if (!in_array($transactionStatus, ['successful', 'success'], true)) {
    respond(400, [
      'status' => 'failed',
      'message' => 'Source transaction status must be successful.',
      'transaction_status' => $transaction['status'],
    ]);
  }

  $transactionUserId = isset($transaction['user_id']) ? (int) $transaction['user_id'] : 0;
  if ($transactionUserId !== $studentId) {
    respond(409, [
      'status' => 'failed',
      'message' => 'Source transaction does not belong to the selected student.',
    ]);
  }

  $materials = getRefundableMaterialsForRef($conn, $sourceRefId, $studentId);

  if ($studentSchool > 0) {
    foreach ($materials as $material) {
      $materialSchoolId = isset($material['school_id']) ? (int) $material['school_id'] : 0;
      if ($materialSchoolId > 0 && $materialSchoolId !== $studentSchool) {
        respond(409, [
          'status' => 'failed',
          'message' => 'Student school does not match materials purchased for this source transaction.',
        ]);
      }
    }
  }

  respond(200, [
    'status' => 'success',
    'message' => 'Source transaction validated.',
    'transaction' => normalizeNumericRow($transaction, ['amount']),
    'materials' => normalizeNumericRows($materials, ['price']),
    'summary' => [
      'materials_count' => count($materials),
      'materials_total' => array_sum(array_map(static function (array $item): float {
        return (float) ($item['price'] ?? 0);
      }, $materials)),
    ],
  ]);
}

function handleCreateRefund(mysqli $conn, array $adminScope, array $request, int $adminId): void
{
  $sourceRefId = trim((string) ($request['source_ref_id'] ?? $request['source_ref'] ?? $request['ref_id'] ?? ''));
  if ($sourceRefId === '') {
    badRequest('source_ref_id is required.');
  }

  $studentEmail = normalizeEmail($request['student_email'] ?? $request['email'] ?? null);
  $selectedMaterialIds = normalizePositiveIntArray($request['material_ids'] ?? []);
  if ($selectedMaterialIds === []) {
    badRequest('Select at least one material to refund.');
  }

  $reason = trim((string) ($request['reason'] ?? ''));
  if ($reason === '') {
    badRequest('reason is required.');
  }
  if (strlen($reason) > 2000) {
    badRequest('reason cannot exceed 2000 characters.');
  }

  $lockName = 'cc_refund_create_' . md5($sourceRefId);
  $lockResult = acquireNamedLock($conn, $lockName, 10);
  if ($lockResult !== 1) {
    respond(409, [
      'status' => 'failed',
      'message' => 'Another refund creation is in progress for this source ref_id. Please retry.',
    ]);
  }

  $httpStatus = 500;
  $responsePayload = [
    'status' => 'failed',
    'message' => 'Failed to create refund.',
  ];
  $transactionStarted = false;

  try {
    $transactionRows = dbFetchAll(
      $conn,
      "SELECT
         t.id,
         t.ref_id,
         t.user_id,
         t.status,
         u.school AS user_school
       FROM transactions t
       LEFT JOIN users u ON u.id = t.user_id
       WHERE t.ref_id = ?
       LIMIT 1",
      's',
      [$sourceRefId]
    );

    if ($transactionRows === []) {
      $httpStatus = 404;
      $responsePayload = [
        'status' => 'failed',
        'message' => 'Source transaction not found.',
      ];
    } else {
      $studentRows = dbFetchAll(
        $conn,
        'SELECT id, school, first_name, last_name, email FROM users WHERE email = ? LIMIT 1',
        's',
        [$studentEmail]
      );

      if ($studentRows === []) {
        $httpStatus = 404;
        $responsePayload = [
          'status' => 'failed',
          'message' => 'No student found for the provided email.',
        ];
      } else {
        $student = $studentRows[0];
        $studentId = (int) ($student['id'] ?? 0);
        $studentSchool = isset($student['school']) ? (int) $student['school'] : 0;
        if ($studentSchool <= 0) {
          $httpStatus = 409;
          $responsePayload = [
            'status' => 'failed',
            'message' => 'Student does not have a valid school assignment.',
          ];
        } else {
          $schoolId = $studentSchool;
          enforceSchoolScope($adminScope, $schoolId);
          $sourceTransaction = $transactionRows[0];
          $sourceStatus = strtolower((string) ($sourceTransaction['status'] ?? ''));
          if (!in_array($sourceStatus, ['successful', 'success'], true)) {
            $httpStatus = 400;
            $responsePayload = [
              'status' => 'failed',
              'message' => 'Source transaction must be successful before creating a refund.',
            ];
          } else {
            $transactionUserId = isset($sourceTransaction['user_id']) ? (int) $sourceTransaction['user_id'] : 0;
            if ($transactionUserId !== $studentId) {
              $httpStatus = 409;
              $responsePayload = [
                'status' => 'failed',
                'message' => 'Source transaction does not belong to the selected student.',
              ];
            } else {
              $refundableMaterials = getRefundableMaterialsForRef($conn, $sourceRefId, $studentId);
              if ($refundableMaterials === []) {
                $httpStatus = 409;
                $responsePayload = [
                  'status' => 'failed',
                  'message' => 'No refundable materials found for this source transaction.',
                ];
              } else {
                $materialsByBoughtId = [];
                foreach ($refundableMaterials as $material) {
                  $boughtId = isset($material['bought_id']) ? (int) $material['bought_id'] : 0;
                  if ($boughtId > 0) {
                    $materialsByBoughtId[$boughtId] = $material;
                  }
                }

                $selectedMaterials = [];
                foreach ($selectedMaterialIds as $materialBoughtId) {
                  if (!isset($materialsByBoughtId[$materialBoughtId])) {
                    $httpStatus = 400;
                    $responsePayload = [
                      'status' => 'failed',
                      'message' => 'One or more selected materials are invalid for this transaction.',
                    ];
                    break;
                  }

                  $selectedMaterial = $materialsByBoughtId[$materialBoughtId];
                  $selectedMaterialSchool = isset($selectedMaterial['school_id']) ? (int) $selectedMaterial['school_id'] : 0;
                  if ($selectedMaterialSchool > 0 && $selectedMaterialSchool !== $schoolId) {
                    $httpStatus = 409;
                    $responsePayload = [
                      'status' => 'failed',
                      'message' => 'Selected materials do not match the student school.',
                    ];
                    break;
                  }

                  $selectedMaterials[] = $selectedMaterial;
                }

                if ($httpStatus === 500) {
                  $amount = 0.0;
                  foreach ($selectedMaterials as $selectedMaterial) {
                    $amount += (float) ($selectedMaterial['price'] ?? 0);
                  }

                  if ($amount <= 0) {
                    $httpStatus = 400;
                    $responsePayload = [
                      'status' => 'failed',
                      'message' => 'Selected materials produce a zero refund amount.',
                    ];
                  } else {
                    mysqli_begin_transaction($conn);
                    $transactionStarted = true;

                    $existingRows = dbFetchAll(
                      $conn,
                      "SELECT id, status, amount, remaining_amount, created_at
                       FROM refunds
                       WHERE school_id = ?
                         AND ref_id = ?
                         AND status <> 'cancelled'
                       ORDER BY id DESC
                       LIMIT 1",
                      'is',
                      [$schoolId, $sourceRefId]
                    );

                    if ($existingRows !== []) {
                      mysqli_rollback($conn);
                      $transactionStarted = false;
                      $httpStatus = 409;
                      $responsePayload = [
                        'status' => 'failed',
                        'message' => 'A non-cancelled refund already exists for this source transaction for the student school.',
                        'existing_refund' => normalizeNumericRow($existingRows[0], ['amount', 'remaining_amount']),
                      ];
                    } else {
                      $materialsJson = json_encode(array_values($selectedMaterialIds), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                      if ($materialsJson === false) {
                        throw new RuntimeException('Unable to encode selected materials payload.');
                      }

                      $hasMaterialsColumn = refundsHasMaterialsColumn($conn);
                      if ($hasMaterialsColumn) {
                        $stmt = dbExecute(
                          $conn,
                          "INSERT INTO refunds
                            (school_id, student_id, ref_id, amount, remaining_amount, status, reason, materials, created_at, updated_at)
                           VALUES
                            (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())",
                          'iisddss',
                          [$schoolId, $studentId, $sourceRefId, $amount, $amount, $reason, $materialsJson]
                        );
                      } else {
                        $stmt = dbExecute(
                          $conn,
                          "INSERT INTO refunds
                            (school_id, student_id, ref_id, amount, remaining_amount, status, reason, created_at, updated_at)
                           VALUES
                            (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
                          'iisdds',
                          [$schoolId, $studentId, $sourceRefId, $amount, $amount, $reason]
                        );
                      }
                      mysqli_stmt_close($stmt);

                      $refundId = (int) mysqli_insert_id($conn);
                      mysqli_commit($conn);
                      $transactionStarted = false;

                      if ($hasMaterialsColumn) {
                        $createdRows = dbFetchAll(
                          $conn,
                          "SELECT id, school_id, student_id, ref_id, amount, remaining_amount, status, reason, materials, created_at, updated_at
                           FROM refunds
                           WHERE id = ?
                           LIMIT 1",
                          'i',
                          [$refundId]
                        );
                      } else {
                        $createdRows = dbFetchAll(
                          $conn,
                          "SELECT id, school_id, student_id, ref_id, amount, remaining_amount, status, reason, created_at, updated_at
                           FROM refunds
                           WHERE id = ?
                           LIMIT 1",
                          'i',
                          [$refundId]
                        );
                      }

                      $createdRefund = $createdRows[0] ?? [
                        'id' => $refundId,
                        'school_id' => $schoolId,
                        'student_id' => $studentId,
                        'ref_id' => $sourceRefId,
                        'amount' => $amount,
                        'remaining_amount' => $amount,
                        'status' => 'pending',
                        'reason' => $reason,
                        'materials' => $materialsJson,
                      ];

                      if (function_exists('log_audit_event')) {
                        log_audit_event($conn, $adminId, 'create', 'refund', (string) $refundId, [
                          'after' => $createdRefund,
                          'former' => null,
                          'source_ref_id' => $sourceRefId,
                          'student_email' => $studentEmail,
                          'selected_materials_count' => count($selectedMaterialIds),
                        ]);
                      }

                      $httpStatus = 201;
                      $responsePayload = [
                        'status' => 'success',
                        'message' => 'Refund created successfully.',
                        'refund' => normalizeNumericRow($createdRefund, ['amount', 'remaining_amount']),
                      ];
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  } catch (Throwable $throwable) {
    if ($transactionStarted) {
      mysqli_rollback($conn);
    }

    $httpStatus = 500;
    $responsePayload = [
      'status' => 'failed',
      'message' => 'Failed to create refund.',
      'error' => $throwable->getMessage(),
    ];
  } finally {
    releaseNamedLock($conn, $lockName);
  }

  respond($httpStatus, $responsePayload);
}

function handleCancelRefund(mysqli $conn, array $adminScope, array $request, int $adminId): void
{
  $refundId = toPositiveIntOrNull($request['refund_id'] ?? $request['id'] ?? null);
  if ($refundId === null) {
    badRequest('refund_id is required.');
  }

  $cancelReason = trim((string) ($request['cancel_reason'] ?? $request['reason'] ?? ''));
  if ($cancelReason === '') {
    badRequest('cancel_reason is required.');
  }
  if (strlen($cancelReason) > 2000) {
    badRequest('cancel_reason cannot exceed 2000 characters.');
  }

  mysqli_begin_transaction($conn);
  try {
    $refundRows = dbFetchAll(
      $conn,
      'SELECT id, school_id, student_id, ref_id, amount, remaining_amount, status, reason, created_at, updated_at FROM refunds WHERE id = ? FOR UPDATE',
      'i',
      [$refundId]
    );

    if ($refundRows === []) {
      mysqli_rollback($conn);
      respond(404, [
        'status' => 'failed',
        'message' => 'Refund not found.',
      ]);
    }

    $refund = $refundRows[0];
    enforceSchoolScope($adminScope, (int) $refund['school_id']);

    if (strtolower((string) ($refund['status'] ?? '')) === 'cancelled') {
      mysqli_commit($conn);
      respond(200, [
        'status' => 'success',
        'message' => 'Refund is already cancelled.',
        'refund' => normalizeNumericRow($refund, ['amount', 'remaining_amount']),
      ]);
    }

    $activeReservationRows = dbFetchAll(
      $conn,
      "SELECT COUNT(*) AS total_reserved
       FROM refund_reservations
       WHERE refund_id = ? AND status = 'reserved'",
      'i',
      [$refundId]
    );

    $activeReservedCount = isset($activeReservationRows[0]['total_reserved'])
      ? (int) $activeReservationRows[0]['total_reserved']
      : 0;

    if ($activeReservedCount > 0) {
      mysqli_rollback($conn);
      respond(409, [
        'status' => 'failed',
        'message' => 'Refund cannot be cancelled while active reserved rows exist.',
        'active_reserved_rows' => $activeReservedCount,
      ]);
    }

    $stmt = dbExecute(
      $conn,
      "UPDATE refunds SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
      'i',
      [$refundId]
    );
    mysqli_stmt_close($stmt);

    $updatedRows = dbFetchAll(
      $conn,
      'SELECT id, school_id, student_id, ref_id, amount, remaining_amount, status, reason, created_at, updated_at FROM refunds WHERE id = ? LIMIT 1',
      'i',
      [$refundId]
    );
    $updatedRefund = $updatedRows[0] ?? $refund;

    if (function_exists('log_audit_event')) {
      log_audit_event($conn, $adminId, 'cancel', 'refund', (string) $refundId, [
        'former' => $refund,
        'after' => $updatedRefund,
        'cancel_reason' => $cancelReason,
        'cancelled_at' => date('Y-m-d H:i:s'),
      ]);
    }

    mysqli_commit($conn);

    respond(200, [
      'status' => 'success',
      'message' => 'Refund cancelled successfully.',
      'refund' => normalizeNumericRow($updatedRefund, ['amount', 'remaining_amount']),
    ]);
  } catch (Throwable $throwable) {
    mysqli_rollback($conn);
    respond(500, [
      'status' => 'failed',
      'message' => 'Failed to cancel refund.',
      'error' => $throwable->getMessage(),
    ]);
  }
}

function getAdminScope(mysqli $conn, int $adminRole, int $adminId): array
{
  $scope = [
    'admin_id' => $adminId,
    'role' => $adminRole,
    'school_id' => null,
  ];

  if ($adminRole === 5) {
    $rows = dbFetchAll($conn, 'SELECT school FROM admins WHERE id = ? LIMIT 1', 'i', [$adminId]);
    if ($rows === []) {
      respond(403, [
        'status' => 'failed',
        'message' => 'Unable to resolve admin scope.',
      ]);
    }

    $school = isset($rows[0]['school']) ? (int) $rows[0]['school'] : 0;
    if ($school <= 0) {
      respond(403, [
        'status' => 'failed',
        'message' => 'School admin does not have a valid school assignment.',
      ]);
    }

    $scope['school_id'] = $school;
  }

  return $scope;
}

function enforceSchoolScope(array $adminScope, int $schoolId): void
{
  if ($adminScope['school_id'] === null) {
    return;
  }

  if ($schoolId !== (int) $adminScope['school_id']) {
    respond(403, [
      'status' => 'failed',
      'message' => 'You are not allowed to access this school record.',
    ]);
  }
}

function refreshRefundStatuses(mysqli $conn): void
{
  mysqli_query(
    $conn,
    "UPDATE refunds
     SET status = CASE
       WHEN COALESCE(remaining_amount, 0) <= 0 THEN 'applied'
       WHEN COALESCE(remaining_amount, 0) < COALESCE(amount, 0) THEN 'partially_applied'
       ELSE 'pending'
     END
     WHERE status IN ('pending', 'partially_applied', 'applied')"
  );
}

function acquireNamedLock(mysqli $conn, string $lockName, int $timeoutSeconds): int
{
  $rows = dbFetchAll($conn, 'SELECT GET_LOCK(?, ?) AS lock_state', 'si', [$lockName, $timeoutSeconds]);
  if ($rows === []) {
    return 0;
  }

  return isset($rows[0]['lock_state']) ? (int) $rows[0]['lock_state'] : 0;
}

function releaseNamedLock(mysqli $conn, string $lockName): void
{
  dbFetchAll($conn, 'SELECT RELEASE_LOCK(?) AS release_state', 's', [$lockName]);
}

function getRequestPayload(): array
{
  $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
  if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
      return $_GET;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      badRequest('Invalid JSON payload.');
    }

    return array_merge($_GET, $decoded);
  }

  if (!empty($_POST)) {
    return array_merge($_GET, $_POST);
  }

  return $_GET;
}

function dbFetchAll(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    throw new RuntimeException('Failed to prepare statement: ' . mysqli_error($conn));
  }

  if ($types !== '') {
    bindStatementParams($stmt, $types, $params);
  }

  if (!mysqli_stmt_execute($stmt)) {
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    throw new RuntimeException('Failed to execute query: ' . $error);
  }

  $result = mysqli_stmt_get_result($stmt);
  $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
  mysqli_stmt_close($stmt);

  return $rows;
}

function dbExecute(mysqli $conn, string $sql, string $types, array $params): mysqli_stmt
{
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    throw new RuntimeException('Failed to prepare statement: ' . mysqli_error($conn));
  }

  if ($types !== '') {
    bindStatementParams($stmt, $types, $params);
  }

  if (!mysqli_stmt_execute($stmt)) {
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    throw new RuntimeException('Failed to execute statement: ' . $error);
  }

  return $stmt;
}

function bindStatementParams(mysqli_stmt $stmt, string $types, array $params): void
{
  if ($types === '') {
    return;
  }

  if (strlen($types) !== count($params)) {
    throw new RuntimeException('Parameter type count does not match parameter values.');
  }

  $bindArgs = [$types];
  foreach ($params as $index => $value) {
    $bindArgs[] = &$params[$index];
  }

  $bound = call_user_func_array([$stmt, 'bind_param'], $bindArgs);
  if ($bound === false) {
    throw new RuntimeException('Failed to bind query parameters.');
  }
}

function normalizeEmail($value): string
{
  $email = trim((string) ($value ?? ''));
  if ($email === '') {
    badRequest('student_email is required.');
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    badRequest('student_email must be a valid email address.');
  }

  return strtolower($email);
}

function normalizePositiveIntArray($value): array
{
  if ($value === null || $value === '') {
    return [];
  }

  $rawItems = [];
  if (is_array($value)) {
    $rawItems = $value;
  } else {
    $rawItems = explode(',', (string) $value);
  }

  $normalized = [];
  foreach ($rawItems as $item) {
    $trimmed = trim((string) $item);
    if ($trimmed === '') {
      continue;
    }

    $validated = filter_var($trimmed, FILTER_VALIDATE_INT);
    if ($validated === false || (int) $validated <= 0) {
      badRequest('material_ids must contain only positive integers.');
    }

    $normalized[(int) $validated] = (int) $validated;
  }

  return array_values($normalized);
}

function getRefundableMaterialsForRef(mysqli $conn, string $sourceRefId, int $studentId): array
{
  return dbFetchAll(
    $conn,
    "SELECT
       mb.id AS bought_id,
       mb.manual_id,
       mb.price,
       mb.school_id,
       m.title,
       m.course_code,
       m.code
     FROM manuals_bought mb
     LEFT JOIN manuals m ON m.id = mb.manual_id
     WHERE mb.ref_id = ?
       AND mb.buyer = ?
       AND mb.status = 'successful'
     ORDER BY mb.id ASC",
    'si',
    [$sourceRefId, $studentId]
  );
}

function refundsHasMaterialsColumn(mysqli $conn): bool
{
  static $checked = false;
  static $hasColumn = false;

  if ($checked) {
    return $hasColumn;
  }

  $rows = dbFetchAll($conn, "SHOW COLUMNS FROM refunds LIKE 'materials'");
  $hasColumn = $rows !== [];
  $checked = true;

  return $hasColumn;
}

function normalizeAmount($value): float
{
  if ($value === null || $value === '') {
    return 0.0;
  }

  if (!is_numeric($value)) {
    badRequest('amount must be numeric.');
  }

  return round((float) $value, 2);
}

function normalizeDateOrNull(string $date): ?string
{
  $date = trim($date);
  if ($date === '') {
    return null;
  }

  $dt = DateTime::createFromFormat('Y-m-d', $date);
  if (!$dt || $dt->format('Y-m-d') !== $date) {
    badRequest('Date filters must use Y-m-d format.');
  }

  return $date;
}

function toPositiveIntOrNull($value): ?int
{
  if ($value === null || $value === '') {
    return null;
  }

  $validated = filter_var($value, FILTER_VALIDATE_INT);
  if ($validated === false || (int) $validated <= 0) {
    badRequest('Expected a positive integer value.');
  }

  return (int) $validated;
}

function normalizeNumericRows(array $rows, array $keys): array
{
  $normalized = [];
  foreach ($rows as $row) {
    $normalized[] = normalizeNumericRow($row, $keys);
  }

  return $normalized;
}

function normalizeNumericRow(array $row, array $keys): array
{
  foreach ($keys as $key) {
    if (array_key_exists($key, $row) && $row[$key] !== null) {
      $row[$key] = (float) $row[$key];
    }
  }

  return $row;
}

function methodNotAllowed(string $allowed): void
{
  respond(405, [
    'status' => 'failed',
    'message' => 'Method not allowed. Allowed: ' . $allowed,
  ]);
}

function badRequest(string $message): void
{
  respond(400, [
    'status' => 'failed',
    'message' => $message,
  ]);
}

function respond(int $statusCode, array $payload): void
{
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
