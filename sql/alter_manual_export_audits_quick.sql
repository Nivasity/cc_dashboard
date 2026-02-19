--
-- Quick ALTER SQL for manual_export_audits table
-- Adds the 4 grant management columns
--

ALTER TABLE `manual_export_audits` 
ADD COLUMN `grant_status` varchar(20) NOT NULL DEFAULT 'pending' AFTER `downloaded_at`,
ADD COLUMN `granted_by` int(11) DEFAULT NULL AFTER `grant_status`,
ADD COLUMN `granted_at` datetime DEFAULT NULL AFTER `granted_by`,
ADD COLUMN `last_student_id` int(11) DEFAULT NULL AFTER `granted_at`,
ADD KEY `idx_manual_export_status` (`grant_status`);
