-- 035_audit_log_canonical_not_null_and_indexes.sql
-- Enforces canonical NOT NULL constraints and indexes on audit_log.

SET NAMES utf8mb4;
ALTER TABLE audit_log
    MODIFY COLUMN user_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN entity_type VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN entity_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    CHANGE COLUMN created_at created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN action VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN field_name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

CREATE INDEX IF NOT EXISTS idx_audit_log_entity_canonical
    ON audit_log (entity_type, entity_id, created_at);

CREATE INDEX IF NOT EXISTS idx_audit_log_actor_canonical
    ON audit_log (actor_id, created_at);
