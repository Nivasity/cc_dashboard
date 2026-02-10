-- Add admin_id column to manuals table
-- This allows tracking which admin created each course material

ALTER TABLE `manuals` 
ADD COLUMN `admin_id` INT(11) DEFAULT NULL AFTER `user_id`;

-- Set default admin_id to 0 for existing records where user_id is 0
UPDATE `manuals` 
SET `admin_id` = 0 
WHERE `user_id` = 0 AND `admin_id` IS NULL;
