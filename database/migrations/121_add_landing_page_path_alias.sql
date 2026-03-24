-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.4 — ga4_landing_performance.landing_page_path
-- FIX: DB-10 — Additive alias: legacy `landing_page` retained; `landing_page_path` matches spec name (GENERATED STORED)

SET NAMES utf8mb4;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ga4_landing_performance'
    AND COLUMN_NAME = 'landing_page_path'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ga4_landing_performance ADD COLUMN landing_page_path VARCHAR(1000) GENERATED ALWAYS AS (landing_page) STORED AFTER landing_page',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
