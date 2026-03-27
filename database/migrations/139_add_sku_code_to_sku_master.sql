-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.1 — sku_code as business SKU string (matches Shopify variant SKU).
-- Implemented as STORED GENERATED from sku_id so it stays identical to the canonical business column and needs no app backfill on INSERT.
-- Idempotent: safe to re-run. Uses DATABASE().

SET NAMES utf8mb4;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND COLUMN_NAME = 'sku_code'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE sku_master ADD COLUMN sku_code VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    GENERATED ALWAYS AS (TRIM(sku_id)) STORED',
  'SELECT 1 AS sku_code_column_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_master' AND INDEX_NAME = 'uq_sku_master_sku_code'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE UNIQUE INDEX uq_sku_master_sku_code ON sku_master (sku_code)',
  'SELECT 1 AS uq_sku_master_sku_code_already_present');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
