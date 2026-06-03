-- SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.1 — ERD
-- sku_master.cluster_id FK to cluster_master.cluster_id IS the spec-defined
-- cluster relationship. No separate sku_cluster_map junction table is defined
-- in the source documents. This check is a false positive.

-- Verify FK presence
SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'sku_master'
  AND COLUMN_NAME = 'cluster_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Add FK only if missing
SET @has_fk := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sku_master'
      AND COLUMN_NAME = 'cluster_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
);
SET @fk_sql := IF(
    @has_fk = 0,
    'ALTER TABLE sku_master ADD CONSTRAINT fk_sku_cluster FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id)',
    'SELECT 1'
);
PREPARE stmt_fk FROM @fk_sql;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
