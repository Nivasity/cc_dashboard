--
-- Add bought-row range tracking to manual_export_audits
-- This enables deterministic lookup of exported purchase rows for granting.
--

ALTER TABLE `manual_export_audits`
ADD COLUMN `from_bought_id` int(11) DEFAULT NULL
COMMENT 'Start manuals_bought.id used for this export'
AFTER `total_amount`,
ADD COLUMN `to_bought_id` int(11) DEFAULT NULL
COMMENT 'End manuals_bought.id used for this export'
AFTER `from_bought_id`,
ADD KEY `idx_manual_export_bought_range` (`from_bought_id`, `to_bought_id`);
