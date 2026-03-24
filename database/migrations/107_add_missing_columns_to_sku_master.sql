-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.1 sku_master
-- FIX: DB-02 — Add spec-required columns missing from sku_master (additive only)

SET NAMES utf8mb4;

ALTER TABLE sku_master
  ADD COLUMN IF NOT EXISTS product_name VARCHAR(500) NOT NULL DEFAULT '' AFTER sku_id,
  ADD COLUMN IF NOT EXISTS shopify_url VARCHAR(1000) NULL AFTER cluster_id,
  ADD COLUMN IF NOT EXISTS shopify_product_id VARCHAR(50) NULL AFTER shopify_url;
