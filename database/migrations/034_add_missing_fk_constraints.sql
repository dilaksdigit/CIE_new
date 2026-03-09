-- =================================================================
-- Migration 034: Add missing FK constraints per CHECK 2.8
-- SOURCE: CLAUDE.md §9 + CIE_Master_Developer_Build_Spec.docx §6
-- =================================================================
-- "Foreign keys enforced on all relationships."
-- "Foreign key constraints enforced at the database level.
--  No orphaned records permitted."
--
-- The LLMSpace2 validation report identified 7 relationships
-- enforced only at application level. This migration brings them
-- into compliance at the database level.
-- =================================================================

SET NAMES utf8mb4;

BEGIN;

-- -----------------------------------------------------------------
-- FIX 1 — clusters.primary_intent_id → intents(id)
-- SOURCE: Validation report, RELATIONSHIP 1
-- DEFECT IN: 003_create_clusters_table.sql
-- -----------------------------------------------------------------
ALTER TABLE clusters
  ADD CONSTRAINT fk_clusters_primary_intent
  FOREIGN KEY (primary_intent_id) REFERENCES intents(id);

-- -----------------------------------------------------------------
-- FIX 2 — staff_effort_logs.category_id
-- SOURCE: Validation report, RELATIONSHIP 2
-- DEFECT IN: 016_create_staff_effort_logs_table.sql
-- -----------------------------------------------------------------
-- TODO: FK for staff_effort_logs.category_id is BLOCKED.
-- Parent table not defined in source documents.
-- Architect must clarify before this constraint can be added.
-- SOURCE: CLAUDE.md §9 | Validation report RELATIONSHIP 2

-- -----------------------------------------------------------------
-- FIX 3 — ai_golden_queries.intent_type_id → intents(id)
-- SOURCE: Validation report, RELATIONSHIP 3
-- DEFECT IN: 023_create_ai_audit_tables.sql
-- -----------------------------------------------------------------
-- TODO: FK for ai_golden_queries.intent_type_id is BLOCKED.
-- TYPE MISMATCH: intent_type_id is SMALLINT (023), intents.id is CHAR(36).
-- This ALTER would fail at the database level.
-- Additionally, migration 028 attempted to rename this column to
-- intent_type and add FK to intent_taxonomy(intent_id) (SMALLINT).
-- Migration 036 still references intent_type_id, making schema
-- state ambiguous. Architect must resolve before applying.
-- SOURCE: CLAUDE.md §9 | Validation report RELATIONSHIP 3
--
-- ALTER TABLE ai_golden_queries
--   ADD CONSTRAINT fk_ai_golden_queries_intent_type
--   FOREIGN KEY (intent_type_id) REFERENCES intents(id);

-- -----------------------------------------------------------------
-- FIX 4 — validation_retry_queue.sku_id → skus(id)
-- SOURCE: Validation report, RELATIONSHIP 4
-- DEFECT IN: 027_create_v2_3_2_patch_tables.sql
--            033_create_validation_retry_queue.sql
-- -----------------------------------------------------------------
ALTER TABLE validation_retry_queue
  ADD CONSTRAINT fk_validation_retry_queue_sku
  FOREIGN KEY (sku_id) REFERENCES skus(id);

-- -----------------------------------------------------------------
-- FIX 5 — sku_faqs.sku_id → skus(id)
-- SOURCE: Validation report, RELATIONSHIP 5
-- DEFECT IN: 027_create_v2_3_2_patch_tables.sql
-- -----------------------------------------------------------------
ALTER TABLE sku_faqs
  ADD CONSTRAINT fk_sku_faqs_sku
  FOREIGN KEY (sku_id) REFERENCES skus(id);

-- -----------------------------------------------------------------
-- FIX 6 — sku_faqs.template_id → faq_templates(id)
-- SOURCE: Validation report, RELATIONSHIP 6
-- DEFECT IN: 027_create_v2_3_2_patch_tables.sql
-- NOTE: Original migration commented out the FK with the note
-- "Template FK is optional to allow ad-hoc FAQs". This exception
-- is NOT permitted by CLAUDE.md §9 ("all relationships"). The
-- column is nullable, so NULL values (ad-hoc FAQs) remain valid
-- but any non-NULL template_id must resolve to a real row.
-- -----------------------------------------------------------------
ALTER TABLE sku_faqs
  ADD CONSTRAINT fk_sku_faqs_template
  FOREIGN KEY (template_id) REFERENCES faq_templates(id);

-- -----------------------------------------------------------------
-- FIX 7 — cluster_review_log.cluster_id → clusters(id)
-- SOURCE: Validation report, RELATIONSHIP 7
-- DEFECT IN: 027_create_v2_3_2_patch_tables.sql
-- -----------------------------------------------------------------
ALTER TABLE cluster_review_log
  ADD CONSTRAINT fk_cluster_review_log_cluster
  FOREIGN KEY (cluster_id) REFERENCES clusters(id);

-- -----------------------------------------------------------------
-- Migration metadata
-- -----------------------------------------------------------------
-- TODO: No migrations table exists in the schema. No CREATE TABLE
-- migrations or INSERT INTO migrations found in any migration file.
-- Verify the migration tracking mechanism before uncommenting.
--
-- INSERT INTO migrations (version, description, applied_at)
-- VALUES ('034', 'Add missing FK constraints per CHECK 2.8', NOW());

COMMIT;
