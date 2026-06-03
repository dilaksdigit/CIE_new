-- SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.1 — ERD FK integrity
-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1 — sku_master canonical table
-- PURPOSE: Correct broken FK on semrush_content_snapshots.sku_id from skus(id) to sku_master(sku_id)

-- Drop existing FK for semrush_content_snapshots.sku_id (name may vary by environment)
SET @fk_name := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'semrush_content_snapshots'
      AND COLUMN_NAME = 'sku_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

SET @drop_sql := IF(
    @fk_name IS NOT NULL,
    CONCAT('ALTER TABLE semrush_content_snapshots DROP FOREIGN KEY ', @fk_name),
    'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

-- Add the correct FK referencing sku_master
ALTER TABLE semrush_content_snapshots
    ADD CONSTRAINT fk_scs_sku_master
    FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id)
    ON DELETE CASCADE;
