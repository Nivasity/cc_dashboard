-- Incremental migration: add gateway support for manual payment batches.
-- Existing rows are marked as FLUTTERWAVE so previously created batches keep working.
-- Future rows default to PAYSTACK.

SET @has_manual_payment_batches_gateway := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'manual_payment_batches'
    AND column_name = 'gateway'
);
SET @add_manual_payment_batches_gateway_sql := IF(
  @has_manual_payment_batches_gateway = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `gateway` VARCHAR(20) NOT NULL DEFAULT 'FLUTTERWAVE' AFTER `tx_ref`",
  'SELECT 1'
);
PREPARE stmt_add_manual_payment_batches_gateway FROM @add_manual_payment_batches_gateway_sql;
EXECUTE stmt_add_manual_payment_batches_gateway;
DEALLOCATE PREPARE stmt_add_manual_payment_batches_gateway;

UPDATE `manual_payment_batches`
SET `gateway` = 'FLUTTERWAVE'
WHERE `gateway` IS NULL OR TRIM(`gateway`) = '';

ALTER TABLE `manual_payment_batches`
  MODIFY COLUMN `gateway` VARCHAR(20) NOT NULL DEFAULT 'PAYSTACK';

SET @has_manual_payment_batches_gateway_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'manual_payment_batches'
    AND index_name = 'gateway'
);
SET @add_manual_payment_batches_gateway_idx_sql := IF(
  @has_manual_payment_batches_gateway_idx = 0,
  "ALTER TABLE `manual_payment_batches` ADD KEY `gateway` (`gateway`)",
  'SELECT 1'
);
PREPARE stmt_add_manual_payment_batches_gateway_idx FROM @add_manual_payment_batches_gateway_idx_sql;
EXECUTE stmt_add_manual_payment_batches_gateway_idx;
DEALLOCATE PREPARE stmt_add_manual_payment_batches_gateway_idx;