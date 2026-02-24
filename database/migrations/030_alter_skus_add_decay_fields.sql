-- Add canonical decay fields to legacy skus table to mirror sku_master semantics

ALTER TABLE skus
  ADD COLUMN decay_consecutive_zeros SMALLINT NOT NULL DEFAULT 0 AFTER decay_weeks,
  ADD COLUMN decay_status ENUM('none','yellow_flag','alert','auto_brief','escalated')
    NOT NULL DEFAULT 'none' AFTER decay_consecutive_zeros;

-- Backfill from existing decay_weeks counter
UPDATE skus
SET decay_consecutive_zeros = decay_weeks;

