-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.1 sku_content
-- FIX: DB-04 — Add shopify_title, meta_title, meta_description per spec (additive only; legacy `title` retained)

SET NAMES utf8mb4;

ALTER TABLE sku_content
  ADD COLUMN IF NOT EXISTS shopify_title VARCHAR(500) NULL AFTER sku_id,
  ADD COLUMN IF NOT EXISTS meta_title VARCHAR(100) NULL AFTER shopify_title,
  ADD COLUMN IF NOT EXISTS meta_description VARCHAR(300) NULL AFTER meta_title;
