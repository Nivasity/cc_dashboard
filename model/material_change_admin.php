<?php
// Material change ("swap") admin override, used from Bella Chats > Change material.
// Students swap materials themselves (or through Bella) within 72 hours, once per purchase.
// Admins can go past those two limits with a reason; every other rule still applies
// (lost or collected copies, same price, department visibility). Logged in
// manual_change_overrides and the audit log.
//   POST action=purchases   user_id
//   POST action=candidates  user_id, manual_id, ref_id
//   POST action=execute     user_id, manual_id, ref_id, new_manual_id, reason, conversation_id?
// Needs nivasity/sql/add_material_change_overrides.sql. Roles: super admin, admin, support (1-3).
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/material_change_service.php';

header('Content-Type: application/json');

function mcaRespond(int $code, array $payload): void
{
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

$adminId = (int) ($_SESSION['nivas_adminId'] ?? 0);
$adminRole = (int) ($_SESSION['nivas_adminRole'] ?? 0);
if ($adminId <= 0) {
  mcaRespond(401, ['error' => 'Sign in again.']);
}
if (!in_array($adminRole, [1, 2, 3], true)) {
  mcaRespond(403, ['error' => 'Only super admins, admins and support can change materials.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  mcaRespond(405, ['error' => 'Method not allowed']);
}

$action = (string) ($_POST['action'] ?? '');
$userId = (int) ($_POST['user_id'] ?? 0);
$buyer = $userId > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, school, dept FROM users WHERE id = $userId LIMIT 1")) : null;
if (!$buyer) {
  mcaRespond(404, ['error' => 'Student not found.']);
}
$schoolId = (int) $buyer['school'];
$deptId = (int) $buyer['dept'];
$overrideAll = ['ignore_window' => true, 'ignore_once' => true];

if ($action === 'purchases') {
  $rows = [];
  $res = mysqli_query($conn, "SELECT mb.manual_id, mb.ref_id, mb.price, mb.created_at, m.title, m.course_code
      FROM manuals_bought mb INNER JOIN manuals m ON m.id = mb.manual_id
      WHERE mb.buyer = $userId AND mb.status = 'successful'
      ORDER BY mb.created_at DESC LIMIT 40");
  while ($res && ($r = mysqli_fetch_assoc($res))) {
    $manualId = (int) $r['manual_id'];
    $ref = (string) $r['ref_id'];
    // What the student could do alone, and what an admin can still do
    $asStudent = material_change_get_order_context($conn, $userId, $schoolId, $manualId, $ref);
    $asAdmin = $asStudent['ok'] ? $asStudent : material_change_get_order_context($conn, $userId, $schoolId, $manualId, $ref, $overrideAll);
    $rows[] = [
      'manual_id' => $manualId,
      'ref_id' => $ref,
      'title' => (string) $r['title'],
      'course_code' => (string) ($r['course_code'] ?? ''),
      'price' => (int) $r['price'],
      'bought_at' => (string) $r['created_at'],
      'student_can_change' => (bool) $asStudent['ok'],
      'admin_can_change' => (bool) $asAdmin['ok'],
      'note' => $asStudent['ok'] ? '' : (string) ($asStudent['message'] ?? ''),
      'blocked' => $asAdmin['ok'] ? '' : (string) ($asAdmin['message'] ?? ''),
    ];
  }
  mcaRespond(200, ['purchases' => $rows]);
}

$manualId = (int) ($_POST['manual_id'] ?? 0);
$refId = trim((string) ($_POST['ref_id'] ?? ''));
if ($manualId <= 0 || $refId === '') {
  mcaRespond(400, ['error' => 'Choose a purchase.']);
}

if ($action === 'candidates') {
  $result = material_change_get_candidate_materials($conn, $userId, $schoolId, $deptId, $manualId, $refId, $overrideAll);
  if (!$result['ok']) {
    mcaRespond((int) ($result['status_code'] ?? 400), ['error' => $result['message']]);
  }
  mcaRespond(200, ['order' => $result['order'], 'candidates' => $result['candidates']]);
}

if ($action === 'execute') {
  $newManualId = (int) ($_POST['new_manual_id'] ?? 0);
  $reason = substr(trim((string) ($_POST['reason'] ?? '')), 0, 250);
  $conversationId = (int) ($_POST['conversation_id'] ?? 0);
  if ($newManualId <= 0) {
    mcaRespond(400, ['error' => 'Choose the material to change to.']);
  }
  if ($reason === '') {
    mcaRespond(400, ['error' => 'Enter a reason for the override.']);
  }
  $context = material_change_get_order_context($conn, $userId, $schoolId, $manualId, $refId, $overrideAll);
  if (!$context['ok']) {
    mcaRespond((int) ($context['status_code'] ?? 400), ['error' => $context['message']]);
  }
  // Record which student limits this change goes past
  $ignoredOnce = !empty($context['already_changed']) ? 1 : 0;
  $ignoredWindow = material_change_is_within_window((string) ($context['order']['created_at'] ?? ''), 72) ? 0 : 1;

  $result = material_change_execute($conn, $userId, $schoolId, $deptId, $manualId, $newManualId, $refId, 'cc', $overrideAll);
  if (!$result['ok']) {
    mcaRespond((int) ($result['status_code'] ?? 400), ['error' => $result['message']]);
  }
  $d = $result['data'];
  $boughtId = (int) ($d['manuals_bought_id'] ?? 0);
  $boughtVal = $boughtId > 0 ? $boughtId : 'NULL';
  $convVal = $conversationId > 0 ? $conversationId : 'NULL';
  $price = (int) ($d['price'] ?? 0);
  $refEsc = mysqli_real_escape_string($conn, $refId);
  $reasonEsc = mysqli_real_escape_string($conn, $reason);
  if (!mysqli_query($conn, "INSERT INTO manual_change_overrides (admin_id, buyer_id, school_id, manuals_bought_id, ref_id, old_manual_id, new_manual_id, price, ignored_window, ignored_once, reason, bella_conversation_id)
      VALUES ($adminId, $userId, $schoolId, $boughtVal, '$refEsc', $manualId, $newManualId, $price, $ignoredWindow, $ignoredOnce, '$reasonEsc', $convVal)")) {
    error_log('manual_change_overrides insert failed: ' . mysqli_error($conn));
  }
  if (function_exists('log_audit_event')) {
    log_audit_event($conn, $adminId, 'material_change_override', 'manuals_bought', $boughtId ?: null, [
      'buyer_id' => $userId,
      'ref_id' => $refId,
      'old_manual_id' => $manualId,
      'new_manual_id' => $newManualId,
      'ignored_window' => $ignoredWindow,
      'ignored_once' => $ignoredOnce,
      'reason' => $reason,
    ]);
  }
  $new = mysqli_fetch_assoc(mysqli_query($conn, "SELECT title, course_code FROM manuals WHERE id = $newManualId LIMIT 1"));
  mcaRespond(200, [
    'ok' => true,
    'message' => 'Material changed.',
    'new_material' => trim(($new['course_code'] ?? '') . ' · ' . ($new['title'] ?? ''), ' ·'),
  ]);
}

mcaRespond(400, ['error' => 'Unknown action']);
