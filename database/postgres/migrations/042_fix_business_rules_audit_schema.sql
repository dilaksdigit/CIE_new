-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5.1

ALTER TABLE business_rules_audit
    ADD COLUMN IF NOT EXISTS approved_by CHAR(36) NULL,
    ADD COLUMN IF NOT EXISTS change_reason TEXT NOT NULL,
    ADD COLUMN IF NOT EXISTS effective_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Rename legacy id -> audit_id only when legacy column exists
SET @has_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'business_rules_audit'
      AND COLUMN_NAME = 'id'
);
SET @has_audit_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'business_rules_audit'
      AND COLUMN_NAME = 'audit_id'
);
SET @rename_sql := IF(@has_id = 1 AND @has_audit_id = 0,
    'ALTER TABLE business_rules_audit CHANGE COLUMN id audit_id CHAR(36) NOT NULL',
    'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required
