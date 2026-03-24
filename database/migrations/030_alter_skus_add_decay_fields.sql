-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.1
-- FIX: DEC-04 — Correct malformed ALTER syntax; keep legacy skus table aligned.

ALTER TABLE skus
  ADD COLUMN IF NOT EXISTS decay_consecutive_zeros SMALLINT NOT NULL DEFAULT 0 AFTER decay_weeks,
  ADD COLUMN IF NOT EXISTS decay_status ENUM('none','yellow_flag','alert','auto_brief','escalated') NOT NULL DEFAULT 'none' AFTER decay_consecutive_zeros;

-- Backfill from existing decay_weeks counter
UPDATE skus
SET decay_consecutive_zeros = COALESCE(decay_weeks, 0)
WHERE decay_consecutive_zeros = 0;

