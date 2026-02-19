--
-- Add departments scope storage for Role 6 admins
-- NULL means all departments within assigned faculty.
--

ALTER TABLE `admins`
ADD COLUMN `departments` TEXT DEFAULT NULL COMMENT 'JSON array of allowed department IDs for role 6; NULL = all departments' AFTER `faculty`;
