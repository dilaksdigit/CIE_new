-- 026_alter_audit_log_to_canonical.sql
-- Transforms the legacy audit_log table into the canonical form used by v2.3.2.

SET NAMES utf8mb4;
ALTER TABLE audit_log
    ADD COLUMN IF NOT EXISTS actor_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS actor_role VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS actor_ip VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS actor_user_agent TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS changed_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS before_value JSON NULL,
    ADD COLUMN IF NOT EXISTS after_value JSON NULL;

-- REMOVED: UPDATE on audit_log is prohibited per CIE_v231_Developer_Build_Pack.pdf §7.2
-- audit_log is immutable. Historical actor_id values remain as originally written.
-- No backfill is permitted under any circumstance.

-- Narrow entity_id to VARCHAR(50) to match canonical entity identifiers
ALTER TABLE audit_log
    MODIFY COLUMN entity_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- Ensure indexes exist for canonical usage
CREATE INDEX IF NOT EXISTS idx_audit_log_actor
    ON audit_log (actor_id, changed_at);

CREATE INDEX IF NOT EXISTS idx_audit_log_entity_canonical
    ON audit_log (entity_type, entity_id, changed_at);
