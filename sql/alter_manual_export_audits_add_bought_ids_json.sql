--
-- Add explicit bought-row list tracking to manual_export_audits
-- Stores exact manuals_bought.id rows included in each export.
--

ALTER TABLE `manual_export_audits`
ADD COLUMN `bought_ids_json` LONGTEXT DEFAULT NULL
COMMENT 'JSON array of manuals_bought.id included in this export'
AFTER `to_bought_id`;
