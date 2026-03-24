-- SOURCE: CIE_v231_Developer_Build_Pack §1.1 ERD — ai_golden_queries.intent_type (FK) → intent_taxonomy
-- SOURCE: CIE_v231_Developer_Build_Pack §ai_golden_queries — "intent_type SMALLINT NOT NULL REFERENCES intent_taxonomy(intent_id)"
-- SOURCE: CIE_Master_Developer_Build_Spec §6 — Foreign key constraints enforced at the database level
-- SOURCE: CLAUDE.md §9 — Foreign keys enforced on all relationships
-- FIX: CHECK 2.8 — Resolve ai_golden_queries FK incorrectly marked BLOCKED in 034
-- NOTE: 034 targeted legacy intents(id CHAR(36)); correct target is intent_taxonomy(intent_id SMALLINT).

SET NAMES utf8mb4;

SET @has_col_intent_type := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ai_golden_queries'
      AND COLUMN_NAME = 'intent_type'
);

SET @has_col_intent_type_id := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ai_golden_queries'
      AND COLUMN_NAME = 'intent_type_id'
);

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ai_golden_queries'
      AND REFERENCED_TABLE_NAME = 'intent_taxonomy'
      AND REFERENCED_COLUMN_NAME = 'intent_id'
      AND COLUMN_NAME IN ('intent_type', 'intent_type_id')
);

-- Orphan cleanup before adding FK (supports either canonical or legacy-suffixed column name)
SET @sql_orphans := IF(@has_col_intent_type > 0,
    'DELETE FROM ai_golden_queries WHERE intent_type NOT IN (SELECT intent_id FROM intent_taxonomy)',
    IF(@has_col_intent_type_id > 0,
        'DELETE FROM ai_golden_queries WHERE intent_type_id NOT IN (SELECT intent_id FROM intent_taxonomy)',
        'SELECT ''skip orphan cleanup (no intent_type/int_type_id column)'' AS msg'
    )
);
PREPARE stmt_orphans FROM @sql_orphans;
EXECUTE stmt_orphans;
DEALLOCATE PREPARE stmt_orphans;

-- Add FK to canonical intent_taxonomy intent_id
SET @sql_fk := IF(@fk_exists > 0,
    'SELECT ''ai_golden_queries -> intent_taxonomy FK already present'' AS msg',
    IF(@has_col_intent_type > 0,
        'ALTER TABLE ai_golden_queries ADD CONSTRAINT fk_golden_queries_intent_taxonomy FOREIGN KEY (intent_type) REFERENCES intent_taxonomy(intent_id)',
        IF(@has_col_intent_type_id > 0,
            'ALTER TABLE ai_golden_queries ADD CONSTRAINT fk_golden_queries_intent_taxonomy FOREIGN KEY (intent_type_id) REFERENCES intent_taxonomy(intent_id)',
            'SELECT ''skip FK add (no intent_type/int_type_id column)'' AS msg'
        )
    )
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
