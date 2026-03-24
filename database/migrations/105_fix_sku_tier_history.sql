-- SOURCE: Build Pack §1.2 parity fields.

SET NAMES utf8mb4;

ALTER TABLE sku_tier_history
  ADD COLUMN IF NOT EXISTS reason ENUM('erp_sync','manual_override','auto_promote','quarterly_review') NULL,
  ADD COLUMN IF NOT EXISTS approved_by VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS second_approver VARCHAR(100) NULL;
