<?php
session_start();
require_once(__DIR__ . '/wallet_transactions_data.php');

header('Content-Type: application/json');

function ccWalletTransactionsRespondJson($code, $payload) {
  http_response_code($code);
  echo json_encode($payload);
  exit();
}

$adminRole = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$adminId = (int) ($_SESSION['nivas_adminId'] ?? 0);

if ($adminId <= 0 || !in_array($adminRole, [1, 2, 3, 4], true)) {
  ccWalletTransactionsRespondJson(403, [
    'success' => false,
    'message' => 'Unauthorized access.',
  ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  ccWalletTransactionsRespondJson(405, [
    'success' => false,
    'message' => 'Method not allowed.',
  ]);
}

$payload = ccWalletTransactionsFetchData($conn, ccWalletTransactionsGetFilters($_GET));
$payload['success'] = true;

ccWalletTransactionsRespondJson(200, $payload);