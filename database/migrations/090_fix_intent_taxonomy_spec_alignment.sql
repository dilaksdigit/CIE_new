-- SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §8.3 Intent Taxonomy Lookup Table
-- SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §4.2 The Nine Intent Taxonomy
-- SOURCE: CLAUDE.md §9 — Intent stored as ENUM matching the 9-intent taxonomy exactly
-- FIX: CHECK 2.4 — Align intent_taxonomy seed data and CHECK constraint to §8.3 authoritative nine
-- ADDITIVE: no DROP COLUMN / DROP TABLE.
-- TRIGGER NOTE: CREATE TRIGGER fails for typical app DB users when log_bin=ON (MySQL ERROR 1419).
-- This migration drops trg_intent_key_immutable so intent_key can be corrected, but does NOT recreate it.
-- Re-apply the immutability trigger from 082_intent_taxonomy_enf_spec_83.sql (lines ~175–186) as a privileged
-- MySQL user, or set log_bin_trust_function_creators per ops policy.

SET NAMES utf8mb4;

-- Drop CHECK if present (idempotent; matches pattern in 082_intent_taxonomy_enf_spec_83.sql)
SET @chk_it := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'intent_taxonomy'
      AND CONSTRAINT_NAME = 'chk_intent_key_locked'
      AND CONSTRAINT_TYPE = 'CHECK'
);
SET @sql_it := IF(@chk_it > 0,
    'ALTER TABLE intent_taxonomy DROP CHECK chk_intent_key_locked',
    'SELECT ''chk_intent_key_locked absent'' AS msg'
);
PREPARE stmt_it FROM @sql_it;
EXECUTE stmt_it;
DEALLOCATE PREPARE stmt_it;

-- Immutability would block intent_key rewrites; DROP does not require SUPER (CREATE does when binlog is on).
DROP TRIGGER IF EXISTS trg_intent_key_immutable;

-- In-place correction for pre-082 rows that still use legacy keys (unique intent_key prevents duplicates)
UPDATE intent_taxonomy SET
    intent_key = 'regulatory',
    label = 'Regulatory / Safety',
    definition = 'User needs safety, compliance, or regulatory detail',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_key = 'safety_compliance';

UPDATE intent_taxonomy SET
    intent_key = 'replacement',
    label = 'Replacement / Refill',
    definition = 'User needs a replacement part or consumable',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_key = 'bulk_trade'
  AND intent_id = 9;

-- Authoritative §8.3 rows by intent_id 1–9 (idempotent; stable intent_id values)
UPDATE intent_taxonomy SET
    intent_key = 'problem_solving',
    label = 'Problem-Solving',
    definition = 'User has a problem, needs product to solve it',
    tier_access = '["hero","support","harvest"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 1;

UPDATE intent_taxonomy SET
    intent_key = 'comparison',
    label = 'Comparison',
    definition = 'User evaluating alternatives',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 2;

UPDATE intent_taxonomy SET
    intent_key = 'compatibility',
    label = 'Compatibility',
    definition = 'User confirming fit with existing setup',
    tier_access = '["hero","support","harvest"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 3;

UPDATE intent_taxonomy SET
    intent_key = 'specification',
    label = 'Specification',
    definition = 'User needs technical details',
    tier_access = '["hero","support","harvest"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 4;

UPDATE intent_taxonomy SET
    intent_key = 'installation',
    label = 'Installation / How-To',
    definition = 'User needs help installing or setting up',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 5;

UPDATE intent_taxonomy SET
    intent_key = 'troubleshooting',
    label = 'Troubleshooting',
    definition = 'User diagnosing or fixing a fault or poor performance',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 6;

UPDATE intent_taxonomy SET
    intent_key = 'inspiration',
    label = 'Inspiration / Style',
    definition = 'User browsing for ideas and style guidance',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 7;

UPDATE intent_taxonomy SET
    intent_key = 'regulatory',
    label = 'Regulatory / Safety',
    definition = 'User needs safety, compliance, or regulatory detail',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 8;

UPDATE intent_taxonomy SET
    intent_key = 'replacement',
    label = 'Replacement / Refill',
    definition = 'User needs a replacement part or consumable',
    tier_access = '["hero","support"]',
    updated_at = CURRENT_TIMESTAMP
WHERE intent_id = 9;

-- Insert any missing intent_id 1–9 (e.g. partial seed); does not delete existing rows
INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 1, 'problem_solving', 'Problem-Solving', 'User has a problem, needs product to solve it', '["hero","support","harvest"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 1);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 2, 'comparison', 'Comparison', 'User evaluating alternatives', '["hero","support"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 2);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 3, 'compatibility', 'Compatibility', 'User confirming fit with existing setup', '["hero","support","harvest"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 3);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 4, 'specification', 'Specification', 'User needs technical details', '["hero","support","harvest"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 4);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 5, 'installation', 'Installation / How-To', 'User needs help installing or setting up', '["hero","support"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 5);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 6, 'troubleshooting', 'Troubleshooting', 'User diagnosing or fixing a fault or poor performance', '["hero","support"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 6);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 7, 'inspiration', 'Inspiration / Style', 'User browsing for ideas and style guidance', '["hero","support"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 7);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 8, 'regulatory', 'Regulatory / Safety', 'User needs safety, compliance, or regulatory detail', '["hero","support"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 8);

INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
SELECT UUID(), 9, 'replacement', 'Replacement / Refill', 'User needs a replacement part or consumable', '["hero","support"]'
WHERE NOT EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 9);

-- Re-add CHECK (§8.3 nine keys only) if absent — idempotent on re-run
SET @chk_end := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'intent_taxonomy'
      AND CONSTRAINT_NAME = 'chk_intent_key_locked'
      AND CONSTRAINT_TYPE = 'CHECK'
);
SET @sql_chk := IF(@chk_end = 0,
    'ALTER TABLE intent_taxonomy ADD CONSTRAINT chk_intent_key_locked CHECK (intent_key IN (''problem_solving'',''comparison'',''compatibility'',''specification'',''installation'',''troubleshooting'',''inspiration'',''regulatory'',''replacement''))',
    'SELECT ''chk_intent_key_locked already present'' AS msg'
);
PREPARE stmt_chk FROM @sql_chk;
EXECUTE stmt_chk;
DEALLOCATE PREPARE stmt_chk;

-- Post-migration (no is_active column on intent_taxonomy in canonical schema):
-- SELECT intent_key, label, tier_access FROM intent_taxonomy ORDER BY intent_id;
-- Expect 9 rows; invalid key INSERT must fail on chk_intent_key_locked.
