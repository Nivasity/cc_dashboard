--
-- Add Admin Role 6 - Grant Manager
-- This role can only access the Material Grants page to approve downloaded materials
--

INSERT INTO `admin_roles` (`id`, `name`, `status`) VALUES 
(6, 'Grant Manager', 'active')
ON DUPLICATE KEY UPDATE 
  `name` = 'Grant Manager',
  `status` = 'active';
