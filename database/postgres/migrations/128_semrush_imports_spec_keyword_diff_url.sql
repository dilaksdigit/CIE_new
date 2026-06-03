-- SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.1 — authoritative columns keyword_diff, url (additive; legacy columns retained)

SET @kd = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_diff'
);
SET @sql_kd = IF(@kd = 0,
  'ALTER TABLE semrush_imports ADD COLUMN keyword_diff INT NULL COMMENT ''Spec §3.1; mirrors legacy keyword_difficulty when present'' AFTER search_volume',
  'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

SET @has_kd = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_diff'
);
SET @has_kdiff = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_difficulty'
);
SET @sql_copy_kd = IF(@has_kd > 0 AND @has_kdiff > 0,
  'UPDATE semrush_imports SET keyword_diff = keyword_difficulty WHERE keyword_diff IS NULL AND keyword_difficulty IS NOT NULL',
  'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

SET @u = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'url'
);
SET @sql_u = IF(@u = 0,
  'ALTER TABLE semrush_imports ADD COLUMN url VARCHAR(1000) NULL COMMENT ''Spec §3.1; legacy competitor_url retained'' AFTER keyword_diff',
  'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

SET @has_url = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'url'
);
SET @has_curl = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'competitor_url'
);
SET @sql_copy_u = IF(@has_url > 0 AND @has_curl > 0,
  'UPDATE semrush_imports SET url = competitor_url WHERE (url IS NULL OR url = '''') AND competitor_url IS NOT NULL',
  'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;
