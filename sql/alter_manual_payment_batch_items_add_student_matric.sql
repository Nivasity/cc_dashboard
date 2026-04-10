-- Incremental migration: allow batch items to preserve unmatched matric numbers.

SET @has_manual_payment_batch_items_student_matric := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'manual_payment_batch_items'
    AND column_name = 'student_matric'
);
SET @add_manual_payment_batch_items_student_matric_sql := IF(
  @has_manual_payment_batch_items_student_matric = 0,
  "ALTER TABLE `manual_payment_batch_items` ADD COLUMN `student_matric` VARCHAR(50) DEFAULT NULL AFTER `student_id`",
  'SELECT 1'
);
PREPARE stmt_add_manual_payment_batch_items_student_matric FROM @add_manual_payment_batch_items_student_matric_sql;
EXECUTE stmt_add_manual_payment_batch_items_student_matric;
DEALLOCATE PREPARE stmt_add_manual_payment_batch_items_student_matric;