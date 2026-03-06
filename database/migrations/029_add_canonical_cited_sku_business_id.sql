-- 029_add_canonical_cited_sku_business_id.sql
-- Adds canonical cited_sku_business_id column to ai_audit_results for Cabridge SKU linkage.

SET NAMES utf8mb4;
ALTER TABLE ai_audit_results
ADD COLUMN cited_sku_business_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

CREATE INDEX idx_ai_audit_results_cited_sku_business_id
ON ai_audit_results (cited_sku_business_id);
