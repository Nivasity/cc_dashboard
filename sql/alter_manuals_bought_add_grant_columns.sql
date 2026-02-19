--
-- Add grant tracking columns to manuals_bought
-- Includes id primary key for range-based export lookup support.
--

ALTER TABLE `manuals_bought`
ADD COLUMN `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
ADD PRIMARY KEY (`id`);

ALTER TABLE `manuals_bought`
ADD COLUMN `grant_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = pending, 1 = granted',
ADD COLUMN `export_id` INT(11) DEFAULT NULL COMMENT 'manual_export_audits.id used to grant this row; NULL for single grant',
ADD KEY `idx_manuals_bought_grant_status` (`grant_status`),
ADD KEY `idx_manuals_bought_export_id` (`export_id`),
ADD KEY `idx_manuals_bought_manual_buyer_grant` (`manual_id`, `buyer`, `grant_status`),
ADD KEY `idx_manuals_bought_manual_id_range` (`manual_id`, `id`);
