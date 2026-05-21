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
