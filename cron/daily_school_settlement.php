<?php

// CLI Cron Worker: Daily Automated School Settlement
// Runs every night at 2:00 AM via server crontab (e.g. 0 2 * * * php /path/to/daily_school_settlement.php)
// The 2am run time is a deliberate cool-off after midnight; it settles the PREVIOUS
// calendar day's transactions (00:00:00-23:59:59), not "the last 24 hours from now".

require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/fw.php');
require_once(__DIR__ . '/../model/school_settlement_service.php');

if (php_sapi_name() !== 'cli') {
  $configuredKey = defined('CRON_SETTLEMENT_KEY') ? (string) CRON_SETTLEMENT_KEY : '';
  $providedKey = isset($_GET['key']) ? (string) $_GET['key'] : '';
  $keyIsValid = $configuredKey !== '' && $configuredKey !== 'REPLACE_WITH_A_LONG_RANDOM_SECRET'
    && hash_equals($configuredKey, $providedKey);

  if (!$keyIsValid) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Access denied: CLI or authorized webhook required.']));
  }
}

$conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$conn) {
  error_log('Daily Settlement Cron Error: Database connection failed - ' . mysqli_connect_error());
  if (php_sapi_name() === 'cli') {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
  }
  die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

try {
  echo "[" . date('Y-m-d H:i:s') . "] Starting Daily School Settlement Run...\n";
  
  $result = ccSchoolSettlementExecuteMidnightRun($conn, 'CRON_MIDNIGHT', 0);
  
  echo "[" . date('Y-m-d H:i:s') . "] Finished Run. Status: " . ($result['status'] ?? 'unknown') . "\n";
  echo "Total Schools Settled: " . ($result['schools_count'] ?? 0) . "\n";
  echo "Total Amount Settled: N" . number_format((float)($result['total_amount_settled'] ?? 0), 2) . "\n";
  echo "Total Students: " . ($result['total_students_count'] ?? 0) . "\n";
  echo "Total Materials: " . ($result['total_materials_count'] ?? 0) . "\n";

  if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($result);
  }
} catch (Throwable $e) {
  error_log('Daily Settlement Cron Exception: ' . $e->getMessage());
  if (php_sapi_name() === 'cli') {
    fwrite(STDERR, "Execution failed: " . $e->getMessage() . "\n");
    exit(1);
  }
  header('Content-Type: application/json');
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
