-- Ensure audit_log has canonical `timestamp` column (fixes "Unknown column 'timestamp' in 'order clause'")
-- Safe to run multiple times: adds column only if missing, then backfills and adds index.

DELIMITER //

DROP PROCEDURE IF EXISTS ensure_audit_log_timestamp//

CREATE PROCEDURE ensure_audit_log_timestamp()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'timestamp') = 0 THEN
    ALTER TABLE audit_log ADD COLUMN `timestamp` TIMESTAMP NULL;
    UPDATE audit_log SET `timestamp` = COALESCE(created_at, CURRENT_TIMESTAMP) WHERE `timestamp` IS NULL;
    ALTER TABLE audit_log MODIFY COLUMN `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
  END IF;
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND INDEX_NAME = 'idx_audit_time') = 0 THEN
    CREATE INDEX idx_audit_time ON audit_log(`timestamp`);
  END IF;
END//

DELIMITER ;

CALL ensure_audit_log_timestamp();
DROP PROCEDURE IF EXISTS ensure_audit_log_timestamp;
