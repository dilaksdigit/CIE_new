-- Allow sku_gate_status to be populated by GateValidator using skus.sku_code
-- without requiring a row in sku_master.

SET @fk_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sku_gate_status'
      AND CONSTRAINT_NAME = 'fk_gate_status_sku_master'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE sku_gate_status DROP FOREIGN KEY fk_gate_status_sku_master',
    'SELECT ''fk_gate_status_sku_master not found'' AS msg'
);

-- TODO(pg): removed MySQL PREPARE/EXECUTE block;
