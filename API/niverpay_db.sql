-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 24, 2026 at 01:04 PM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `niverpay_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` varchar(25) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `gateway` varchar(20) DEFAULT 'FLUTTERWAVE' COMMENT 'Payment gateway used: flutterwave, paystack, or interswitch',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `depts`
--

CREATE TABLE `depts` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(100) NOT NULL DEFAULT 'public',
  `school` int(11) DEFAULT NULL,
  `event_link` text DEFAULT NULL,
  `location` text NOT NULL,
  `event_banner` text NOT NULL,
  `price` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `event_date` datetime NOT NULL,
  `event_time` time NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `currency` varchar(30) NOT NULL DEFAULT 'NGN',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_tickets`
--

CREATE TABLE `event_tickets` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `seller` int(11) NOT NULL,
  `buyer` int(11) NOT NULL,
  `ref_id` varchar(50) NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'successful',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fund_requests`
--

CREATE TABLE `fund_requests` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `amount_requested` decimal(15,2) NOT NULL,
  `purpose` text NOT NULL,
  `additional_details` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manuals`
--

CREATE TABLE `manuals` (
  `id` int(11) NOT NULL,
  `title` text NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `price` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `due_date` datetime NOT NULL,
  `quantity` int(11) NOT NULL,
  `dept` int(11) NOT NULL,
  `depts` longtext DEFAULT NULL,
  `coverage` enum('School','Faculty','Custom') NOT NULL DEFAULT 'Custom',
  `faculty` int(11) DEFAULT NULL,
  `host_faculty` int(11) NOT NULL DEFAULT 0,
  `level` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `currency` varchar(30) NOT NULL DEFAULT 'NGN',
  `school_id` int(11) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manuals_bought`
--

CREATE TABLE `manuals_bought` (
  `id` int(11) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `seller` int(11) NOT NULL,
  `buyer` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `ref_id` varchar(50) NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'successful',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `grant_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = pending, 1 = granted',
  `export_id` int(11) DEFAULT NULL COMMENT 'manual_export_audits.id used to grant this row; NULL for single grant'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_export_audits`
--

CREATE TABLE `manual_export_audits` (
  `id` int(11) NOT NULL,
  `code` varchar(25) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `hoc_user_id` int(11) NOT NULL,
  `students_count` int(11) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `from_bought_id` int(11) DEFAULT NULL COMMENT 'Start manuals_bought.id used for this export',
  `to_bought_id` int(11) DEFAULT NULL COMMENT 'End manuals_bought.id used for this export',
  `downloaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `grant_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'Status of the grant: pending or granted',
  `granted_by` int(11) DEFAULT NULL COMMENT 'Admin ID who granted the export',
  `granted_at` datetime DEFAULT NULL COMMENT 'Timestamp when the export was granted',
  `last_student_id` int(11) DEFAULT NULL COMMENT 'Track last student granted for pagination',
  `bought_ids_json` longtext DEFAULT NULL COMMENT 'JSON array of manuals_bought.id included in this export'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mobile_experience_feedback`
--

CREATE TABLE `mobile_experience_feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_choice` enum('android','iphone') NOT NULL,
  `comfort_level` enum('love_it','its_cool','its_okay','kinda_stressful','not_good_experience') NOT NULL,
  `comfort_label` varchar(64) NOT NULL,
  `source_page` varchar(64) NOT NULL DEFAULT 'store',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `data` text DEFAULT NULL COMMENT 'JSON encoded data',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organisation`
--

CREATE TABLE `organisation` (
  `id` int(11) NOT NULL,
  `business_name` text NOT NULL,
  `business_address` text DEFAULT NULL,
  `web_url` text DEFAULT NULL,
  `work_email` varchar(100) DEFAULT NULL,
  `socials` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quick_login_codes`
--

CREATE TABLE `quick_login_codes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `expiry_datetime` datetime NOT NULL,
  `status` enum('active','expired','used','deleted') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `code` varchar(200) NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settlement_accounts`
--

CREATE TABLE `settlement_accounts` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `acct_name` text NOT NULL,
  `acct_number` varchar(15) NOT NULL,
  `bank` varchar(50) NOT NULL,
  `flw_id` varchar(9999) DEFAULT NULL,
  `subaccount_code` varchar(100) NOT NULL,
  `gateway` varchar(20) DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'user',
  `currency` varchar(20) NOT NULL DEFAULT 'NGN',
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_contacts`
--

CREATE TABLE `support_contacts` (
  `id` int(11) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL COMMENT 'WhatsApp number with country code (e.g., +2348012345678)',
  `email` varchar(255) DEFAULT NULL COMMENT 'Support email address',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Support phone number with country code',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Only active contact is shown in app',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Support contact information for mobile app';

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets_legacy`
--

CREATE TABLE `support_tickets_legacy` (
  `id` int(11) NOT NULL,
  `code` varchar(25) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'open',
  `response` text NOT NULL,
  `response_time` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets_v2`
--

CREATE TABLE `support_tickets_v2` (
  `id` int(11) NOT NULL,
  `code` varchar(25) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `category` varchar(50) DEFAULT NULL,
  `assigned_admin_id` int(11) DEFAULT NULL,
  `last_message_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_attachments`
--

CREATE TABLE `support_ticket_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_messages`
--

CREATE TABLE `support_ticket_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `sender_type` enum('user','admin','system') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_alerts`
--

CREATE TABLE `system_alerts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `expiry_date` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `charge` int(11) DEFAULT NULL,
  `profit` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `medium` varchar(50) NOT NULL DEFAULT 'FLUTTERWAVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `school` int(11) DEFAULT NULL,
  `dept` int(11) DEFAULT NULL,
  `matric_no` varchar(25) DEFAULT NULL,
  `role` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'unverified',
  `adm_year` varchar(255) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'user.jpg',
  `last_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `verification_code`
--

CREATE TABLE `verification_code` (
  `user_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `exp_date` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `action` (`action`),
  ADD KEY `entity_type` (`entity_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gateway` (`gateway`);

--
-- Indexes for table `depts`
--
ALTER TABLE `depts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_tickets`
--
ALTER TABLE `event_tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fund_requests`
--
ALTER TABLE `fund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `manuals`
--
ALTER TABLE `manuals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_manuals_coverage` (`coverage`);

--
-- Indexes for table `manuals_bought`
--
ALTER TABLE `manuals_bought`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_manuals_bought_grant_status` (`grant_status`),
  ADD KEY `idx_manuals_bought_export_id` (`export_id`),
  ADD KEY `idx_manuals_bought_manual_buyer_grant` (`manual_id`,`buyer`,`grant_status`);

--
-- Indexes for table `manual_export_audits`
--
ALTER TABLE `manual_export_audits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_manual_export_code` (`code`),
  ADD KEY `idx_manual_export_manual` (`manual_id`),
  ADD KEY `idx_manual_export_hoc` (`hoc_user_id`),
  ADD KEY `idx_manual_export_status` (`grant_status`),
  ADD KEY `idx_manual_export_bought_range` (`from_bought_id`,`to_bought_id`);

--
-- Indexes for table `mobile_experience_feedback`
--
ALTER TABLE `mobile_experience_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mef_user_id` (`user_id`),
  ADD KEY `idx_mef_device_choice` (`device_choice`),
  ADD KEY `idx_mef_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `type` (`type`),
  ADD KEY `read_at` (`read_at`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_user_unread` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `organisation`
--
ALTER TABLE `organisation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quick_login_codes`
--
ALTER TABLE `quick_login_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `expiry_datetime` (`expiry_datetime`),
  ADD KEY `fk_quick_login_admin` (`created_by`),
  ADD KEY `idx_code_status` (`code`,`status`),
  ADD KEY `idx_expiry_status` (`expiry_datetime`,`status`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settlement_accounts`
--
ALTER TABLE `settlement_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gateway` (`gateway`);

--
-- Indexes for table `support_contacts`
--
ALTER TABLE `support_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `support_tickets_legacy`
--
ALTER TABLE `support_tickets_legacy`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets_v2`
--
ALTER TABLE `support_tickets_v2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_ticket_code` (`code`),
  ADD KEY `idx_ticket_user` (`user_id`),
  ADD KEY `idx_ticket_status` (`status`),
  ADD KEY `idx_ticket_assigned` (`assigned_admin_id`);

--
-- Indexes for table `support_ticket_attachments`
--
ALTER TABLE `support_ticket_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attach_msg` (`message_id`);

--
-- Indexes for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_ticket` (`ticket_id`),
  ADD KEY `idx_msg_user` (`user_id`),
  ADD KEY `idx_msg_admin` (`admin_id`);

--
-- Indexes for table `system_alerts`
--
ALTER TABLE `system_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active` (`active`),
  ADD KEY `expiry_date` (`expiry_date`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medium` (`medium`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quick_login_codes`
--
ALTER TABLE `quick_login_codes`
  ADD CONSTRAINT `fk_quick_login_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quick_login_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets_v2`
--
ALTER TABLE `support_tickets_v2`
  ADD CONSTRAINT `fk_ticket_assigned_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `support_ticket_attachments`
--
ALTER TABLE `support_ticket_attachments`
  ADD CONSTRAINT `fk_attach_msg` FOREIGN KEY (`message_id`) REFERENCES `support_ticket_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD CONSTRAINT `fk_msg_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_msg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets_v2` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
