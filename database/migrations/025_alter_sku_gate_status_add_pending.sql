-- Add 'pending' status to sku_gate_status.status enum
-- v2.3.2 Fail-soft vector validation support

ALTER TABLE sku_gate_status
MODIFY COLUMN status ENUM('pass','fail','pending','not_applicable') NOT NULL;

