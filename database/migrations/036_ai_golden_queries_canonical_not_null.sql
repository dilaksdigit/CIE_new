-- ===================================================================
-- CIE v2.3.1 – ai_golden_queries canonical NOT NULL (Document 1)
-- ===================================================================
-- - intent_type NOT NULL (FK to intent_taxonomy.intent_id)
-- - success_criteria VARCHAR(300) NOT NULL
-- - locked_until DATE NOT NULL
-- ===================================================================

-- 1) Backfill intent_type where NULL (use intent_id 1 as default)
UPDATE ai_golden_queries
SET intent_type = 1
WHERE intent_type IS NULL;

-- 2) Backfill success_criteria where NULL
UPDATE ai_golden_queries
SET success_criteria = ''
WHERE success_criteria IS NULL;

-- 3) Backfill locked_until where NULL (far-future = effectively unlocked)
UPDATE ai_golden_queries
SET locked_until = '2099-12-31'
WHERE locked_until IS NULL;

-- 4) Apply NOT NULL constraints per Document 1
ALTER TABLE ai_golden_queries
  MODIFY COLUMN intent_type SMALLINT NOT NULL,
  MODIFY COLUMN success_criteria VARCHAR(300) NOT NULL,
  MODIFY COLUMN locked_until DATE NOT NULL;
