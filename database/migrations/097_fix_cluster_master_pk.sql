-- SOURCE: Master Spec §6.2 (cluster_id as business PK), MySQL adaptation.
-- SAFETY: only switch PK if no FK references cluster_master.id.

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS p097_fix_cluster_master_pk;
DELIMITER //
CREATE PROCEDURE p097_fix_cluster_master_pk()
BEGIN
  DECLARE id_fk_refs INT DEFAULT 0;
  DECLARE pk_col VARCHAR(64) DEFAULT '';

  SELECT COUNT(*)
    INTO id_fk_refs
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME = 'cluster_master'
    AND REFERENCED_COLUMN_NAME = 'id';

  SELECT COLUMN_NAME
    INTO pk_col
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cluster_master'
    AND INDEX_NAME = 'PRIMARY'
  LIMIT 1;

  IF id_fk_refs = 0 AND pk_col <> 'cluster_id' THEN
    ALTER TABLE cluster_master DROP PRIMARY KEY;
    ALTER TABLE cluster_master ADD PRIMARY KEY (cluster_id);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cluster_master' AND COLUMN_NAME = 'is_active'
  ) THEN
    ALTER TABLE cluster_master ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cluster_master' AND COLUMN_NAME = 'created_by'
  ) THEN
    ALTER TABLE cluster_master ADD COLUMN created_by CHAR(36) NULL;
  END IF;
END//
DELIMITER ;
CALL p097_fix_cluster_master_pk();
DROP PROCEDURE IF EXISTS p097_fix_cluster_master_pk;
