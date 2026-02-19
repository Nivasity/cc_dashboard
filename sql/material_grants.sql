-- SQL migration for Material Grants feature
-- This table tracks grant status for bought materials
-- Admin role 6 will use this to manage material grants

CREATE TABLE IF NOT EXISTS `material_grants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manual_bought_ref_id` varchar(50) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `admin_id` int(11) DEFAULT NULL COMMENT 'Admin who granted',
  `status` enum('pending','granted') NOT NULL DEFAULT 'pending',
  `last_student_id` int(11) DEFAULT NULL COMMENT 'Track last student granted for batch tracking',
  `granted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ref_id` (`manual_bought_ref_id`),
  KEY `idx_manual_id` (`manual_id`),
  KEY `idx_buyer_id` (`buyer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_school_id` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Populate existing bought materials as pending grants
INSERT INTO `material_grants` (`manual_bought_ref_id`, `manual_id`, `buyer_id`, `seller_id`, `school_id`, `status`)
SELECT 
  `ref_id`,
  `manual_id`,
  `buyer`,
  `seller`,
  `school_id`,
  'pending'
FROM `manuals_bought`
WHERE `status` = 'successful'
ON DUPLICATE KEY UPDATE `manual_bought_ref_id` = `manual_bought_ref_id`;
