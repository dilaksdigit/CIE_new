-- 036_ai_golden_queries_canonical_not_null.sql
-- Enforces canonical NOT NULL constraints on ai_golden_queries.

SET NAMES utf8mb4;
ALTER TABLE ai_golden_queries
    MODIFY COLUMN question_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN category ENUM('cables', 'lampshades', 'bulbs', 'pendants', 'floor_lamps', 'ceiling_lights', 'accessories') NOT NULL,
    MODIFY COLUMN question_text VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN intent_type_id SMALLINT NULL,
    MODIFY COLUMN query_family ENUM('primary', 'secondary', 'other') NOT NULL,
    MODIFY COLUMN target_tier ENUM('hero', 'support') NOT NULL,
    MODIFY COLUMN target_skus JSON NULL,
    MODIFY COLUMN success_criteria VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN locked_until DATE NULL,
    MODIFY COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE,
    MODIFY COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
