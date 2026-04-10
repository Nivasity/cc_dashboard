-- Backfill manuals.admin_id from the earliest matching course-material create audit log.
-- This only repairs rows that have an audit trail; rows without a matching audit log remain unchanged.

UPDATE `manuals` AS m
JOIN (
  SELECT CAST(al.entity_id AS UNSIGNED) AS manual_id, al.admin_id
  FROM `audit_logs` AS al
  INNER JOIN (
    SELECT entity_id, MIN(id) AS first_log_id
    FROM `audit_logs`
    WHERE `entity_type` = 'course_material'
      AND `action` = 'create'
      AND `entity_id` IS NOT NULL
      AND `entity_id` REGEXP '^[0-9]+$'
    GROUP BY entity_id
  ) AS first_logs
    ON first_logs.first_log_id = al.id
) AS audit_map
  ON audit_map.manual_id = m.id
SET m.admin_id = audit_map.admin_id
WHERE IFNULL(m.admin_id, 0) = 0;

-- Optional verification query:
-- SELECT id, title, course_code, admin_id FROM manuals WHERE IFNULL(admin_id, 0) = 0 ORDER BY id DESC;