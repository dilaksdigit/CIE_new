-- SOURCE: CIE_v231_Developer_Build_Pack.pdf §1.1 ERD sku_tier_history
-- FIX: DB-13 — Add changed_by FK to users (additive; string approver columns retained)

SET NAMES utf8mb4;

ALTER TABLE sku_tier_history
  ADD COLUMN IF NOT EXISTS changed_by CHAR(36) NULL;

SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sku_tier_history'
    AND CONSTRAINT_NAME = 'fk_tier_history_user'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE sku_tier_history ADD CONSTRAINT fk_tier_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT ''fk_tier_history_user already present'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
