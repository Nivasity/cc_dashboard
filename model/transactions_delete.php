<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');

header('Content-Type: application/json');

$status = 'failed';
$message = '';

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;

if (!$admin_id || !in_array((int)$admin_role, [1, 2, 4], true)) {
  echo json_encode([
    'status' => 'failed',
    'message' => 'You are not allowed to delete transactions.'
  ]);
  exit;
}

$ref_id = trim($_POST['ref_id'] ?? '');
if ($ref_id === '') {
  echo json_encode([
    'status' => 'failed',
    'message' => 'Invalid transaction reference.'
  ]);
  exit;
}

$tx_stmt = $conn->prepare('SELECT id FROM transactions WHERE ref_id = ? LIMIT 1');
if (!$tx_stmt) {
  echo json_encode([
    'status' => 'failed',
    'message' => 'Unable to prepare transaction lookup.'
  ]);
  exit;
}
$tx_stmt->bind_param('s', $ref_id);
$tx_stmt->execute();
$tx_stmt->bind_result($tx_id);
$has_tx = $tx_stmt->fetch();
$tx_stmt->close();

if (!$has_tx) {
  echo json_encode([
    'status' => 'failed',
    'message' => 'Transaction not found.'
  ]);
  exit;
}

mysqli_begin_transaction($conn);
try {
  $del_mb = $conn->prepare('DELETE FROM manuals_bought WHERE ref_id = ?');
  if ($del_mb) {
    $del_mb->bind_param('s', $ref_id);
    if (!$del_mb->execute()) {
      throw new Exception('Failed to delete related material purchases.');
    }
    $del_mb->close();
  }

  $del_tx = $conn->prepare('DELETE FROM transactions WHERE ref_id = ?');
  if (!$del_tx) {
    throw new Exception('Unable to prepare transaction delete.');
  }
  $del_tx->bind_param('s', $ref_id);
  if (!$del_tx->execute()) {
    throw new Exception('Failed to delete transaction record.');
  }
  $del_tx->close();

  mysqli_commit($conn);
  $status = 'success';
  $message = 'Transaction deleted successfully.';

  log_audit_event($conn, (int)$admin_id, 'delete', 'transaction', (int)$tx_id, [
    'ref_id' => $ref_id
  ]);
} catch (Exception $e) {
  mysqli_rollback($conn);
  $status = 'failed';
  $message = $e->getMessage();
}

echo json_encode([
  'status' => $status,
  'message' => $message
]);
exit;

