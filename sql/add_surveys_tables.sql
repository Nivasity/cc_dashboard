-- ================================================================
-- Migration: Dynamic Survey System
-- Creates tables for storing survey definitions and responses
-- Run this once against niverpay_db
-- ================================================================

-- --------------------------------------------------------
-- Table: surveys
-- Stores survey definitions (title, questions JSON, status)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `surveys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(160) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `questions_json` longtext NOT NULL COMMENT 'Full survey definition JSON matching SurveyData format (questions or sections)',
  `status` enum('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  `allow_duplicate_email` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = block duplicate emails per survey, 1 = allow',
  `expiry_date` datetime DEFAULT NULL COMMENT 'Auto-close survey after this date; NULL = no expiry',
  `created_by_admin_id` int(11) DEFAULT NULL,
  `updated_by_admin_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_survey_slug` (`slug`),
  KEY `idx_survey_status` (`status`),
  KEY `idx_survey_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: survey_responses
-- Stores individual survey submissions (one row per respondent per survey)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `survey_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) NOT NULL,
  `first_name` varchar(160) NOT NULL,
  `last_name` varchar(160) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `responses_json` longtext NOT NULL COMMENT 'JSON object mapping question_id to answer value',
  `submitter_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sr_survey` (`survey_id`),
  KEY `idx_sr_email` (`email`),
  KEY `idx_sr_created_at` (`created_at`),
  UNIQUE KEY `ux_sr_survey_email` (`survey_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
