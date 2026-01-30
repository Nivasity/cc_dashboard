-- --------------------------------------------------------
--
-- Table structure for table `quick_login_codes`
-- This table stores temporary login codes for students that expire within 24 hours
--

CREATE TABLE `quick_login_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `expiry_datetime` datetime NOT NULL,
  `status` enum('active','expired','used','deleted') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`),
  KEY `expiry_datetime` (`expiry_datetime`),
  CONSTRAINT `fk_quick_login_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quick_login_admin` FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `quick_login_codes`
--
ALTER TABLE `quick_login_codes`
  ADD INDEX `idx_code_status` (`code`, `status`),
  ADD INDEX `idx_expiry_status` (`expiry_datetime`, `status`);
