SET @schema_name := DATABASE();

SET @has_batch_paid_by_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batches'
    AND COLUMN_NAME = 'paid_by_name'
);
SET @sql := IF(
  @has_batch_paid_by_name = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `paid_by_name` VARCHAR(120) DEFAULT NULL AFTER `status`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_batch_paid_by_phone := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batches'
    AND COLUMN_NAME = 'paid_by_phone'
);
SET @sql := IF(
  @has_batch_paid_by_phone = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `paid_by_phone` VARCHAR(30) DEFAULT NULL AFTER `paid_by_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_batch_payment_reason := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batches'
    AND COLUMN_NAME = 'payment_reason'
);
SET @sql := IF(
  @has_batch_payment_reason = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `payment_reason` TEXT DEFAULT NULL AFTER `paid_by_phone`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_batch_receipt_path := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batches'
    AND COLUMN_NAME = 'receipt_path'
);
SET @sql := IF(
  @has_batch_receipt_path = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `receipt_path` VARCHAR(255) DEFAULT NULL AFTER `payment_reason`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_batch_receipt_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batches'
    AND COLUMN_NAME = 'receipt_name'
);
SET @sql := IF(
  @has_batch_receipt_name = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `receipt_name` VARCHAR(255) DEFAULT NULL AFTER `receipt_path`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_batch_receipt_mime_type := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batches'
    AND COLUMN_NAME = 'receipt_mime_type'
);
SET @sql := IF(
  @has_batch_receipt_mime_type = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `receipt_mime_type` VARCHAR(100) DEFAULT NULL AFTER `receipt_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_batch_receipt_size := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batches'
    AND COLUMN_NAME = 'receipt_size'
);
SET @sql := IF(
  @has_batch_receipt_size = 0,
  "ALTER TABLE `manual_payment_batches` ADD COLUMN `receipt_size` INT(11) DEFAULT NULL AFTER `receipt_mime_type`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_student_first_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'student_first_name'
);
SET @sql := IF(
  @has_item_student_first_name = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `student_first_name` VARCHAR(100) DEFAULT NULL AFTER `student_matric`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_student_last_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'student_last_name'
);
SET @sql := IF(
  @has_item_student_last_name = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `student_last_name` VARCHAR(100) DEFAULT NULL AFTER `student_first_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_placeholder_user_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'placeholder_user_id'
);
SET @sql := IF(
  @has_item_placeholder_user_id = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `placeholder_user_id` INT(11) DEFAULT NULL AFTER `student_last_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_matched_user_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'matched_user_id'
);
SET @sql := IF(
  @has_item_matched_user_id = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `matched_user_id` INT(11) DEFAULT NULL AFTER `placeholder_user_id`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_manuals_bought_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'manuals_bought_id'
);
SET @sql := IF(
  @has_item_manuals_bought_id = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `manuals_bought_id` INT(11) DEFAULT NULL AFTER `matched_user_id`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_normalized_first_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'normalized_first_name'
);
SET @sql := IF(
  @has_item_normalized_first_name = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `normalized_first_name` VARCHAR(100) DEFAULT NULL AFTER `manuals_bought_id`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_normalized_last_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'normalized_last_name'
);
SET @sql := IF(
  @has_item_normalized_last_name = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `normalized_last_name` VARCHAR(100) DEFAULT NULL AFTER `normalized_first_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_pending_lookup_matric_no := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'pending_lookup_matric_no'
);
SET @sql := IF(
  @has_item_pending_lookup_matric_no = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `pending_lookup_matric_no` VARCHAR(100) DEFAULT NULL AFTER `normalized_last_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_claim_status := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'claim_status'
);
SET @sql := IF(
  @has_item_claim_status = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `claim_status` VARCHAR(32) DEFAULT NULL AFTER `pending_lookup_matric_no`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_claimed_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'claimed_at'
);
SET @sql := IF(
  @has_item_claimed_at = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `claimed_at` DATETIME DEFAULT NULL AFTER `claim_status`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_item_confirmed_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'manual_payment_batch_items'
    AND COLUMN_NAME = 'confirmed_at'
);
SET @sql := IF(
  @has_item_confirmed_at = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `confirmed_at` DATETIME DEFAULT NULL AFTER `claimed_at`",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
