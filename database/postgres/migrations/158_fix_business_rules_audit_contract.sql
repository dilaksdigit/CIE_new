-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5.1
-- Fix DB-02: Align business_rules_audit schema contract without changing app logic.

-- Ensure canonical primary key name
SET @has_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'business_rules_audit'
    AND COLUMN_NAME = 'id'
);

SET @has_audit_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'business_rules_audit'
    AND COLUMN_NAME = 'audit_id'
);

SET @sql := IF(
  @has_id = 1 AND @has_audit_id = 0,
  'ALTER TABLE business_rules_audit CHANGE COLUMN id audit_id CHAR(36) NOT NULL',
  'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

-- Add missing spec columns
ALTER TABLE business_rules_audit
  ADD COLUMN IF NOT EXISTS approved_by CHAR(36) NULL AFTER changed_by,
  ADD COLUMN IF NOT EXISTS change_reason TEXT NOT NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS effective_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER changed_at;

-- Ensure changed_by type remains spec-compatible
ALTER TABLE business_rules_audit
  MODIFY COLUMN changed_by CHAR(36) NULL;

-- Enforce min 20 chars on change_reason at insert-time
-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required

-- Recreate immutable triggers (kept explicit in this migration for idempotence)
-- TODO(pg): review trigger drop
-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required
-- TODO(pg): manual trigger conversion required

-- Recreate audit append trigger with spec columns populated
-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required
