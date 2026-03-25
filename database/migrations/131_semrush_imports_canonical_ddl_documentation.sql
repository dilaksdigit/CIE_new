-- SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.1 — Canonical DDL reconciliation (additive documentation + optional rename)
-- CANONICAL (per §3.1): id, import_batch, keyword, position, prev_position, search_volume,
--   keyword_diff, url, traffic_pct, trend, imported_by, imported_at
-- EXTENDED (drift / CLAUDE.md §13): sku_code, intent, cluster_id, competitor_url,
--   competitor_position, import_batch_id, keyword_difficulty
-- Per CLAUDE.md §16 authority order: Semrush CSV Import Spec outranks CLAUDE.md for column naming.
-- Extra columns are NOT dropped (additive-only). Spec-compliant parser maps Semrush "Keyword Difficulty" → keyword_diff.

SET NAMES utf8mb4;

-- Rename keyword_difficulty → keyword_diff only when legacy difficulty exists and canonical keyword_diff does not
SET @has_kdiff = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_difficulty'
);
SET @has_kd = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semrush_imports' AND COLUMN_NAME = 'keyword_diff'
);
SET @sql_rename = IF(@has_kdiff > 0 AND @has_kd = 0,
  'ALTER TABLE semrush_imports CHANGE COLUMN keyword_difficulty keyword_diff INT NULL COMMENT ''Spec §3.1''',
  'SELECT 1'
);
PREPARE stmt_rn FROM @sql_rename; EXECUTE stmt_rn; DEALLOCATE PREPARE stmt_rn;
