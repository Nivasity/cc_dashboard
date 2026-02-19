--
-- Add manual_export_audits table to track material downloads and grants
-- This migration adds the table with grant tracking capabilities
--

CREATE TABLE IF NOT EXISTS `manual_export_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(25) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `hoc_user_id` int(11) NOT NULL,
  `students_count` int(11) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `downloaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `grant_status` varchar(20) NOT NULL DEFAULT 'pending',
  `granted_by` int(11) DEFAULT NULL COMMENT 'Admin ID who granted the export',
  `granted_at` datetime DEFAULT NULL COMMENT 'Timestamp when export was granted',
  `last_student_id` int(11) DEFAULT NULL COMMENT 'Track last student granted for pagination',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_manual_export_code` (`code`),
  KEY `idx_manual_export_manual` (`manual_id`),
  KEY `idx_manual_export_hoc` (`hoc_user_id`),
  KEY `idx_manual_export_status` (`grant_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
