-- 036_ai_golden_queries_canonical_not_null.sql
-- Enforces canonical NOT NULL constraints on ai_golden_queries.

ALTER TABLE ai_golden_queries
    MODIFY COLUMN question_id VARCHAR(20) NOT NULL,
    MODIFY COLUMN category TEXT NOT NULL,
    MODIFY COLUMN question_text VARCHAR(500) NOT NULL,
    MODIFY COLUMN intent_type_id SMALLINT NULL,
    MODIFY COLUMN query_family TEXT NOT NULL,
    MODIFY COLUMN target_tier TEXT NOT NULL,
    MODIFY COLUMN target_skus JSON NULL,
    MODIFY COLUMN success_criteria VARCHAR(300) NOT NULL,
    MODIFY COLUMN locked_until DATE NULL,
    MODIFY COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE,
    MODIFY COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
