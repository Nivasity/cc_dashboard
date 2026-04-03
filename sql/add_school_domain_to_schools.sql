ALTER TABLE schools
  ADD COLUMN domain varchar(255) DEFAULT NULL AFTER code;

ALTER TABLE schools
  ADD UNIQUE KEY uniq_schools_domain (domain);

-- Store only the host, for example:
-- UPDATE schools SET domain = 'funaab.nivasity.com' WHERE code = 'FUNAAB';