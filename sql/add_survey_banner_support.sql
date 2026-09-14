-- ================================================================
-- Migration: Bottom-Right Survey Banner Support
-- Adds banner targeting to the existing survey system.
-- Run this once against niverpay_db, AFTER add_surveys_tables.sql
-- (requires the `surveys` and `survey_responses` tables to already exist).
-- ================================================================

-- --------------------------------------------------------
-- surveys: flag a survey to show as the non-blocking bottom-right
-- banner in the student app. Only one survey should be flagged at a
-- time (enforced in application code, not by this migration).
-- --------------------------------------------------------

ALTER TABLE `surveys`
  ADD COLUMN IF NOT EXISTS `show_as_banner` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '1 = show as the non-blocking bottom-right banner in the student app (only one survey should be flagged at a time)'
    AFTER `allow_duplicate_email`,
  ADD KEY IF NOT EXISTS `idx_survey_show_as_banner` (`show_as_banner`);

-- --------------------------------------------------------
-- Table: survey_banner_dismissals
-- Tracks which logged-in students have closed the bottom-right survey
-- banner in the student app, so it stays hidden for that student across
-- every device/browser once dismissed (server-side, per user_id + survey_id).
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `survey_banner_dismissals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dismissed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_sbd_survey_user` (`survey_id`, `user_id`),
  KEY `idx_sbd_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
