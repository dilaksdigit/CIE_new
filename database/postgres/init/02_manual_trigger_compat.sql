-- Manual PostgreSQL trigger compatibility layer for critical immutable tables.

-- audit_log immutability
CREATE OR REPLACE FUNCTION fn_block_audit_log_update_delete()
RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'audit_log is immutable: % not allowed', TG_OP;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tr_audit_log_no_update ON audit_log;
DROP TRIGGER IF EXISTS tr_audit_log_no_delete ON audit_log;

CREATE TRIGGER tr_audit_log_no_update
BEFORE UPDATE ON audit_log
FOR EACH ROW
EXECUTE FUNCTION fn_block_audit_log_update_delete();

CREATE TRIGGER tr_audit_log_no_delete
BEFORE DELETE ON audit_log
FOR EACH ROW
EXECUTE FUNCTION fn_block_audit_log_update_delete();

-- business_rules_audit immutability
CREATE OR REPLACE FUNCTION fn_block_business_rules_audit_update_delete()
RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'business_rules_audit is immutable: % not allowed', TG_OP;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tr_bra_no_update ON business_rules_audit;
DROP TRIGGER IF EXISTS tr_bra_no_delete ON business_rules_audit;

CREATE TRIGGER tr_bra_no_update
BEFORE UPDATE ON business_rules_audit
FOR EACH ROW
EXECUTE FUNCTION fn_block_business_rules_audit_update_delete();

CREATE TRIGGER tr_bra_no_delete
BEFORE DELETE ON business_rules_audit
FOR EACH ROW
EXECUTE FUNCTION fn_block_business_rules_audit_update_delete();

