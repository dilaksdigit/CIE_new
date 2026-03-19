-- 026_alter_audit_log_to_canonical.sql
-- Transforms the legacy audit_log table into the canonical form used by v2.3.2.
-- Compatible with MySQL 5.7 (no ADD COLUMN IF NOT EXISTS / CREATE INDEX IF NOT EXISTS).

SET NAMES utf8mb4;

DELIMITER //

DROP PROCEDURE IF EXISTS ensure_audit_log_canonical_columns//

CREATE PROCEDURE ensure_audit_log_canonical_columns()
BEGIN
  -- actor_id
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'actor_id') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN actor_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
  END IF;
  -- actor_role
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'actor_role') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN actor_role VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
  END IF;
  -- actor_ip
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'actor_ip') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN actor_ip VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
  END IF;
  -- actor_user_agent
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'actor_user_agent') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN actor_user_agent TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
  END IF;
  -- changed_at
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'changed_at') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN changed_at TIMESTAMP NULL;
  END IF;
  -- before_value
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'before_value') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN before_value JSON NULL;
  END IF;
  -- after_value
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'after_value') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN after_value JSON NULL;
  END IF;
END//

DELIMITER ;

CALL ensure_audit_log_canonical_columns();
DROP PROCEDURE IF EXISTS ensure_audit_log_canonical_columns;

-- REMOVED: UPDATE on audit_log is prohibited per CIE_v231_Developer_Build_Pack.pdf §7.2
-- audit_log is immutable. Historical actor_id values remain as originally written.
-- No backfill is permitted under any circumstance.

-- Narrow entity_id to VARCHAR(50) to match canonical entity identifiers
ALTER TABLE audit_log
    MODIFY COLUMN entity_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- Ensure indexes exist for canonical usage (only if missing, for MySQL 5.7)
DELIMITER //

DROP PROCEDURE IF EXISTS ensure_audit_log_canonical_indexes//

CREATE PROCEDURE ensure_audit_log_canonical_indexes()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND INDEX_NAME = 'idx_audit_log_actor') = 0 THEN
    CREATE INDEX idx_audit_log_actor ON audit_log (actor_id, changed_at);
  END IF;
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND INDEX_NAME = 'idx_audit_log_entity_canonical') = 0 THEN
    CREATE INDEX idx_audit_log_entity_canonical ON audit_log (entity_type, entity_id, changed_at);
  END IF;
END//

DELIMITER ;

CALL ensure_audit_log_canonical_indexes();
DROP PROCEDURE IF EXISTS ensure_audit_log_canonical_indexes;