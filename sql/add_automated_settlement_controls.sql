-- Migration: Automated Daily Midnight Settlement Controls & Execution Logs

CREATE TABLE IF NOT EXISTS `school_settlement_configs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `is_auto_settlement_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = PAUSED globally',
  `min_settlement_amount` INT(11) NOT NULL DEFAULT 1000 COMMENT 'Minimum balance required to trigger transfer (e.g. N1,000)',
  `max_settlement_cap_per_school` INT(11) NOT NULL DEFAULT 5000000 COMMENT 'Daily maximum cap per school (e.g. N5,000,000)',
  `execution_time` VARCHAR(10) NOT NULL DEFAULT '02:00' COMMENT 'Scheduled daily run time (2am cool-off after midnight)',
  `notify_email` VARCHAR(255) DEFAULT 'finance@nivasity.com' COMMENT 'Destination email for midnight stats',
  `automation_cutoff_at` DATETIME DEFAULT NULL COMMENT 'Ledger rows created before this moment are excluded from automated runs and must be settled manually',
  `updated_by` INT(11) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backfill for existing installs that already created this table without the column.
ALTER TABLE `school_settlement_configs`
  ADD COLUMN IF NOT EXISTS `automation_cutoff_at` DATETIME DEFAULT NULL COMMENT 'Ledger rows created before this moment are excluded from automated runs and must be settled manually';

INSERT INTO `school_settlement_configs` (`id`, `is_auto_settlement_enabled`, `min_settlement_amount`, `max_settlement_cap_per_school`, `execution_time`, `notify_email`, `automation_cutoff_at`)
SELECT 1, 1, 1000, 5000000, '02:00', 'finance@nivasity.com', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `school_settlement_configs` WHERE `id` = 1);

-- For an existing row created before this migration, set the cutover to now so old backlog
-- stays manual-only and automation only ever picks up ledger rows from this point forward.
UPDATE `school_settlement_configs`
SET `automation_cutoff_at` = NOW()
WHERE `id` = 1 AND `automation_cutoff_at` IS NULL;

CREATE TABLE IF NOT EXISTS `settlement_cron_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `run_reference` VARCHAR(64) NOT NULL,
  `started_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `status` ENUM('running','success','paused','partial_failure','failed') NOT NULL DEFAULT 'running',
  `schools_count` INT(11) NOT NULL DEFAULT 0,
  `total_amount_settled` INT(11) NOT NULL DEFAULT 0,
  `total_students_count` INT(11) NOT NULL DEFAULT 0,
  `total_materials_count` INT(11) NOT NULL DEFAULT 0,
  `summary_json` LONGTEXT DEFAULT NULL COMMENT 'Detailed JSON breakdown of schools, faculties, materials, and errors',
  `triggered_by` VARCHAR(50) NOT NULL DEFAULT 'CRON_MIDNIGHT' COMMENT 'CRON_MIDNIGHT or MANUAL_DASHBOARD_TRIGGER',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_settlement_cron_status` (`status`),
  KEY `idx_settlement_cron_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
