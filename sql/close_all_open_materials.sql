-- SQL script to close all currently open course materials
-- This script updates the status of all course materials from 'open' to 'closed'
-- 
-- Usage: Execute this script in your MySQL database to close all open materials
--        mysql -u your_username -p your_database < close_all_open_materials.sql
-- 
-- Created: 2026-02-16
-- Purpose: As part of the update to ignore due date criteria for closed materials,
--          this script provides a way to close all materials that are currently open.
--
-- Note: The table 'manuals' stores course materials in the database

-- Update all materials with status='open' to status='closed'
UPDATE manuals 
SET status = 'closed' 
WHERE status = 'open';

-- Display the number of materials that were closed
SELECT ROW_COUNT() AS 'Number of materials closed';
