<?php

if (!function_exists('batchLedgerTableExists')) {
  function batchLedgerTableExists(mysqli $conn, string $tableName): bool
  {
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
      return $cache[$tableName];
    }

    $tableNameSafe = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableNameSafe'");
    $cache[$tableName] = $result instanceof mysqli_result && mysqli_num_rows($result) > 0;

    return $cache[$tableName];
  }
}

if (!function_exists('batchEnsureSchoolInternalWallet')) {
  function batchEnsureSchoolInternalWallet(mysqli $conn, int $schoolId): void
  {
    if ($schoolId <= 0 || !batchLedgerTableExists($conn, 'school_internal_wallets')) {
      return;
    }

    $walletSql = "INSERT INTO school_internal_wallets (school_id, current_balance, pending_payout_balance, carry_forward_balance, currency, status)
                  VALUES ($schoolId, 0, 0, 0, 'NGN', 'active')
                  ON DUPLICATE KEY UPDATE updated_at = updated_at";
    if (!mysqli_query($conn, $walletSql)) {
      throw new RuntimeException('Failed to ensure school internal wallet: ' . mysqli_error($conn));
    }
  }
}

if (!function_exists('batchRecordSchoolPayable')) {
  function batchRecordSchoolPayable(mysqli $conn, array $payload): array
  {
    if (!batchLedgerTableExists($conn, 'school_payable_ledger')) {
      return [
        'status' => 'missing_table',
        'payable_amount' => 0,
      ];
    }

    $schoolId = (int)($payload['school_id'] ?? 0);
    $sourceRefId = trim((string)($payload['source_ref_id'] ?? ''));
    $payerUserId = max(0, (int)($payload['payer_user_id'] ?? 0));
    $sourceMedium = strtoupper(trim((string)($payload['source_medium'] ?? 'BATCH_PAYMENT')));
    $sourceChannel = strtolower(trim((string)($payload['source_channel'] ?? 'batch_payment')));
    $itemSubtotal = max(0, (int)round((float)($payload['item_subtotal'] ?? 0)));
    $collectedTotal = max(0, (int)round((float)($payload['collected_total'] ?? $itemSubtotal)));
    $chargeAmount = max(0, (int)round((float)($payload['charge_amount'] ?? 0)));
    $refundAmount = max(0, (int)round((float)($payload['refund_amount'] ?? 0)));
    $metadata = $payload['metadata'] ?? [];
    $payableAmount = max(0, $itemSubtotal - $refundAmount);

    if ($schoolId <= 0 || $sourceRefId === '' || $itemSubtotal <= 0) {
      return [
        'status' => 'ignored',
        'payable_amount' => 0,
      ];
    }

    $sourceRefSafe = mysqli_real_escape_string($conn, $sourceRefId);
    $existingSql = "SELECT * FROM school_payable_ledger WHERE source_ref_id = '$sourceRefSafe' LIMIT 1 FOR UPDATE";
    $existingRs = mysqli_query($conn, $existingSql);
    if (!$existingRs) {
      throw new RuntimeException('Failed to inspect school payable ledger state: ' . mysqli_error($conn));
    }
    if (mysqli_num_rows($existingRs) > 0) {
      $existingRow = mysqli_fetch_assoc($existingRs);
      return [
        'status' => 'exists',
        'payable_amount' => (int)($existingRow['payable_amount'] ?? 0),
        'row' => $existingRow,
      ];
    }

    batchEnsureSchoolInternalWallet($conn, $schoolId);

    if (!is_array($metadata)) {
      $metadata = [];
    }

    $mediumSafe = mysqli_real_escape_string($conn, $sourceMedium);
    $channelSafe = mysqli_real_escape_string($conn, $sourceChannel);
    $metadataJson = mysqli_real_escape_string(
      $conn,
      json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    $insertSql = "INSERT INTO school_payable_ledger (
                    school_id, source_ref_id, payer_user_id, source_medium, source_channel,
                    item_subtotal, collected_total, charge_amount, refund_amount, payable_amount, metadata
                  ) VALUES (
                    $schoolId, '$sourceRefSafe', $payerUserId, '$mediumSafe', '$channelSafe',
                    $itemSubtotal, $collectedTotal, $chargeAmount, $refundAmount, $payableAmount, '$metadataJson'
                  )";
    if (!mysqli_query($conn, $insertSql)) {
      throw new RuntimeException('Failed to insert school payable ledger row: ' . mysqli_error($conn));
    }

    if ($payableAmount > 0 && batchLedgerTableExists($conn, 'school_internal_wallets')) {
      $updateWalletSql = "UPDATE school_internal_wallets
                          SET current_balance = current_balance + $payableAmount,
                              pending_payout_balance = pending_payout_balance + $payableAmount,
                              updated_at = NOW()
                          WHERE school_id = $schoolId";
      if (!mysqli_query($conn, $updateWalletSql)) {
        throw new RuntimeException('Failed to update school internal wallet balance: ' . mysqli_error($conn));
      }
    }

    return [
      'status' => 'created',
      'payable_amount' => $payableAmount,
    ];
  }
}

if (!function_exists('batchRecordSchoolPayableForItem')) {
  function batchRecordSchoolPayableForItem(mysqli $conn, array $batch, array $item, string $gateway): array
  {
    $schoolId = (int)($batch['school_id'] ?? 0);
    $batchId = (int)($batch['id'] ?? 0);
    $manualId = (int)($item['manual_id'] ?? 0);
    $batchItemId = (int)($item['id'] ?? 0);
    $studentId = max(0, (int)($item['student_id'] ?? 0));
    $studentMatric = trim((string)($item['student_matric'] ?? ''));
    $refId = trim((string)($item['ref_id'] ?? ''));
    $price = max(0, (int)($item['price'] ?? 0));

    return batchRecordSchoolPayable($conn, [
      'school_id' => $schoolId,
      'source_ref_id' => $refId,
      'payer_user_id' => $studentId,
      'source_medium' => strtoupper(trim($gateway)) !== '' ? strtoupper(trim($gateway)) : 'BATCH_PAYMENT',
      'source_channel' => 'batch_payment',
      'item_subtotal' => $price,
      'collected_total' => $price,
      'charge_amount' => 0,
      'refund_amount' => 0,
      'metadata' => [
        'source' => 'bulk_payment_batch',
        'batch_id' => $batchId,
        'batch_item_id' => $batchItemId,
        'manual_id' => $manualId,
        'student_id' => $studentId,
        'student_matric' => $studentMatric,
        'gateway' => strtoupper(trim($gateway)),
        'hoc_id' => (int)($batch['hoc_id'] ?? 0),
      ],
    ]);
  }
}

if (!function_exists('batchFinalizeTransactionForItem')) {
  function batchFinalizeTransactionForItem(mysqli $conn, array $batch, array $item, string $gateway): array
  {
    $refId = trim((string)($item['ref_id'] ?? ''));
    $batchId = (int)($batch['id'] ?? 0);
    $studentId = max(0, (int)($item['student_id'] ?? 0));
    $amount = max(0, (int)($item['price'] ?? 0));
    $medium = strtoupper(trim($gateway));

    if ($medium === '') {
      $medium = 'PAYSTACK';
    }

    if ($refId === '' || $batchId <= 0 || $amount <= 0) {
      return [
        'status' => 'ignored',
      ];
    }

    $lookupStmt = $conn->prepare('SELECT id FROM transactions WHERE ref_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    if (!$lookupStmt) {
      throw new RuntimeException('Failed to prepare batch transaction lookup: ' . mysqli_error($conn));
    }

    $lookupStmt->bind_param('s', $refId);
    if (!$lookupStmt->execute()) {
      $lookupStmt->close();
      throw new RuntimeException('Failed to inspect batch transaction state: ' . mysqli_error($conn));
    }

    $existingRs = $lookupStmt->get_result();
    $existingRow = $existingRs ? $existingRs->fetch_assoc() : null;
    $lookupStmt->close();

    if ($existingRow) {
      $transactionId = (int)($existingRow['id'] ?? 0);
      $updateStmt = $conn->prepare('UPDATE transactions SET user_id = ?, batch_id = ?, amount = ?, status = "successful", medium = ? WHERE id = ?');
      if (!$updateStmt) {
        throw new RuntimeException('Failed to prepare batch transaction update: ' . mysqli_error($conn));
      }

      $updateStmt->bind_param('iiisi', $studentId, $batchId, $amount, $medium, $transactionId);
      if (!$updateStmt->execute()) {
        $updateStmt->close();
        throw new RuntimeException('Failed to finalize existing batch transaction: ' . mysqli_error($conn));
      }
      $updateStmt->close();

      return [
        'status' => 'updated',
        'transaction_id' => $transactionId,
      ];
    }

    $insertStmt = $conn->prepare('INSERT INTO transactions (ref_id, user_id, batch_id, amount, status, medium) VALUES (?, ?, ?, ?, "successful", ?)');
    if (!$insertStmt) {
      throw new RuntimeException('Failed to prepare batch transaction insert: ' . mysqli_error($conn));
    }

    $insertStmt->bind_param('siiis', $refId, $studentId, $batchId, $amount, $medium);
    if (!$insertStmt->execute()) {
      $insertStmt->close();
      throw new RuntimeException('Failed to create batch transaction: ' . mysqli_error($conn));
    }

    $transactionId = (int)$insertStmt->insert_id;
    $insertStmt->close();

    return [
      'status' => 'created',
      'transaction_id' => $transactionId,
    ];
  }
}