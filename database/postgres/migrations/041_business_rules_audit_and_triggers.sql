-- SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.1
-- CIE v2.3.2 – business_rules_audit: append-only history. NO UPDATE/DELETE allowed.

CREATE TABLE IF NOT EXISTS business_rules_audit (
    id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
    rule_key VARCHAR(100) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by VARCHAR(100) NULL
);

-- MySQL: append-only. Spec: UPDATE must fail (SIGNAL). DELETE prevented.
-- TODO(pg): review trigger drop
-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required
-- TODO(pg): manual trigger conversion required

-- Audit trail: when business_rules is updated, append to business_rules_audit.
-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required
