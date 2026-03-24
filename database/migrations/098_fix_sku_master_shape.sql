-- SOURCE: Master Spec §6.1 + Build Pack business-key intent.
-- ADDITIVE: add missing columns; keep existing surrogate PK for compatibility.

SET NAMES utf8mb4;

ALTER TABLE sku_master
  ADD COLUMN IF NOT EXISTS maturity_level ENUM('bronze','silver','gold') DEFAULT 'bronze',
  ADD COLUMN IF NOT EXISTS decay_weeks INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
