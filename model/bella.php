<?php
// Bella conversations (bella_chats.php): server-side proxy to the Bella Worker's admin API, so the
// admin key never reaches the browser.
//   POST action=stats
//   POST action=list    status, q, page
//   POST action=get     id
//   POST action=reply   id, text
//   POST action=status  id, status (bella|waiting|resolved)
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/page_config.php';
if (file_exists(__DIR__ . '/../config/bella.php')) {
  require_once __DIR__ . '/../config/bella.php';
}

header('Content-Type: application/json');

function bellaRespond(int $code, array $payload): void
{
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

$adminId = (int) ($_SESSION['nivas_adminId'] ?? 0);
if ($adminId <= 0 || !$support_mgt_menu) {
  bellaRespond(401, ['error' => 'Sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  bellaRespond(405, ['error' => 'Method not allowed']);
}
if (!defined('BELLA_URL') || !defined('BELLA_ADMIN_KEY')) {
  bellaRespond(200, ['error' => 'Bella is not configured: copy config/bella.example.php to config/bella.php.']);
}

function bellaCall(string $method, string $path, ?array $body = null): array
{
  $ch = curl_init(rtrim(BELLA_URL, '/') . $path);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => ['X-Admin-Key: ' . BELLA_ADMIN_KEY, 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
  ]);
  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  }
  $raw = curl_exec($ch);
  $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($raw === false) {
    bellaRespond(502, ['error' => 'Could not reach Bella: ' . $err]);
  }
  $json = json_decode((string) $raw, true);
  if (!is_array($json)) {
    bellaRespond(502, ['error' => 'Unexpected reply from Bella (HTTP ' . $http . ')']);
  }
  if ($http >= 400) {
    bellaRespond($http, ['error' => $json['error'] ?? 'Bella refused the request']);
  }
  return $json;
}

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$adminName = 'Nivasity team';
$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name FROM admins WHERE id = $adminId LIMIT 1"));
if ($row && trim((string) $row['first_name']) !== '') {
  $adminName = trim((string) $row['first_name']) . ' (Nivasity)';
}

switch ($action) {
  case 'stats':
    bellaRespond(200, bellaCall('GET', '/admin/stats'));
  case 'list':
    $q = http_build_query(array_filter([
      'status' => (string) ($_POST['status'] ?? ''),
      'q' => trim((string) ($_POST['q'] ?? '')),
      'page' => max(1, (int) ($_POST['page'] ?? 1)),
    ]));
    bellaRespond(200, bellaCall('GET', '/admin/conversations?' . $q));
  case 'get':
    bellaRespond(200, bellaCall('GET', '/admin/conversations/' . $id));
  case 'reply':
    $text = trim((string) ($_POST['text'] ?? ''));
    if ($text === '') {
      bellaRespond(400, ['error' => 'Type a reply.']);
    }
    $res = bellaCall('POST', '/admin/conversations/' . $id . '/reply', ['text' => $text, 'agent_name' => $adminName]);
    if (function_exists('log_audit_event')) {
      log_audit_event($conn, $adminId, 'bella_reply', 'bella_conversation', $id, []);
    }
    bellaRespond(200, $res);
  case 'status':
    $res = bellaCall('POST', '/admin/conversations/' . $id . '/status', ['status' => (string) ($_POST['status'] ?? ''), 'agent_name' => $adminName]);
    bellaRespond(200, $res);
}
bellaRespond(400, ['error' => 'Unknown action']);
