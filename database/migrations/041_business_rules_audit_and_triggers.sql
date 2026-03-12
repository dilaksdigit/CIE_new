SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.1
-- CIE v2.3.2 – business_rules_audit: append-only history. NO UPDATE/DELETE allowed.

CREATE TABLE IF NOT EXISTS business_rules_audit (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    rule_key VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    old_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    new_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    INDEX idx_bra_rule_key (rule_key),
    INDEX idx_bra_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MySQL: append-only. Spec: UPDATE must fail (SIGNAL). DELETE prevented.
DROP TRIGGER IF EXISTS tr_bra_no_update;
DROP TRIGGER IF EXISTS tr_bra_no_delete;
DELIMITER //
CREATE TRIGGER tr_bra_no_update
BEFORE UPDATE ON business_rules_audit
FOR EACH ROW
BEGIN
    -- SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.1 — audit row UPDATE must fail
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'business_rules_audit is immutable: UPDATE not permitted';
END//
CREATE TRIGGER tr_bra_no_delete
BEFORE DELETE ON business_rules_audit
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'business_rules_audit is append-only: DELETE not allowed';
END//
DELIMITER ;

-- Audit trail: when business_rules is updated, append to business_rules_audit.
DROP TRIGGER IF EXISTS tr_business_rules_after_update;
DELIMITER //
CREATE TRIGGER tr_business_rules_after_update
AFTER UPDATE ON business_rules
FOR EACH ROW
BEGIN
    INSERT INTO business_rules_audit (id, rule_key, old_value, new_value, changed_at, changed_by)
    VALUES (UUID(), OLD.rule_key, OLD.value, NEW.value, NOW(), NULL);
END//
DELIMITER ;
