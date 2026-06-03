-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5.1
CREATE TABLE IF NOT EXISTS business_rules (
    rule_key        VARCHAR(100) PRIMARY KEY,
    rule_value      TEXT NOT NULL,
    data_type       TEXT NOT NULL,
    module          VARCHAR(50) NOT NULL,
    label           VARCHAR(200) NOT NULL,
    description     TEXT,
    unit            VARCHAR(50),
    min_value       TEXT,
    max_value       TEXT,
    approval_level  TEXT NOT NULL DEFAULT 'single',
    approver_roles  VARCHAR(200) NOT NULL,
    is_locked       BOOLEAN DEFAULT FALSE,
    last_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_changed_by CHAR(36),
    effective_from  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5.1
CREATE TABLE IF NOT EXISTS business_rules_audit (
    audit_id        CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
    rule_key        VARCHAR(100) NOT NULL,
    old_value       TEXT,
    new_value       TEXT NOT NULL,
    changed_by      CHAR(36) NOT NULL,
    approved_by     CHAR(36),
    change_reason   TEXT NOT NULL,
    changed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    effective_from  TIMESTAMP NOT NULL
);

-- Immutable: block UPDATE and DELETE on business_rules_audit at trigger level
-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5.1
CREATE TRIGGER block_bra_update
BEFORE UPDATE ON business_rules_audit
FOR EACH ROW RAISE EXCEPTION 'UPDATE not permitted on business_rules_audit';

CREATE TRIGGER block_bra_delete
BEFORE DELETE ON business_rules_audit
FOR EACH ROW RAISE EXCEPTION 'DELETE not permitted on business_rules_audit';
