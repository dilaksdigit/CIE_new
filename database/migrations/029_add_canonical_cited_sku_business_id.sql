-- Add canonical business-id FK for cited SKUs in ai_audit_results
-- This is additive and keeps the existing FK to skus(id) untouched.

ALTER TABLE ai_audit_results
  ADD COLUMN cited_sku_business_id VARCHAR(50) NULL AFTER cited_sku_id;

ALTER TABLE ai_audit_results
  ADD CONSTRAINT fk_ai_audit_results_cited_sku_business_id
    FOREIGN KEY (cited_sku_business_id)
    REFERENCES sku_master(sku_id)
    ON DELETE SET NULL;

