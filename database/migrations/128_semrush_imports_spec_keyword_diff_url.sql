-- SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.1 — authoritative columns keyword_diff, url (additive; legacy columns retained)
SET NAMES utf8mb4;

SET @kd = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_diff'
);
SET @sql_kd = IF(@kd = 0,
  'ALTER TABLE semrush_imports ADD COLUMN keyword_diff INT NULL COMMENT ''Spec §3.1; mirrors legacy keyword_difficulty when present'' AFTER search_volume',
  'SELECT 1'
);
PREPARE s1 FROM @sql_kd; EXECUTE s1; DEALLOCATE PREPARE s1;

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
PREPARE s2 FROM @sql_copy_kd; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @u = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'url'
);
SET @sql_u = IF(@u = 0,
  'ALTER TABLE semrush_imports ADD COLUMN url VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT ''Spec §3.1; legacy competitor_url retained'' AFTER keyword_diff',
  'SELECT 1'
);
PREPARE s3 FROM @sql_u; EXECUTE s3; DEALLOCATE PREPARE s3;

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
PREPARE s4 FROM @sql_copy_u; EXECUTE s4; DEALLOCATE PREPARE s4;
