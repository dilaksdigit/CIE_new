-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.4 ga4_landing_performance
-- FIX: DB-10 — FK sku_id → sku_master(id) and UNIQUE (sku_id, week_ending); spec columns added in 102_fix_ga4_landing_performance.sql

SET NAMES utf8mb4;

-- Orphan rows would block FK; clear references to non-existent SKU master rows only.
DELETE FROM ga4_landing_performance
WHERE sku_id IS NOT NULL
  AND sku_id NOT IN (SELECT id FROM sku_master);

SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ga4_landing_performance'
    AND CONSTRAINT_NAME = 'fk_ga4_sku'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk := IF(@fk_exists = 0,
  'ALTER TABLE ga4_landing_performance ADD CONSTRAINT fk_ga4_sku FOREIGN KEY (sku_id) REFERENCES sku_master(id) ON DELETE SET NULL',
  'SELECT ''fk_ga4_sku already present'' AS msg'
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ga4_landing_performance'
    AND INDEX_NAME = 'idx_ga4_sku_week'
);
SET @sql_idx := IF(@idx_exists = 0,
  'ALTER TABLE ga4_landing_performance ADD UNIQUE INDEX idx_ga4_sku_week (sku_id, week_ending)',
  'SELECT ''idx_ga4_sku_week already present'' AS msg'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
