-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 20, 2025 at 04:38 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `role` int(11) NOT NULL,
  `school` int(11) DEFAULT NULL,
  `faculty` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `profile_pic` varchar(255) DEFAULT 'user.jpg',
  `last_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_keys`
--

CREATE TABLE `admin_keys` (
  `_key` varchar(20) NOT NULL,
  `exp_date` datetime NOT NULL,
  `status` varchar(25) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--

CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `status` enum('pending','confirmed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
-- Table structure for table `depts`
--

CREATE TABLE `depts` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `faculty_id` int(11) DEFAULT NULL,
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
  `faculty` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
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
  `manual_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `seller` int(11) NOT NULL,
  `buyer` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `ref_id` varchar(50) NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'successful',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `school_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `acct_name` text NOT NULL,
  `acct_number` varchar(15) NOT NULL,
  `bank` varchar(50) NOT NULL,
  `flw_id` varchar(9999) DEFAULT NULL,
  `subaccount_code` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'user',
  `currency` varchar(20) NOT NULL DEFAULT 'NGN',
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
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
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(25) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `status` ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `category` VARCHAR(50) DEFAULT NULL,
  `assigned_admin_id` INT(11) DEFAULT NULL,
  `last_message_at` DATETIME NOT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_ticket_code` (`code`),
  KEY `idx_ticket_user` (`user_id`),
  KEY `idx_ticket_status` (`status`),
  KEY `idx_ticket_assigned` (`assigned_admin_id`),
  CONSTRAINT `fk_ticket_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_ticket_assigned_admin`
    FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_messages`
--

CREATE TABLE `support_ticket_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `sender_type` ENUM('user','admin','system') NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `admin_id` INT(11) DEFAULT NULL,
  `body` TEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_ticket` (`ticket_id`),
  KEY `idx_msg_user` (`user_id`),
  KEY `idx_msg_admin` (`admin_id`),
  CONSTRAINT `fk_msg_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets_v2`(`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_msg_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_msg_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_attachments`
--

CREATE TABLE `support_ticket_attachments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `message_id` INT(11) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_size` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attach_msg` (`message_id`),
  CONSTRAINT `fk_attach_msg`
    FOREIGN KEY (`message_id`) REFERENCES `support_ticket_messages`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_tickets`
--

CREATE TABLE `admin_support_tickets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(25) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `created_by_admin_id` INT(11) NOT NULL,

  `status` ENUM('open','pending','resolved','closed')
      NOT NULL DEFAULT 'open',
  `priority` ENUM('low','medium','high','urgent')
      NOT NULL DEFAULT 'medium',
  `category` VARCHAR(50) DEFAULT NULL,

  `assigned_admin_id` INT(11) DEFAULT NULL,
  `assigned_role_id` INT(11) DEFAULT NULL,

  `related_ticket_id` INT(11) DEFAULT NULL,

  `last_message_at` DATETIME NOT NULL,
  `closed_at` DATETIME DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_admin_ticket_code` (`code`),
  KEY `idx_admin_ticket_status` (`status`),
  KEY `idx_admin_ticket_assigned_admin` (`assigned_admin_id`),
  KEY `idx_admin_ticket_assigned_role` (`assigned_role_id`),
  KEY `idx_admin_ticket_related` (`related_ticket_id`),
  CONSTRAINT `fk_admin_ticket_created_by`
    FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins`(`id`),
  CONSTRAINT `fk_admin_ticket_assigned_admin`
    FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins`(`id`),
  CONSTRAINT `fk_admin_ticket_assigned_role`
    FOREIGN KEY (`assigned_role_id`) REFERENCES `admin_roles`(`id`),
  CONSTRAINT `fk_admin_ticket_related`
    FOREIGN KEY (`related_ticket_id`) REFERENCES `support_tickets_v2`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_ticket_messages`
--

CREATE TABLE `admin_support_ticket_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,

  `sender_type` ENUM('user','admin','system') NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `admin_id` INT(11) DEFAULT NULL,

  `body` TEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_admin_msg_ticket` (`ticket_id`),
  KEY `idx_admin_msg_user` (`user_id`),
  KEY `idx_admin_msg_admin` (`admin_id`),
  CONSTRAINT `fk_admin_msg_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `admin_support_tickets`(`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_admin_msg_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_admin_msg_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_ticket_attachments`
--

CREATE TABLE `admin_support_ticket_attachments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `message_id` INT(11) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_size` INT(11) DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_admin_attach_msg` (`message_id`),
  CONSTRAINT `fk_admin_attach_msg`
    FOREIGN KEY (`message_id`) REFERENCES `admin_support_ticket_messages`(`id`)
    ON DELETE CASCADE
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
-- Table structure for table `manual_payment_batches`
--

CREATE TABLE `manual_payment_batches` (
  `id` INT(11) NOT NULL,
  `manual_id` INT(11) NOT NULL,
  `hoc_id` INT(11) NOT NULL,
  `dept_id` INT(11) NOT NULL,
  `school_id` INT(11) NOT NULL,
  `total_students` INT(11) NOT NULL,
  `total_amount` INT(11) NOT NULL,
  `tx_ref` VARCHAR(50) NOT NULL,
  `flw_tx_id` VARCHAR(100) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_payment_batch_items`
--

CREATE TABLE `manual_payment_batch_items` (
  `id` INT(11) NOT NULL,
  `batch_id` INT(11) NOT NULL,
  `manual_id` INT(11) NOT NULL,
  `student_id` INT(11) NOT NULL,
  `price` INT(11) NOT NULL,
  `ref_id` VARCHAR(50) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `manuals`
--
ALTER TABLE `manuals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organisation`
--
ALTER TABLE `organisation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settlement_accounts`
--
ALTER TABLE `settlement_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `manual_payment_batches`
--

ALTER TABLE `manual_payment_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tx_ref` (`tx_ref`),
  ADD KEY `manual_id` (`manual_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `manual_payment_batch_items`
--

ALTER TABLE `manual_payment_batch_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ref_id` (`ref_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `manual_id` (`manual_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_roles`
--
ALTER TABLE `admin_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `depts`
--
ALTER TABLE `depts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_tickets`
--
ALTER TABLE `event_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manuals`
--
ALTER TABLE `manuals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organisation`
--
ALTER TABLE `organisation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settlement_accounts`
--
ALTER TABLE `settlement_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_payment_batches`
--

ALTER TABLE `manual_payment_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_payment_batch_items`
--

ALTER TABLE `manual_payment_batch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
