-- SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 4
-- Adds cluster_id and intent_key to faq_templates for GET /faq/templates?cluster_id=&intent_key= (Patch 4 Page 16).
-- Does not drop or remove any existing columns.

SET NAMES utf8mb4;

ALTER TABLE faq_templates ADD COLUMN IF NOT EXISTS cluster_id INT NULL;
ALTER TABLE faq_templates ADD COLUMN IF NOT EXISTS intent_key VARCHAR(64) NULL;
CREATE INDEX IF NOT EXISTS idx_faq_templates_cluster_intent ON faq_templates (cluster_id, intent_key);
