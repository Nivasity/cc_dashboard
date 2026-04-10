-- Incremental migration: add Paystack subaccount code to manual payment batches.

SET @has_manual_payment_batches_paystack_subaccount_code := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'manual_payment_batches'
    AND column_name = 'paystack_subaccount_code'
);

SET @add_manual_payment_batches_paystack_subaccount_code_sql := IF(
  @has_manual_payment_batches_paystack_subaccount_code = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `paystack_subaccount_code` VARCHAR(100) DEFAULT NULL AFTER `gateway`",
  'SELECT 1'
);

PREPARE stmt_add_manual_payment_batches_paystack_subaccount_code FROM @add_manual_payment_batches_paystack_subaccount_code_sql;
EXECUTE stmt_add_manual_payment_batches_paystack_subaccount_code;
DEALLOCATE PREPARE stmt_add_manual_payment_batches_paystack_subaccount_code;