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

  $reservationAggJoin = "LEFT JOIN (
      SELECT
        rr.refund_id,
        COALESCE(SUM(CASE WHEN rr.status = 'consumed' THEN rr.amount ELSE 0 END), 0) AS total_consumed,
        COALESCE(SUM(CASE WHEN rr.status = 'reserved' THEN rr.amount ELSE 0 END), 0) AS total_reserved
      FROM refund_reservations rr
      GROUP BY rr.refund_id
    ) rr_agg ON rr_agg.refund_id = r.id";
  $consumedExpr = 'COALESCE(rr_agg.total_consumed, 0)';
  $reservedExpr = 'COALESCE(rr_agg.total_reserved, 0)';
  $remainingExpr = "GREATEST(COALESCE(r.amount, 0) - {$consumedExpr} - {$reservedExpr}, 0)";
  $statusExpr = "CASE
      WHEN r.status = 'cancelled' THEN 'cancelled'
      WHEN {$remainingExpr} <= 0 AND {$reservedExpr} <= 0 AND {$consumedExpr} >= COALESCE(r.amount, 0) THEN 'applied'
      WHEN {$remainingExpr} >= COALESCE(r.amount, 0) THEN 'pending'
      ELSE 'partially_applied'
    END";

  $where = [];
  $types = '';
  $params = [];

  if ($schoolId !== null) {
    $where[] = 'r.school_id = ?';
    $types .= 'i';
    $params[] = $schoolId;
  }

  if ($status !== '') {
    $where[] = "{$statusExpr} = ?";
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
    "SELECT COUNT(*) AS total_rows
     FROM refunds r
     {$reservationAggJoin}
     {$whereSql}",
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
       {$remainingExpr} AS remaining_amount,
       {$consumedExpr} AS consumed_amount,
       {$statusExpr} AS status,
       r.reason,
       r.created_at,
       r.updated_at
     FROM refunds r
     {$reservationAggJoin}
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

  $consumedExpr = 'COALESCE(rr_agg.total_consumed, 0)';
  $reservedExpr = 'COALESCE(rr_agg.total_reserved, 0)';
  $remainingExpr = "GREATEST(COALESCE(r.amount, 0) - {$consumedExpr} - {$reservedExpr}, 0)";
  $statusExpr = "CASE
      WHEN r.status = 'cancelled' THEN 'cancelled'
      WHEN {$remainingExpr} <= 0 AND {$reservedExpr} <= 0 AND {$consumedExpr} >= COALESCE(r.amount, 0) THEN 'applied'
      WHEN {$remainingExpr} >= COALESCE(r.amount, 0) THEN 'pending'
      ELSE 'partially_applied'
    END";

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
       {$remainingExpr} AS remaining_amount,
       {$consumedExpr} AS consumed_amount,
       {$statusExpr} AS status,
       r.reason,
       r.created_at,
       r.updated_at
     FROM refunds r
     LEFT JOIN (
       SELECT
         rr.refund_id,
         COALESCE(SUM(CASE WHEN rr.status = 'consumed' THEN rr.amount ELSE 0 END), 0) AS total_consumed,
         COALESCE(SUM(CASE WHEN rr.status = 'reserved' THEN rr.amount ELSE 0 END), 0) AS total_reserved
       FROM refund_reservations rr
       GROUP BY rr.refund_id
     ) rr_agg ON rr_agg.refund_id = r.id
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
  $totals['outstanding'] = max((float) ($refund['remaining_amount'] ?? 0), 0.0);

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

  $reservationAggJoin = "LEFT JOIN (
      SELECT
        rr.refund_id,
        COALESCE(SUM(CASE WHEN rr.status = 'consumed' THEN rr.amount ELSE 0 END), 0) AS total_consumed,
        COALESCE(SUM(CASE WHEN rr.status = 'reserved' THEN rr.amount ELSE 0 END), 0) AS total_reserved
      FROM refund_reservations rr
      GROUP BY rr.refund_id
    ) rr_agg ON rr_agg.refund_id = r.id";
  $consumedExpr = 'COALESCE(rr_agg.total_consumed, 0)';
  $reservedExpr = 'COALESCE(rr_agg.total_reserved, 0)';
  $remainingExpr = "GREATEST(COALESCE(r.amount, 0) - {$consumedExpr} - {$reservedExpr}, 0)";

  $where = ["r.status <> 'cancelled'", "{$remainingExpr} > 0"];
  $refundedWhere = ["r.status <> 'cancelled'"];
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
       COALESCE(SUM({$remainingExpr}), 0) AS outstanding_amount,
       COUNT(*) AS refunds_count
     FROM refunds r
     {$reservationAggJoin}
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
       COALESCE(SUM({$consumedExpr}), 0) AS total_refunded
     FROM refunds r
     {$reservationAggJoin}
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
  $preflightDetails = null;
  $reallocationLogs = [];
  $refundStateChanges = [];
  $transactionCapLogs = [];
  $affectedSourceRefs = [$sourceRefId => true];
  $affectedRefundIds = [];
  $postflightWarnings = [];
  $createdRefund = null;

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
                $materialsByManualId = [];
                foreach ($refundableMaterials as $material) {
                  $manualId = isset($material['manual_id']) ? (int) $material['manual_id'] : 0;
                  if ($manualId > 0) {
                    $materialsByManualId[$manualId] = $material;
                  }
                }

                $selectedMaterials = [];
                foreach ($selectedMaterialIds as $selectedManualId) {
                  if (!isset($materialsByManualId[$selectedManualId])) {
                    $httpStatus = 400;
                    $responsePayload = [
                      'status' => 'failed',
                      'message' => 'One or more selected materials are invalid for this transaction.',
                    ];
                    break;
                  }

                  $selectedMaterial = $materialsByManualId[$selectedManualId];
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

                    $preflightDetails = reconcileSourceRefWithFallback($conn, $sourceRefId);
                    if (isset($preflightDetails['affected_source_refs']) && is_array($preflightDetails['affected_source_refs'])) {
                      foreach ($preflightDetails['affected_source_refs'] as $affectedSourceRef) {
                        $affectedSourceRef = trim((string) $affectedSourceRef);
                        if ($affectedSourceRef !== '') {
                          $affectedSourceRefs[$affectedSourceRef] = true;
                        }
                      }
                    }
                    if (isset($preflightDetails['affected_refund_ids']) && is_array($preflightDetails['affected_refund_ids'])) {
                      foreach ($preflightDetails['affected_refund_ids'] as $affectedRefundId) {
                        $affectedRefundId = (int) $affectedRefundId;
                        if ($affectedRefundId > 0) {
                          $affectedRefundIds[$affectedRefundId] = true;
                        }
                      }
                    }

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
                      $manualIdsExist = validateManualIdsExist($conn, $selectedMaterialIds);
                      if (!$manualIdsExist) {
                        throw new RuntimeException('One or more selected materials do not exist.');
                      }

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
                      $affectedRefundIds[$refundId] = true;

                      $targetTotals = getRefundLedgerTotals($conn, $refundId);
                      $targetNeeded = max(
                        0.0,
                        round(
                          $amount
                          - (float) ($targetTotals['consumed_total'] ?? 0)
                          - (float) ($targetTotals['reserved_total'] ?? 0),
                          2
                        )
                      );

                      if ($targetNeeded > 0) {
                        $nextTargetSplitSequence = getNextRefundSplitSequence($conn, $refundId);
                        $nextSourceSplitSequenceByRefund = [];
                        $overlyRows = dbFetchAll(
                          $conn,
                          "SELECT
                             id,
                             refund_id,
                             ref_id,
                             split_sequence,
                             school_id,
                             payer_user_id,
                             gateway,
                             amount,
                             channel,
                             reserved_at,
                             consumed_at,
                             released_at
                           FROM refund_reservations
                           WHERE status = 'released'
                             AND release_reason = 'overly'
                             AND school_id = ?
                             AND refund_id <> ?
                             AND amount > 0
                           ORDER BY COALESCE(released_at, consumed_at, reserved_at) ASC, id ASC
                           FOR UPDATE",
                          'ii',
                          [$schoolId, $refundId]
                        );

                        foreach ($overlyRows as $sourceRow) {
                          if ($targetNeeded <= 0) {
                            break;
                          }

                          $sourceRowId = (int) ($sourceRow['id'] ?? 0);
                          $sourceRefundId = (int) ($sourceRow['refund_id'] ?? 0);
                          $sourceRowAmount = round((float) ($sourceRow['amount'] ?? 0), 2);
                          if ($sourceRowId <= 0 || $sourceRefundId <= 0 || $sourceRowAmount <= 0) {
                            continue;
                          }

                          $movedAmount = round(min($targetNeeded, $sourceRowAmount), 2);
                          if ($movedAmount <= 0) {
                            continue;
                          }

                          $currentTimestamp = date('Y-m-d H:i:s');
                          insertRefundReservationRow($conn, [
                            'refund_id' => $refundId,
                            'ref_id' => (string) ($sourceRow['ref_id'] ?? ''),
                            'split_sequence' => $nextTargetSplitSequence,
                            'school_id' => $schoolId,
                            'payer_user_id' => (int) ($sourceRow['payer_user_id'] ?? 0),
                            'gateway' => (string) ($sourceRow['gateway'] ?? ''),
                            'amount' => $movedAmount,
                            'channel' => (string) ($sourceRow['channel'] ?? ''),
                            'status' => 'consumed',
                            'reserved_at' => $currentTimestamp,
                            'consumed_at' => $currentTimestamp,
                            'released_at' => null,
                            'release_reason' => null,
                          ]);
                          $nextTargetSplitSequence++;

                          $allocationPath = 'full';
                          if ($movedAmount >= $sourceRowAmount - 0.00001) {
                            $sourceUpdateStmt = dbExecute(
                              $conn,
                              "UPDATE refund_reservations
                               SET release_reason = 'overly_reallocated'
                               WHERE id = ?",
                              'i',
                              [$sourceRowId]
                            );
                            mysqli_stmt_close($sourceUpdateStmt);
                          } else {
                            $allocationPath = 'partial';
                            $leftoverAmount = round($sourceRowAmount - $movedAmount, 2);
                            if ($leftoverAmount < 0) {
                              $leftoverAmount = 0;
                            }

                            $sourceUpdateStmt = dbExecute(
                              $conn,
                              "UPDATE refund_reservations
                               SET amount = ?, release_reason = 'overly'
                               WHERE id = ?",
                              'di',
                              [$leftoverAmount, $sourceRowId]
                            );
                            mysqli_stmt_close($sourceUpdateStmt);

                            insertRefundReservationRow($conn, [
                              'refund_id' => $sourceRefundId,
                              'ref_id' => (string) ($sourceRow['ref_id'] ?? ''),
                              'split_sequence' => getNextSplitSequenceCursor($conn, $sourceRefundId, $nextSourceSplitSequenceByRefund),
                              'school_id' => (int) ($sourceRow['school_id'] ?? $schoolId),
                              'payer_user_id' => (int) ($sourceRow['payer_user_id'] ?? 0),
                              'gateway' => (string) ($sourceRow['gateway'] ?? ''),
                              'amount' => $movedAmount,
                              'channel' => (string) ($sourceRow['channel'] ?? ''),
                              'status' => 'released',
                              'reserved_at' => $sourceRow['reserved_at'] ?? null,
                              'consumed_at' => $sourceRow['consumed_at'] ?? null,
                              'released_at' => $sourceRow['released_at'] ?? $currentTimestamp,
                              'release_reason' => 'overly_reallocated',
                            ]);
                          }

                          $affectedRefundIds[$sourceRefundId] = true;
                          $sourceReservationRef = trim((string) ($sourceRow['ref_id'] ?? ''));
                          if ($sourceReservationRef !== '') {
                            $affectedSourceRefs[$sourceReservationRef] = true;
                          }

                          $targetNeeded = max(0.0, round($targetNeeded - $movedAmount, 2));
                          $reallocationLogs[] = [
                            'target_refund_id' => $refundId,
                            'source_released_row_id' => $sourceRowId,
                            'source_refund_id' => $sourceRefundId,
                            'source_ref_id' => (string) ($sourceRow['ref_id'] ?? ''),
                            'moved_amount' => $movedAmount,
                            'path' => $allocationPath,
                            'actor_admin_id' => $adminId,
                            'moved_at' => $currentTimestamp,
                          ];
                        }
                      }

                      $refundStateChanges = recomputeRefundsFromLedger($conn, array_map('intval', array_keys($affectedRefundIds)));
                      $transactionCapLogs = capTransactionsRefundForSourceRefs($conn, array_keys($affectedSourceRefs));

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

                      mysqli_commit($conn);
                      $transactionStarted = false;

                      foreach (array_keys($affectedSourceRefs) as $affectedSourceRef) {
                        try {
                          reconcileSourceRefWithFallback($conn, $affectedSourceRef);
                        } catch (Throwable $postflightThrowable) {
                          $postflightWarnings[] = [
                            'source_ref_id' => $affectedSourceRef,
                            'error' => $postflightThrowable->getMessage(),
                          ];
                        }
                      }

                      $createdRows = fetchRefundById($conn, $refundId, $hasMaterialsColumn);
                      if ($createdRows !== []) {
                        $createdRefund = $createdRows[0];
                      }

                      if (function_exists('log_audit_event')) {
                        log_audit_event($conn, $adminId, 'create', 'refund', (string) $refundId, [
                          'after' => $createdRefund,
                          'former' => null,
                          'source_ref_id' => $sourceRefId,
                          'student_email' => $studentEmail,
                          'selected_materials_count' => count($selectedMaterialIds),
                          'selected_manual_ids' => array_values($selectedMaterialIds),
                          'preflight' => $preflightDetails,
                          'reallocation' => [
                            'rows' => $reallocationLogs,
                            'moved_total' => array_sum(array_map(static function (array $row): float {
                              return (float) ($row['moved_amount'] ?? 0);
                            }, $reallocationLogs)),
                          ],
                          'affected_refund_state_changes' => $refundStateChanges,
                          'transaction_refund_caps' => $transactionCapLogs,
                          'postflight_warnings' => $postflightWarnings,
                        ]);
                      }

                      foreach ($reallocationLogs as $reallocationLog) {
                        if (!function_exists('log_audit_event')) {
                          break;
                        }
                        log_audit_event(
                          $conn,
                          $adminId,
                          'reallocate',
                          'refund_reservation',
                          (string) ($reallocationLog['source_released_row_id'] ?? ''),
                          $reallocationLog
                        );
                      }

                      $httpStatus = 201;
                      $responsePayload = [
                        'status' => 'success',
                        'message' => 'Refund created successfully.',
                        'refund' => normalizeNumericRow($createdRefund, ['amount', 'remaining_amount']),
                        'reallocation' => [
                          'rows_moved' => count($reallocationLogs),
                          'moved_total' => array_sum(array_map(static function (array $row): float {
                            return (float) ($row['moved_amount'] ?? 0);
                          }, $reallocationLogs)),
                        ],
                        'postflight_warnings' => $postflightWarnings,
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
    "UPDATE refunds r
     LEFT JOIN (
       SELECT
         rr.refund_id,
         COALESCE(SUM(CASE WHEN rr.status = 'consumed' THEN rr.amount ELSE 0 END), 0) AS total_consumed,
         COALESCE(SUM(CASE WHEN rr.status = 'reserved' THEN rr.amount ELSE 0 END), 0) AS total_reserved
       FROM refund_reservations rr
       GROUP BY rr.refund_id
     ) rr_agg ON rr_agg.refund_id = r.id
     SET status = CASE
       WHEN GREATEST(COALESCE(r.amount, 0) - COALESCE(rr_agg.total_consumed, 0) - COALESCE(rr_agg.total_reserved, 0), 0) <= 0
         AND COALESCE(rr_agg.total_reserved, 0) <= 0
         AND COALESCE(rr_agg.total_consumed, 0) >= COALESCE(r.amount, 0)
         THEN 'applied'
       WHEN GREATEST(COALESCE(r.amount, 0) - COALESCE(rr_agg.total_consumed, 0) - COALESCE(rr_agg.total_reserved, 0), 0) >= COALESCE(r.amount, 0) THEN 'pending'
       ELSE 'partially_applied'
     END,
     remaining_amount = GREATEST(COALESCE(r.amount, 0) - COALESCE(rr_agg.total_consumed, 0) - COALESCE(rr_agg.total_reserved, 0), 0)
     WHERE r.status IN ('pending', 'partially_applied', 'applied')"
  );
}

function reconcileSourceRefWithFallback(mysqli $conn, string $sourceRefId): array
{
  $sourceRefId = trim($sourceRefId);
  if ($sourceRefId === '') {
    return [
      'mode' => 'skip',
      'source_ref_id' => '',
      'affected_refund_ids' => [],
      'affected_source_refs' => [],
    ];
  }

  if (callReconcileProcedureIfExists($conn, $sourceRefId)) {
    return [
      'mode' => 'procedure',
      'source_ref_id' => $sourceRefId,
      'affected_refund_ids' => [],
      'affected_source_refs' => [$sourceRefId],
    ];
  }

  if (isConnectionInTransaction($conn)) {
    return inlineReconcileSourceRef($conn, $sourceRefId);
  }

  mysqli_begin_transaction($conn);
  try {
    $details = inlineReconcileSourceRef($conn, $sourceRefId);
    mysqli_commit($conn);
    return $details;
  } catch (Throwable $throwable) {
    mysqli_rollback($conn);
    throw $throwable;
  }
}

function callReconcileProcedureIfExists(mysqli $conn, string $sourceRefId): bool
{
  if (!storedProcedureExists($conn, 'sp_reconcile_source_ref')) {
    return false;
  }

  $stmt = mysqli_prepare($conn, 'CALL sp_reconcile_source_ref(?)');
  if (!$stmt) {
    throw new RuntimeException('Failed to prepare reconcile procedure call: ' . mysqli_error($conn));
  }

  $stmt->bind_param('s', $sourceRefId);
  if (!mysqli_stmt_execute($stmt)) {
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    throw new RuntimeException('Failed to execute reconcile procedure: ' . $error);
  }
  mysqli_stmt_close($stmt);

  // Flush any pending result sets from the procedure call.
  do {
    $result = mysqli_store_result($conn);
    if ($result instanceof mysqli_result) {
      mysqli_free_result($result);
    }
  } while (mysqli_more_results($conn) && mysqli_next_result($conn));

  return true;
}

function storedProcedureExists(mysqli $conn, string $procedureName): bool
{
  static $cache = [];
  $cacheKey = strtolower($procedureName);
  if (array_key_exists($cacheKey, $cache)) {
    return $cache[$cacheKey];
  }

  $rows = dbFetchAll(
    $conn,
    "SELECT COUNT(*) AS total_found
     FROM INFORMATION_SCHEMA.ROUTINES
     WHERE ROUTINE_SCHEMA = DATABASE()
       AND ROUTINE_TYPE = 'PROCEDURE'
       AND ROUTINE_NAME = ?",
    's',
    [$procedureName]
  );

  $exists = isset($rows[0]['total_found']) && (int) $rows[0]['total_found'] > 0;
  $cache[$cacheKey] = $exists;

  return $exists;
}

function isConnectionInTransaction(mysqli $conn): bool
{
  $rows = dbFetchAll($conn, 'SELECT @@in_transaction AS in_transaction');
  if ($rows === []) {
    return false;
  }

  return isset($rows[0]['in_transaction']) && (int) $rows[0]['in_transaction'] === 1;
}

function inlineReconcileSourceRef(mysqli $conn, string $sourceRefId): array
{
  $sourceRefId = trim($sourceRefId);
  if ($sourceRefId === '') {
    return [
      'mode' => 'inline',
      'source_ref_id' => '',
      'affected_refund_ids' => [],
      'affected_source_refs' => [],
      'over_consumed_adjustments' => [],
      'refund_state_changes' => [],
      'transaction_refund_caps' => [],
    ];
  }

  $affectedRefundIds = [];
  $affectedSourceRefs = [$sourceRefId => true];
  $overConsumedAdjustments = [];

  $refundRows = dbFetchAll(
    $conn,
    "SELECT id, amount, status
     FROM refunds
     WHERE ref_id = ?
       AND status <> 'cancelled'
     ORDER BY id ASC
     FOR UPDATE",
    's',
    [$sourceRefId]
  );

  foreach ($refundRows as $refundRow) {
    $refundId = (int) ($refundRow['id'] ?? 0);
    if ($refundId <= 0) {
      continue;
    }

    $refundAmount = round((float) ($refundRow['amount'] ?? 0), 2);
    $ledgerTotals = getRefundLedgerTotals($conn, $refundId);
    $consumedTotal = round((float) ($ledgerTotals['consumed_total'] ?? 0), 2);

    if ($consumedTotal > $refundAmount + 0.00001) {
      $excess = round($consumedTotal - $refundAmount, 2);
      $nextSplitSequence = getNextRefundSplitSequence($conn, $refundId);
      $consumedRows = dbFetchAll(
        $conn,
        "SELECT
           id,
           refund_id,
           ref_id,
           split_sequence,
           school_id,
           payer_user_id,
           gateway,
           amount,
           channel,
           reserved_at,
           consumed_at,
           released_at
         FROM refund_reservations
         WHERE refund_id = ?
           AND status = 'consumed'
         ORDER BY COALESCE(consumed_at, reserved_at) DESC, id DESC
         FOR UPDATE",
        'i',
        [$refundId]
      );

      foreach ($consumedRows as $consumedRow) {
        if ($excess <= 0) {
          break;
        }

        $consumedRowId = (int) ($consumedRow['id'] ?? 0);
        $consumedRowAmount = round((float) ($consumedRow['amount'] ?? 0), 2);
        if ($consumedRowId <= 0 || $consumedRowAmount <= 0) {
          continue;
        }

        $releasedAmount = round(min($excess, $consumedRowAmount), 2);
        $currentTimestamp = date('Y-m-d H:i:s');
        if ($releasedAmount >= $consumedRowAmount - 0.00001) {
          $updateStmt = dbExecute(
            $conn,
            "UPDATE refund_reservations
             SET status = 'released',
                 released_at = NOW(),
                 release_reason = 'overly'
             WHERE id = ?",
            'i',
            [$consumedRowId]
          );
          mysqli_stmt_close($updateStmt);
        } else {
          $leftoverAmount = round($consumedRowAmount - $releasedAmount, 2);
          if ($leftoverAmount < 0) {
            $leftoverAmount = 0;
          }

          $updateStmt = dbExecute(
            $conn,
            "UPDATE refund_reservations
             SET amount = ?
             WHERE id = ?",
            'di',
            [$leftoverAmount, $consumedRowId]
          );
          mysqli_stmt_close($updateStmt);

          insertRefundReservationRow($conn, [
            'refund_id' => $refundId,
            'ref_id' => (string) ($consumedRow['ref_id'] ?? ''),
            'split_sequence' => $nextSplitSequence,
            'school_id' => (int) ($consumedRow['school_id'] ?? 0),
            'payer_user_id' => (int) ($consumedRow['payer_user_id'] ?? 0),
            'gateway' => (string) ($consumedRow['gateway'] ?? ''),
            'amount' => $releasedAmount,
            'channel' => (string) ($consumedRow['channel'] ?? ''),
            'status' => 'released',
            'reserved_at' => $consumedRow['reserved_at'] ?? null,
            'consumed_at' => $consumedRow['consumed_at'] ?? null,
            'released_at' => $currentTimestamp,
            'release_reason' => 'overly',
          ]);
          $nextSplitSequence++;
        }

        $overConsumedAdjustments[] = [
          'refund_id' => $refundId,
          'reservation_row_id' => $consumedRowId,
          'released_amount' => $releasedAmount,
          'reason' => 'overly',
        ];
        $consumedSourceRef = trim((string) ($consumedRow['ref_id'] ?? ''));
        if ($consumedSourceRef !== '') {
          $affectedSourceRefs[$consumedSourceRef] = true;
        }
        $excess = max(0.0, round($excess - $releasedAmount, 2));
      }
    }

    $affectedRefundIds[$refundId] = true;
  }

  $refundStateChanges = recomputeRefundsFromLedger($conn, array_map('intval', array_keys($affectedRefundIds)));
  $transactionRefundCaps = capTransactionsRefundForSourceRefs($conn, array_keys($affectedSourceRefs));

  return [
    'mode' => 'inline',
    'source_ref_id' => $sourceRefId,
    'affected_refund_ids' => array_values(array_map('intval', array_keys($affectedRefundIds))),
    'affected_source_refs' => array_values(array_keys($affectedSourceRefs)),
    'over_consumed_adjustments' => $overConsumedAdjustments,
    'refund_state_changes' => $refundStateChanges,
    'transaction_refund_caps' => $transactionRefundCaps,
  ];
}

function getRefundLedgerTotals(mysqli $conn, int $refundId): array
{
  $totalsRows = dbFetchAll(
    $conn,
    "SELECT
       COALESCE(SUM(CASE WHEN status = 'consumed' THEN amount ELSE 0 END), 0) AS consumed_total,
       COALESCE(SUM(CASE WHEN status = 'reserved' THEN amount ELSE 0 END), 0) AS reserved_total
     FROM refund_reservations
     WHERE refund_id = ?",
    'i',
    [$refundId]
  );

  return $totalsRows[0] ?? [
    'consumed_total' => 0,
    'reserved_total' => 0,
  ];
}

function deriveRefundStatus(float $refundAmount, float $remainingAmount, float $consumedTotal, float $reservedTotal): string
{
  if ($remainingAmount <= 0.00001 && $reservedTotal <= 0.00001 && $consumedTotal + 0.00001 >= $refundAmount) {
    return 'applied';
  }

  if ($remainingAmount + 0.00001 >= $refundAmount) {
    return 'pending';
  }

  return 'partially_applied';
}

function recomputeRefundsFromLedger(mysqli $conn, array $refundIds): array
{
  $changes = [];
  $normalizedRefundIds = array_values(array_unique(array_filter(array_map('intval', $refundIds), static function (int $refundId): bool {
    return $refundId > 0;
  })));

  foreach ($normalizedRefundIds as $refundId) {
    $refundRows = dbFetchAll(
      $conn,
      "SELECT id, amount, remaining_amount, status
       FROM refunds
       WHERE id = ?
       LIMIT 1
       FOR UPDATE",
      'i',
      [$refundId]
    );

    if ($refundRows === []) {
      continue;
    }

    $refund = $refundRows[0];
    $currentStatus = strtolower((string) ($refund['status'] ?? 'pending'));
    if ($currentStatus === 'cancelled') {
      continue;
    }

    $refundAmount = round((float) ($refund['amount'] ?? 0), 2);
    $ledgerTotals = getRefundLedgerTotals($conn, $refundId);
    $consumedTotal = round((float) ($ledgerTotals['consumed_total'] ?? 0), 2);
    $reservedTotal = round((float) ($ledgerTotals['reserved_total'] ?? 0), 2);
    $newRemaining = max(0.0, round($refundAmount - $consumedTotal - $reservedTotal, 2));
    $newStatus = deriveRefundStatus($refundAmount, $newRemaining, $consumedTotal, $reservedTotal);

    $beforeState = [
      'remaining_amount' => (float) ($refund['remaining_amount'] ?? 0),
      'status' => (string) ($refund['status'] ?? 'pending'),
      'consumed_total' => $consumedTotal,
      'reserved_total' => $reservedTotal,
    ];

    $previousRemaining = round((float) ($refund['remaining_amount'] ?? 0), 2);
    $previousStatus = strtolower((string) ($refund['status'] ?? 'pending'));
    if (abs($previousRemaining - $newRemaining) > 0.00001 || $previousStatus !== $newStatus) {
      $updateStmt = dbExecute(
        $conn,
        "UPDATE refunds
         SET remaining_amount = ?, status = ?, updated_at = NOW()
         WHERE id = ?",
        'dsi',
        [$newRemaining, $newStatus, $refundId]
      );
      mysqli_stmt_close($updateStmt);
    }

    $changes[] = [
      'refund_id' => $refundId,
      'before' => $beforeState,
      'after' => [
        'remaining_amount' => $newRemaining,
        'status' => $newStatus,
        'consumed_total' => $consumedTotal,
        'reserved_total' => $reservedTotal,
      ],
    ];
  }

  return $changes;
}

function getNextRefundSplitSequence(mysqli $conn, int $refundId): int
{
  $rows = dbFetchAll(
    $conn,
    "SELECT COALESCE(MAX(split_sequence), 0) AS max_sequence
     FROM refund_reservations
     WHERE refund_id = ?
     FOR UPDATE",
    'i',
    [$refundId]
  );

  $maxSequence = isset($rows[0]['max_sequence']) ? (int) $rows[0]['max_sequence'] : 0;
  return $maxSequence + 1;
}

function getNextSplitSequenceCursor(mysqli $conn, int $refundId, array &$sequenceCursorByRefund): int
{
  if ($refundId <= 0) {
    return 1;
  }

  if (!array_key_exists($refundId, $sequenceCursorByRefund)) {
    $sequenceCursorByRefund[$refundId] = getNextRefundSplitSequence($conn, $refundId);
  }

  $current = (int) $sequenceCursorByRefund[$refundId];
  $sequenceCursorByRefund[$refundId] = $current + 1;
  return $current;
}

function insertRefundReservationRow(mysqli $conn, array $rowData): int
{
  $payload = array_merge([
    'refund_id' => 0,
    'ref_id' => '',
    'split_sequence' => 0,
    'school_id' => 0,
    'payer_user_id' => 0,
    'gateway' => '',
    'amount' => 0,
    'channel' => '',
    'status' => 'released',
    'reserved_at' => null,
    'consumed_at' => null,
    'released_at' => null,
    'release_reason' => null,
  ], $rowData);

  if ((int) $payload['refund_id'] <= 0) {
    throw new RuntimeException('refund_id is required when inserting a refund reservation row.');
  }

  $insertStmt = dbExecute(
    $conn,
    "INSERT INTO refund_reservations
      (refund_id, ref_id, split_sequence, school_id, payer_user_id, gateway, amount, channel, status, reserved_at, consumed_at, released_at, release_reason)
     VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    'isiiisdssssss',
    [
      (int) $payload['refund_id'],
      (string) $payload['ref_id'],
      (int) $payload['split_sequence'],
      (int) $payload['school_id'],
      (int) $payload['payer_user_id'],
      (string) $payload['gateway'],
      round((float) $payload['amount'], 2),
      (string) $payload['channel'],
      (string) $payload['status'],
      $payload['reserved_at'],
      $payload['consumed_at'],
      $payload['released_at'],
      $payload['release_reason'],
    ]
  );
  mysqli_stmt_close($insertStmt);

  return (int) mysqli_insert_id($conn);
}

function transactionsHasRefundColumn(mysqli $conn): bool
{
  static $checked = false;
  static $hasColumn = false;

  if ($checked) {
    return $hasColumn;
  }

  $rows = dbFetchAll($conn, "SHOW COLUMNS FROM transactions LIKE 'refund'");
  $hasColumn = $rows !== [];
  $checked = true;

  return $hasColumn;
}

function capTransactionsRefundForSourceRefs(mysqli $conn, array $sourceRefs): array
{
  if (!transactionsHasRefundColumn($conn)) {
    return [];
  }

  $normalizedRefs = array_values(array_unique(array_filter(array_map(static function ($value): string {
    return trim((string) $value);
  }, $sourceRefs), static function (string $value): bool {
    return $value !== '';
  })));

  $changes = [];
  foreach ($normalizedRefs as $sourceRefId) {
    $expectedRows = dbFetchAll(
      $conn,
      "SELECT
         COALESCE(
           SUM(
             LEAST(
               COALESCE(r.amount, 0),
               COALESCE(rr_ref.total_consumed_for_refund, 0)
             )
           ),
           0
         ) AS expected_refund
       FROM refunds r
       INNER JOIN (
         SELECT
           refund_id,
           COALESCE(SUM(amount), 0) AS total_consumed_for_refund
         FROM refund_reservations
         WHERE status = 'consumed'
           AND ref_id = ?
         GROUP BY refund_id
       ) rr_ref ON rr_ref.refund_id = r.id
       WHERE r.status <> 'cancelled'",
      's',
      [$sourceRefId]
    );

    $expectedRefund = isset($expectedRows[0]['expected_refund'])
      ? round((float) $expectedRows[0]['expected_refund'], 2)
      : 0.0;
    if ($expectedRefund < 0) {
      $expectedRefund = 0.0;
    }

    $transactionRows = dbFetchAll(
      $conn,
      "SELECT id, amount, COALESCE(refund, 0) AS refund
       FROM transactions
       WHERE ref_id = ?
       FOR UPDATE",
      's',
      [$sourceRefId]
    );

    foreach ($transactionRows as $transactionRow) {
      $transactionId = (int) ($transactionRow['id'] ?? 0);
      if ($transactionId <= 0) {
        continue;
      }

      $transactionAmount = round((float) ($transactionRow['amount'] ?? 0), 2);
      $currentRefund = round((float) ($transactionRow['refund'] ?? 0), 2);
      $newRefund = max(0.0, min($expectedRefund, $transactionAmount));

      if (abs($newRefund - $currentRefund) <= 0.00001) {
        continue;
      }

      $updateStmt = dbExecute(
        $conn,
        "UPDATE transactions
         SET refund = ?
         WHERE id = ?",
        'di',
        [$newRefund, $transactionId]
      );
      mysqli_stmt_close($updateStmt);

      $changes[] = [
        'source_ref_id' => $sourceRefId,
        'transaction_id' => $transactionId,
        'before_refund' => $currentRefund,
        'after_refund' => $newRefund,
        'expected_refund' => $expectedRefund,
      ];
    }
  }

  return $changes;
}

function validateManualIdsExist(mysqli $conn, array $manualIds): bool
{
  $manualIds = array_values(array_unique(array_filter(array_map('intval', $manualIds), static function (int $manualId): bool {
    return $manualId > 0;
  })));
  if ($manualIds === []) {
    return false;
  }

  $placeholders = implode(',', array_fill(0, count($manualIds), '?'));
  $types = str_repeat('i', count($manualIds));
  $rows = dbFetchAll(
    $conn,
    "SELECT COUNT(DISTINCT id) AS total_found
     FROM manuals
     WHERE id IN ({$placeholders})",
    $types,
    $manualIds
  );

  $totalFound = isset($rows[0]['total_found']) ? (int) $rows[0]['total_found'] : 0;
  return $totalFound === count($manualIds);
}

function fetchRefundById(mysqli $conn, int $refundId, bool $hasMaterialsColumn): array
{
  if ($hasMaterialsColumn) {
    return dbFetchAll(
      $conn,
      "SELECT id, school_id, student_id, ref_id, amount, remaining_amount, status, reason, materials, created_at, updated_at
       FROM refunds
       WHERE id = ?
       LIMIT 1",
      'i',
      [$refundId]
    );
  }

  return dbFetchAll(
    $conn,
    "SELECT id, school_id, student_id, ref_id, amount, remaining_amount, status, reason, created_at, updated_at
     FROM refunds
     WHERE id = ?
     LIMIT 1",
    'i',
    [$refundId]
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
       mb.manual_id,
       COALESCE(SUM(mb.price), 0) AS price,
       MIN(mb.school_id) AS school_id,
       m.title,
       m.course_code,
       m.code
     FROM manuals_bought mb
     INNER JOIN manuals m ON m.id = mb.manual_id
     WHERE mb.ref_id = ?
       AND mb.buyer = ?
       AND mb.status = 'successful'
     GROUP BY mb.manual_id, m.title, m.course_code, m.code
     ORDER BY mb.manual_id ASC",
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
