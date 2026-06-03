-- SOURCE: Build Pack §1.2 parity fields.

ALTER TABLE sku_tier_history
  ADD COLUMN IF NOT EXISTS reason TEXT NULL,
  ADD COLUMN IF NOT EXISTS approved_by VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS second_approver VARCHAR(100) NULL;
