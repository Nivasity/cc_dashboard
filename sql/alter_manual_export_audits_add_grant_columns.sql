--
-- ALTER SQL to update manual_export_audits table
-- This script adds grant management columns to an existing manual_export_audits table
-- 
-- Use this if you already have the basic table structure and need to add grant tracking
--

-- Add grant_status column
ALTER TABLE `manual_export_audits` 
ADD COLUMN `grant_status` varchar(20) NOT NULL DEFAULT 'pending' 
COMMENT 'Status of the grant: pending or granted'
AFTER `downloaded_at`;

-- Add granted_by column
ALTER TABLE `manual_export_audits` 
ADD COLUMN `granted_by` int(11) DEFAULT NULL 
COMMENT 'Admin ID who granted the export'
AFTER `grant_status`;

-- Add granted_at column
ALTER TABLE `manual_export_audits` 
ADD COLUMN `granted_at` datetime DEFAULT NULL 
COMMENT 'Timestamp when the export was granted'
AFTER `granted_by`;

-- Add last_student_id column
ALTER TABLE `manual_export_audits` 
ADD COLUMN `last_student_id` int(11) DEFAULT NULL 
COMMENT 'Track last student granted for pagination'
AFTER `granted_at`;

-- Add index on grant_status for better query performance
ALTER TABLE `manual_export_audits` 
ADD KEY `idx_manual_export_status` (`grant_status`);

-- Optional: If the table doesn't have AUTO_INCREMENT on id column, add it
-- ALTER TABLE `manual_export_audits` 
-- MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Optional: If the table doesn't have PRIMARY KEY, add it
-- ALTER TABLE `manual_export_audits` 
-- ADD PRIMARY KEY (`id`);

-- Optional: If the table doesn't have UNIQUE KEY on code, add it
-- ALTER TABLE `manual_export_audits` 
-- ADD UNIQUE KEY `ux_manual_export_code` (`code`);

-- Optional: If the table doesn't have indexes on manual_id and hoc_user_id, add them
-- ALTER TABLE `manual_export_audits` 
-- ADD KEY `idx_manual_export_manual` (`manual_id`),
-- ADD KEY `idx_manual_export_hoc` (`hoc_user_id`);
