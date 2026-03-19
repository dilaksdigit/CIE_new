-- SOURCE: CIE Validation Report DB-06 | CLAUDE.md Section 9; CIE_v232_Semrush_CSV_Import_Spec
-- Idempotent: safe to run on fresh table or after partial 064 run (e.g. intent already added).
SET NAMES utf8mb4;

-- 1) Add intent if missing
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'intent'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE semrush_imports ADD COLUMN intent VARCHAR(100) NULL AFTER search_volume',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Add sku_code if missing
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'sku_code'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE semrush_imports ADD COLUMN sku_code VARCHAR(100) NULL AFTER intent',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Add cluster_id if missing
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'cluster_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE semrush_imports ADD COLUMN cluster_id VARCHAR(50) NULL AFTER sku_code',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Rename keyword_diff -> keyword_difficulty if old column exists
SET @old_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_diff'
);
SET @sql = IF(@old_exists > 0,
  'ALTER TABLE semrush_imports CHANGE COLUMN keyword_diff keyword_difficulty INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Rename url -> competitor_url if old column exists
SET @old_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'url'
);
SET @sql = IF(@old_exists > 0,
  'ALTER TABLE semrush_imports CHANGE COLUMN url competitor_url VARCHAR(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) Add FK only if constraint does not exist
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND CONSTRAINT_NAME = 'fk_semrush_cluster'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE semrush_imports ADD CONSTRAINT fk_semrush_cluster FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;