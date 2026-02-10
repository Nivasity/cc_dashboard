-- Add admin_id column to manuals table
-- This allows tracking which admin created each course material

ALTER TABLE `manuals` 
ADD COLUMN `admin_id` INT(11) DEFAULT NULL AFTER `user_id`;

-- Note: Existing records will have NULL admin_id to indicate unknown/unassigned admin
-- New materials created through the admin panel will have the creating admin's ID
