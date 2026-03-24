-- SOURCE: CLAUDE.md §13 Semrush Integration
-- FIX: DB-17 — Add Semrush CSV–aligned columns if missing (additive; 064 may have renamed keyword_diff / url)

SET NAMES utf8mb4;

SET @c := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'intent'
);
SET @sql := IF(@c = 0,
  'ALTER TABLE semrush_imports ADD COLUMN intent VARCHAR(50) NULL AFTER search_volume',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'sku_code'
);
SET @sql := IF(@c = 0,
  'ALTER TABLE semrush_imports ADD COLUMN sku_code VARCHAR(50) NULL AFTER intent',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'cluster_id'
);
SET @sql := IF(@c = 0,
  'ALTER TABLE semrush_imports ADD COLUMN cluster_id VARCHAR(100) NULL AFTER sku_code',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'competitor_url'
);
SET @sql := IF(@c = 0,
  'ALTER TABLE semrush_imports ADD COLUMN competitor_url VARCHAR(1000) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'trend'
);
SET @sql := IF(@c = 0,
  'ALTER TABLE semrush_imports ADD COLUMN trend VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Alias column: keyword_difficulty when legacy keyword_diff exists (do not rename keyword_diff)
SET @has_diff := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_diff'
);
SET @has_difficulty := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_difficulty'
);
SET @sql := IF(@has_diff > 0 AND @has_difficulty = 0,
  'ALTER TABLE semrush_imports ADD COLUMN keyword_difficulty INT NULL AFTER keyword_diff',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_diff := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_diff'
);
SET @has_difficulty := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_difficulty'
);
SET @sql := IF(@has_diff > 0 AND @has_difficulty > 0,
  'UPDATE semrush_imports SET keyword_difficulty = keyword_diff WHERE keyword_difficulty IS NULL AND keyword_diff IS NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
