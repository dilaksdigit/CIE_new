-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.4 url_performance
-- FIX: DB-09 — Unique (sku_id, week_ending); columns sku_id/week_ending added in 101_fix_url_performance.sql

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'url_performance'
    AND INDEX_NAME = 'idx_url_perf_sku_week'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE url_performance ADD UNIQUE INDEX idx_url_perf_sku_week (sku_id, week_ending)',
  'SELECT ''idx_url_perf_sku_week already present'' AS msg'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;
