-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5.1
-- Fix DB-02: Align business_rules_audit schema contract without changing app logic.

SET NAMES utf8mb4;

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
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add missing spec columns
ALTER TABLE business_rules_audit
  ADD COLUMN IF NOT EXISTS approved_by CHAR(36) NULL AFTER changed_by,
  ADD COLUMN IF NOT EXISTS change_reason TEXT NOT NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS effective_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER changed_at;

-- Ensure changed_by type remains spec-compatible
ALTER TABLE business_rules_audit
  MODIFY COLUMN changed_by CHAR(36) NULL;

-- Enforce min 20 chars on change_reason at insert-time
DROP TRIGGER IF EXISTS tr_bra_validate_insert;
DELIMITER //
CREATE TRIGGER tr_bra_validate_insert
BEFORE INSERT ON business_rules_audit
FOR EACH ROW
BEGIN
  IF CHAR_LENGTH(TRIM(COALESCE(NEW.change_reason, ''))) < 20 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'business_rules_audit.change_reason must be at least 20 characters';
  END IF;
END//
DELIMITER ;

-- Recreate immutable triggers (kept explicit in this migration for idempotence)
DROP TRIGGER IF EXISTS tr_bra_no_update;
DROP TRIGGER IF EXISTS tr_bra_no_delete;
DELIMITER //
CREATE TRIGGER tr_bra_no_update
BEFORE UPDATE ON business_rules_audit
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'business_rules_audit is immutable: UPDATE not permitted';
END//
CREATE TRIGGER tr_bra_no_delete
BEFORE DELETE ON business_rules_audit
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'business_rules_audit is append-only: DELETE not allowed';
END//
DELIMITER ;

-- Recreate audit append trigger with spec columns populated
DROP TRIGGER IF EXISTS tr_business_rules_after_update;
DELIMITER //
CREATE TRIGGER tr_business_rules_after_update
AFTER UPDATE ON business_rules
FOR EACH ROW
BEGIN
  INSERT INTO business_rules_audit
  (
    audit_id,
    rule_key,
    old_value,
    new_value,
    changed_by,
    approved_by,
    change_reason,
    changed_at,
    effective_from
  )
  VALUES
  (
    UUID(),
    OLD.rule_key,
    OLD.rule_value,
    NEW.rule_value,
    NEW.last_changed_by,
    NULL,
    'Business rule updated via admin API',
    NOW(),
    COALESCE(NEW.effective_from, NOW())
  );
END//
DELIMITER ;
