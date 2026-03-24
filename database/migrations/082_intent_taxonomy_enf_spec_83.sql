-- SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — realign locked 9-intent taxonomy + FK-safe SKU remapping
-- ADDITIVE: drops/re-adds CHECK and immutability trigger only to allow controlled taxonomy row updates; no columns dropped.
SET NAMES utf8mb4;

-- ── 1. Allow intent_taxonomy row updates (CHECK + immutability trigger) ───────
ALTER TABLE intent_taxonomy DROP CHECK chk_intent_key_locked;

DROP TRIGGER IF EXISTS trg_intent_key_immutable;

-- ── 2. Remap canonical smallint intent_id on SKU tables (preserve semantics) ─
-- Old seed id 5=inspiration → new id 7; id 6=installation → new id 5; id 7=safety → new id 8;
-- id 8=replacement → new id 9; id 9=bulk_trade → new id 2 (comparison; no §8.3 equivalent — architect may refine).
-- Idempotent: only when legacy layout is present (installation at intent_id 6 per pre-§8.3 seed).
UPDATE sku_master SET primary_intent_id = CASE primary_intent_id
    WHEN 5 THEN 7
    WHEN 6 THEN 5
    WHEN 7 THEN 8
    WHEN 8 THEN 9
    WHEN 9 THEN 2
    ELSE primary_intent_id
END
WHERE primary_intent_id IN (5, 6, 7, 8, 9)
  AND EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 6 AND intent_key = 'installation');

UPDATE sku_secondary_intents SET intent_id = CASE intent_id
    WHEN 5 THEN 7
    WHEN 6 THEN 5
    WHEN 7 THEN 8
    WHEN 8 THEN 9
    WHEN 9 THEN 2
    ELSE intent_id
END
WHERE intent_id IN (5, 6, 7, 8, 9)
  AND EXISTS (SELECT 1 FROM intent_taxonomy WHERE intent_id = 6 AND intent_key = 'installation');

-- ── 3. Replace intent_taxonomy rows in place (intent_id 1–9 stable) ───────────
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

-- ── 4. String-key columns (FAQ, etc.) — legacy API keys → §8.3 keys ───────────
UPDATE faq_templates SET intent_key = 'regulatory' WHERE intent_key = 'safety_compliance';
UPDATE faq_templates SET intent_key = 'comparison' WHERE intent_key = 'bulk_trade';

-- ── 5. Laravel `intents` table: relax CHECK, rename keys, restore CHECK ───────
ALTER TABLE intents DROP CHECK chk_intent_name_locked;

UPDATE intents SET
    name = 'regulatory',
    display_name = 'Regulatory / Safety',
    description = 'User needs safety, compliance, or regulatory detail',
    sort_order = 8
WHERE name = 'safety_compliance';

UPDATE intents SET
    name = 'troubleshooting',
    display_name = 'Troubleshooting',
    description = 'User diagnosing or fixing a fault or poor performance',
    sort_order = 6
WHERE name = 'bulk_trade';

UPDATE intents SET sort_order = 1 WHERE name = 'problem_solving';
UPDATE intents SET sort_order = 2 WHERE name = 'comparison';
UPDATE intents SET sort_order = 3 WHERE name = 'compatibility';
UPDATE intents SET sort_order = 4 WHERE name = 'specification';
UPDATE intents SET sort_order = 5 WHERE name = 'installation';
UPDATE intents SET sort_order = 6 WHERE name = 'troubleshooting';
UPDATE intents SET sort_order = 7 WHERE name = 'inspiration';
UPDATE intents SET sort_order = 8 WHERE name = 'regulatory';
UPDATE intents SET sort_order = 9 WHERE name = 'replacement';

ALTER TABLE intents
    ADD CONSTRAINT chk_intent_name_locked CHECK (
        name IN (
            'problem_solving',
            'comparison',
            'compatibility',
            'specification',
            'installation',
            'troubleshooting',
            'inspiration',
            'regulatory',
            'replacement'
        )
    );

-- ── 6. tier_intent_rules: Harvest = intent_id 1,3,4 only (additive) ───────────
INSERT IGNORE INTO tier_intent_rules (id, tier, intent_id)
SELECT UUID(), 'harvest', intent_id FROM intent_taxonomy WHERE intent_id IN (1, 3, 4);

-- ── 7. Restore intent_taxonomy CHECK + immutability trigger ─────────────────
ALTER TABLE intent_taxonomy
    ADD CONSTRAINT chk_intent_key_locked CHECK (
        intent_key IN (
            'problem_solving',
            'comparison',
            'compatibility',
            'specification',
            'installation',
            'troubleshooting',
            'inspiration',
            'regulatory',
            'replacement'
        )
    );

DROP TRIGGER IF EXISTS trg_intent_key_immutable;
DELIMITER //
CREATE TRIGGER trg_intent_key_immutable
BEFORE UPDATE ON intent_taxonomy
FOR EACH ROW
BEGIN
    IF NEW.intent_key <> OLD.intent_key THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
            'intent_key is immutable. Taxonomy values cannot be renamed. Changes require a formal spec change per quarterly review.';
    END IF;
END//
DELIMITER ;
