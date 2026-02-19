-- Add Material Grant Manager role to admin_roles table
-- This should be run after the material_grants.sql migration

-- Insert role 6 if it doesn't exist
INSERT INTO `admin_roles` (`id`, `name`, `status`) 
VALUES (6, 'Material Grant Manager', 'active')
ON DUPLICATE KEY UPDATE `name` = 'Material Grant Manager', `status` = 'active';

-- Example: Create a test admin with role 6
-- UNCOMMENT BELOW TO CREATE TEST ADMIN
-- UPDATE THE EMAIL AND PASSWORD AS NEEDED
/*
INSERT INTO `admins` (`first_name`, `last_name`, `email`, `phone`, `role`, `password`, `status`)
VALUES (
  'Grant',
  'Manager',
  'grant.manager@example.com',
  '1234567890',
  6,
  MD5('password123'),  -- Change this password!
  'active'
);
*/
