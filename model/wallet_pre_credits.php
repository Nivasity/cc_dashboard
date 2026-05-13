<?php
session_start();
require_once(__DIR__ . '/wallet_pre_credit_service.php');

header('Content-Type: application/json');

function ccWalletPreCreditRespondJson($statusCode, array $payload) {
  http_response_code($statusCode);
  echo json_encode($payload);
  exit();
}

$adminRole = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$adminId = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;
$allowedRoles = [1, 2, 3, 4];

if (!in_array($adminRole, $allowedRoles, true) || $adminId <= 0) {
  ccWalletPreCreditRespondJson(403, [
    'success' => false,
    'message' => 'Unauthorized access.',
  ]);
}

$missingTables = ccWalletPreCreditGetMissingTables($conn);
if ($missingTables !== []) {
  ccWalletPreCreditRespondJson(500, [
    'success' => false,
    'tables_ready' => false,
    'missing_tables' => $missingTables,
    'message' => 'Wallet pre-credit tables are not available in this environment yet.',
  ]);
}

$adminScope = ccWalletPreCreditGetAdminScope($conn, $adminId);
$adminSchool = (int) ($adminScope['school'] ?? 0);
$adminFaculty = (int) ($adminScope['faculty'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $fetch = trim((string) ($_GET['fetch'] ?? 'list'));

  if ($fetch === 'details') {
    $preCreditId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $record = ccWalletPreCreditFetchOne($conn, $preCreditId, $adminRole, $adminSchool, $adminFaculty);
    if ($record === null) {
      ccWalletPreCreditRespondJson(404, [
        'success' => false,
        'message' => 'Pre-credit record not found.',
      ]);
    }

    ccWalletPreCreditRespondJson(200, [
      'success' => true,
      'record' => $record,
    ]);
  }

  $filters = ccWalletPreCreditGetFilters($_GET);
  $list = ccWalletPreCreditFetchList($conn, $filters, $adminRole, $adminSchool, $adminFaculty);
  ccWalletPreCreditRespondJson(200, [
    'success' => true,
    'summary' => $list['summary'],
    'rows' => $list['rows'],
    'table_notice' => $list['table_notice'],
    'filters' => $filters,
  ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ccWalletPreCreditRespondJson(405, [
    'success' => false,
    'message' => 'Method not allowed.',
  ]);
}

if (isset($_POST['lookup_wallet'])) {
  $accountNumber = trim((string) ($_POST['account_number'] ?? ''));
  if ($accountNumber === '') {
    ccWalletPreCreditRespondJson(400, [
      'success' => false,
      'message' => 'Enter the wallet account number to continue.',
    ]);
  }

  $wallet = ccWalletPreCreditFindWalletByAccountNumber($conn, $accountNumber, $adminRole, $adminSchool, $adminFaculty);
  if ($wallet === null) {
    ccWalletPreCreditRespondJson(404, [
      'success' => false,
      'message' => 'No active student wallet matched that account number.',
    ]);
  }

  ccWalletPreCreditRespondJson(200, [
    'success' => true,
    'wallet' => ccWalletPreCreditNormalizeWalletRecord($wallet),
    'message' => 'Wallet found. Continue with the pre-credit details.',
  ]);
}

if (isset($_POST['create_pre_credit'])) {
  $accountNumber = trim((string) ($_POST['account_number'] ?? ''));
  $wallet = ccWalletPreCreditFindWalletByAccountNumber($conn, $accountNumber, $adminRole, $adminSchool, $adminFaculty);
  if ($wallet === null) {
    ccWalletPreCreditRespondJson(404, [
      'success' => false,
      'message' => 'The selected wallet could not be found again. Please run the lookup again.',
    ]);
  }

  try {
    $result = ccWalletPreCreditCreate($conn, $adminId, $wallet, [
      'provider_reference' => $_POST['provider_reference'] ?? '',
      'amount' => $_POST['amount'] ?? 0,
      'admin_note' => $_POST['admin_note'] ?? '',
      'receipt' => $_FILES['receipt'] ?? null,
    ]);
  } catch (Throwable $throwable) {
    ccWalletPreCreditRespondJson(400, [
      'success' => false,
      'message' => $throwable->getMessage(),
    ]);
  }

  ccWalletPreCreditRespondJson(200, [
    'success' => true,
    'message' => 'Wallet pre-credit created and the student wallet has been credited.',
    'record' => $result['record'],
    'pre_credit_id' => $result['pre_credit_id'],
  ]);
}

if (isset($_POST['update_pre_credit'])) {
  $preCreditId = isset($_POST['pre_credit_id']) ? (int) $_POST['pre_credit_id'] : 0;

  try {
    $record = ccWalletPreCreditUpdate($conn, $adminId, $preCreditId, $adminRole, $adminSchool, $adminFaculty, [
      'status' => $_POST['status'] ?? '',
      'admin_note' => $_POST['admin_note'] ?? '',
      'reconciliation_note' => $_POST['reconciliation_note'] ?? '',
    ]);
  } catch (Throwable $throwable) {
    ccWalletPreCreditRespondJson(400, [
      'success' => false,
      'message' => $throwable->getMessage(),
    ]);
  }

  ccWalletPreCreditRespondJson(200, [
    'success' => true,
    'message' => 'Wallet pre-credit updated successfully.',
    'record' => $record,
  ]);
}

ccWalletPreCreditRespondJson(400, [
  'success' => false,
  'message' => 'Unknown wallet pre-credit action.',
]);