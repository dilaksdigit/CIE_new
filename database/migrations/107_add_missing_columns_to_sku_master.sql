-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.1 sku_master (MySQL 8.x)
-- Adds shopify_url, shopify_product_id, optional §6.1 columns if missing, and URL index.
-- Idempotent: safe to re-run. Uses DATABASE() (run against cie_v232: `mysql ... cie_v232 < this file`).
--
-- NOTE: CIE canonical schema uses sku_master.decay_consecutive_zeros (024); §6.1 "decay_weeks"
-- is added here only if absent. decay_status may already exist from 024 — skipped if present.

SET NAMES utf8mb4;

-- shopify_url
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND COLUMN_NAME = 'shopify_url'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE sku_master ADD COLUMN shopify_url VARCHAR(1000) NULL',
  'SELECT 1 AS shopify_url_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_product_id
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND COLUMN_NAME = 'shopify_product_id'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE sku_master ADD COLUMN shopify_product_id VARCHAR(50) NULL',
  'SELECT 1 AS shopify_product_id_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- maturity_level (§6.1)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND COLUMN_NAME = 'maturity_level'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE sku_master ADD COLUMN maturity_level ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze'",
  'SELECT 1 AS maturity_level_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- decay_status (§6.1) — only if missing (often already present from 024)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND COLUMN_NAME = 'decay_status'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE sku_master ADD COLUMN decay_status ENUM('none','yellow_flag','alert','auto_brief','escalated') NOT NULL DEFAULT 'none'",
  'SELECT 1 AS decay_status_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- decay_weeks (§6.1 integer; distinct from decay_consecutive_zeros if both exist)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND COLUMN_NAME = 'decay_weeks'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE sku_master ADD COLUMN decay_weeks INT NOT NULL DEFAULT 0',
  'SELECT 1 AS decay_weeks_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Prefix index: full VARCHAR(1000) utf8mb4 exceeds default InnoDB index byte limit
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND INDEX_NAME = 'idx_sku_master_shopify_url'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_sku_master_shopify_url ON sku_master (shopify_url(255))',
  'SELECT 1 AS idx_sku_master_shopify_url_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- §6.1 alignment notes (CIE MySQL canonical schema vs abstract spec wording):
-- - Business SKU string is stored in `sku_id` (VARCHAR); spec-named `sku_code` is added in migration 139 (generated from sku_id).
-- - Surrogate PK is `id` (CHAR(36)); not UUID+gen_random_uuid() (PostgreSQL idiom).
-- - `primary_intent_id` + `intent_taxonomy` replaces a varchar `primary_intent` column.
-- - `decay_consecutive_zeros` (024) may coexist with `decay_weeks` when both are present.
-- ---------------------------------------------------------------------------
