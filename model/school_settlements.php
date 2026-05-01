<?php
session_start();

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/school_settlement_service.php');

header('Content-Type: application/json');

$adminId = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;
$adminRole = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;

if ($adminId <= 0) {
  http_response_code(401);
  echo json_encode([
    'status' => 'unauthorized',
    'message' => 'Sign in to continue.',
  ]);
  exit;
}

if (!ccSchoolSettlementAdminAllowed($adminRole)) {
  http_response_code(403);
  echo json_encode([
    'status' => 'forbidden',
    'message' => 'Only admin roles 1, 2, and 4 can manage school settlements.',
  ]);
  exit;
}

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? 'snapshot'));

function schoolSettlementAuditPayload(array $batch, array $extra = []): array
{
  return array_merge([
    'batch_id' => (int) ($batch['id'] ?? 0),
    'school_id' => (int) ($batch['school_id'] ?? 0),
    'batch_reference' => (string) ($batch['batch_reference'] ?? ''),
    'status' => (string) ($batch['status'] ?? ''),
    'total_amount' => (int) ($batch['total_amount'] ?? 0),
    'total_records' => (int) ($batch['total_records'] ?? 0),
    'transfer_provider' => (string) ($batch['transfer_provider'] ?? ''),
    'provider_reference' => (string) ($batch['provider_reference'] ?? ''),
  ], $extra);
}

try {
  switch ($action) {
    case 'snapshot':
      $schoolId = (int) ($_GET['school_id'] ?? $_POST['school_id'] ?? 0);
      echo json_encode(ccSchoolSettlementGetSnapshot($conn, $schoolId), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      break;

    case 'stage_batch':
      $schoolId = (int) ($_POST['school_id'] ?? 0);
      $scheduledFor = (string) ($_POST['scheduled_for'] ?? date('Y-m-d'));
      $notes = (string) ($_POST['notes'] ?? '');
      $result = ccSchoolSettlementStageBatch($conn, $schoolId, $scheduledFor, $adminId, $adminRole, $notes);
      if (($result['status'] ?? '') === 'success' && function_exists('log_audit_event')) {
        $batch = $result['batch'] ?? [];
        log_audit_event(
          $conn,
          $adminId,
          'create',
          'school_settlement_batch',
          (string) ((int) ($batch['id'] ?? 0)),
          schoolSettlementAuditPayload($batch, [
            'source' => 'cc_dashboard',
            'notes' => $notes,
          ])
        );
      }
      echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      break;

    case 'complete_batch':
      $batchId = (int) ($_POST['batch_id'] ?? 0);
      $providerReference = (string) ($_POST['provider_reference'] ?? '');
      $notes = (string) ($_POST['notes'] ?? '');
      $result = ccSchoolSettlementCompleteBatch($conn, $batchId, $providerReference, $adminId, $adminRole, $notes);
      if (($result['status'] ?? '') === 'success' && function_exists('log_audit_event')) {
        $batch = $result['batch'] ?? [];
        log_audit_event(
          $conn,
          $adminId,
          'complete',
          'school_settlement_batch',
          (string) ((int) ($batch['id'] ?? 0)),
          schoolSettlementAuditPayload($batch, [
            'source' => 'cc_dashboard',
            'notes' => $notes,
          ])
        );
      }
      echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      break;

    case 'fail_batch':
      $batchId = (int) ($_POST['batch_id'] ?? 0);
      $reason = (string) ($_POST['reason'] ?? '');
      $result = ccSchoolSettlementFailBatch($conn, $batchId, $adminId, $adminRole, $reason);
      if (($result['status'] ?? '') === 'success' && function_exists('log_audit_event')) {
        $batch = $result['batch'] ?? [];
        log_audit_event(
          $conn,
          $adminId,
          'fail',
          'school_settlement_batch',
          (string) ((int) ($batch['id'] ?? 0)),
          schoolSettlementAuditPayload($batch, [
            'source' => 'cc_dashboard',
            'reason' => $reason,
          ])
        );
      }
      echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      break;

    default:
      http_response_code(400);
      echo json_encode([
        'status' => 'invalid_action',
        'message' => 'Unsupported settlement action.',
      ]);
      break;
  }
} catch (Throwable $error) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => $error->getMessage(),
  ]);
}