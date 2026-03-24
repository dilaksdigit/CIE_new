-- SOURCE: Master Spec §6.4.
-- ADDITIVE: add SKU link columns. Backfill/strict FK enforcement can be done after mapping.

SET NAMES utf8mb4;

ALTER TABLE url_performance
  ADD COLUMN IF NOT EXISTS sku_id CHAR(36) NULL,
  ADD COLUMN IF NOT EXISTS week_ending DATE NULL,
  ADD COLUMN IF NOT EXISTS top_query VARCHAR(500) NULL;
