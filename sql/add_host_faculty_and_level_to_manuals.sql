-- Add host_faculty and level columns to manuals table
-- host_faculty: Faculty hosting the material (can be different from faculty who can buy)
-- level: Student level (100-700)

-- Add host_faculty column and copy existing faculty values into it
ALTER TABLE `manuals` 
ADD COLUMN `host_faculty` INT(11) NOT NULL DEFAULT 0 AFTER `faculty`;

-- Copy existing faculty values to host_faculty for all existing records
UPDATE `manuals` 
SET `host_faculty` = `faculty`;

-- Add level column (100, 200, 300, 400, 500, 600, 700)
ALTER TABLE `manuals` 
ADD COLUMN `level` INT(11) DEFAULT NULL AFTER `host_faculty`;

-- Note: 
-- - host_faculty: The faculty that hosts/owns this material
-- - faculty: The faculty whose students can purchase this material
-- - level: Student academic level (100-700), NULL means all levels
