-- Align ai_golden_queries intent type with canonical spec
-- - Rename intent_type_id -> intent_type
-- - Add FK to intent_taxonomy(intent_id)

ALTER TABLE ai_golden_queries
  CHANGE COLUMN intent_type_id intent_type SMALLINT;

ALTER TABLE ai_golden_queries
  ADD CONSTRAINT fk_ai_golden_queries_intent_type
    FOREIGN KEY (intent_type) REFERENCES intent_taxonomy(intent_id);

