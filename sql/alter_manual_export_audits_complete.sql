--
-- COMPLETE ALTER SQL for manual_export_audits table
-- This script transforms a basic manual_export_audits table into the full grant management version
-- 
-- This includes:
-- 1. Grant management columns (grant_status, granted_by, granted_at, last_student_id)
-- 2. Primary key and AUTO_INCREMENT (if missing)
-- 3. Unique constraint on code (if missing)
-- 4. Indexes for performance (if missing)
--
-- WARNING: Run each section only if needed. Comment out sections that are already in place.
--

-- ======================================================================
-- SECTION 1: Add Grant Management Columns (REQUIRED)
-- ======================================================================

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


-- ======================================================================
-- SECTION 2: Add PRIMARY KEY (if missing)
-- ======================================================================
-- Uncomment the following lines if your table does NOT have a PRIMARY KEY

-- ALTER TABLE `manual_export_audits` 
-- ADD PRIMARY KEY (`id`);


-- ======================================================================
-- SECTION 3: Add AUTO_INCREMENT (if missing)
-- ======================================================================
-- Uncomment the following lines if your id column is NOT AUTO_INCREMENT

-- ALTER TABLE `manual_export_audits` 
-- MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


-- ======================================================================
-- SECTION 4: Add UNIQUE Constraint on code (if missing)
-- ======================================================================
-- Uncomment the following lines if you don't have a UNIQUE constraint on code

-- ALTER TABLE `manual_export_audits` 
-- ADD UNIQUE KEY `ux_manual_export_code` (`code`);


-- ======================================================================
-- SECTION 5: Add Performance Indexes (if missing)
-- ======================================================================
-- Uncomment the following lines to add indexes for better query performance

-- Index on grant_status (for filtering by status)
ALTER TABLE `manual_export_audits` 
ADD KEY `idx_manual_export_status` (`grant_status`);

-- Index on manual_id (for joining with manuals table)
-- Uncomment if missing:
-- ALTER TABLE `manual_export_audits` 
-- ADD KEY `idx_manual_export_manual` (`manual_id`);

-- Index on hoc_user_id (for filtering by HOC)
-- Uncomment if missing:
-- ALTER TABLE `manual_export_audits` 
-- ADD KEY `idx_manual_export_hoc` (`hoc_user_id`);


-- ======================================================================
-- VERIFICATION QUERIES
-- ======================================================================
-- Run these queries to verify the changes

-- Show table structure
-- DESCRIBE `manual_export_audits`;

-- Show indexes
-- SHOW INDEXES FROM `manual_export_audits`;

-- Count existing records
-- SELECT COUNT(*) as total_records FROM `manual_export_audits`;

-- Show sample data with new columns
-- SELECT * FROM `manual_export_audits` LIMIT 5;
