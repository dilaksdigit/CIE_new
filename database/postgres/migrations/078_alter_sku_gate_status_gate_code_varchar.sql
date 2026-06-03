-- Allow sku_gate_status to store full GateType values (G1_BASIC_INFO, G2_INTENT, etc.)
-- used by GateValidator and SkuController.

SET @tbl_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_gate_status'
);

SET @sql = IF(@tbl_exists > 0,
    'ALTER TABLE sku_gate_status MODIFY COLUMN gate_code VARCHAR(50) NOT NULL',
    'SELECT ''sku_gate_status not found'' AS msg'
);

-- TODO(pg): removed MySQL PREPARE/EXECUTE block;
