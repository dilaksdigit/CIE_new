-- 035_audit_log_canonical_not_null_and_indexes.sql
-- Enforces canonical NOT NULL constraints and indexes on audit_log.

ALTER TABLE audit_log
    MODIFY COLUMN user_id VARCHAR(100) NOT NULL,
    MODIFY COLUMN entity_type VARCHAR(50) NOT NULL,
    MODIFY COLUMN entity_id VARCHAR(50) NOT NULL,
    CHANGE COLUMN created_at created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN action VARCHAR(50) NOT NULL,
    MODIFY COLUMN field_name VARCHAR(100) NULL;

CREATE INDEX IF NOT EXISTS idx_audit_log_entity_canonical
    ON audit_log (entity_type, entity_id, created_at);

CREATE INDEX IF NOT EXISTS idx_audit_log_actor_canonical
    ON audit_log (actor_id, created_at);
