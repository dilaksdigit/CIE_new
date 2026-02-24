-- ===================================================================
-- CIE v2.3.1 – Canonical Seeds
-- ===================================================================
-- - 9 locked intents in intent_taxonomy
-- - Tier → Intent mapping in tier_intent_rules
-- - 4 canonical tiers in tier_types
-- - material_wikidata reference seeds
-- ===================================================================

-- -------------------------------------------------------------------
-- 1. Intent Taxonomy – 9 LOCKED INTENTS (NEVER user-editable)
-- -------------------------------------------------------------------
INSERT INTO intent_taxonomy (id, intent_id, intent_key, label, definition, tier_access)
VALUES
  (UUID(), 1, 'problem_solving', 'Problem Solving',
   'User has a concrete problem or failure and needs a fix.',
   JSON_ARRAY('hero','support')),
  (UUID(), 2, 'comparison', 'Comparison',
   'User is choosing between products or options and needs tradeoffs.',
   JSON_ARRAY('hero','support')),
  (UUID(), 3, 'compatibility', 'Compatibility',
   'User needs to know what works with what (fixtures, bulbs, voltages, mounts).',
   JSON_ARRAY('hero','support')),
  (UUID(), 4, 'specification', 'Specification',
   'User needs detailed technical specifications and attributes.',
   JSON_ARRAY('hero','support')),
  (UUID(), 5, 'installation', 'Installation',
   'User needs step-by-step setup and installation guidance.',
   JSON_ARRAY('hero','support')),
  (UUID(), 6, 'troubleshooting', 'Troubleshooting',
   'User is diagnosing issues after installation or use.',
   JSON_ARRAY('hero','support')),
  (UUID(), 7, 'inspiration', 'Inspiration',
   'User is exploring styles, looks, and use-case ideas.',
   JSON_ARRAY('hero','support')),
  (UUID(), 8, 'regulatory', 'Regulatory',
   'User needs compliance, safety, and standards information.',
   JSON_ARRAY('hero','support')),
  (UUID(), 9, 'replacement', 'Replacement',
   'User is replacing an existing part or SKU and needs a compatible alternative.',
   JSON_ARRAY('hero','support'));

-- -------------------------------------------------------------------
-- 2. Tier Types – 4 canonical tiers
-- -------------------------------------------------------------------
INSERT INTO tier_types (id, tier_key, label, description) VALUES
  (UUID(), 'hero',    'Hero',
   'Top-performing SKUs with full content coverage and strict gating.'),
  (UUID(), 'support', 'Support',
   'Important SKUs that backfill hero coverage and meet baseline gates.'),
  (UUID(), 'harvest', 'Harvest',
   'Legacy or low-priority SKUs kept for residual demand with reduced gating.'),
  (UUID(), 'kill',    'Kill',
   'SKUs scheduled for removal or fully suppressed from content surfaces.');

-- -------------------------------------------------------------------
-- 3. Tier Intent Rules – normalized mapping
--    Business rule (v2.3.1): hero/support can use all 9 intents.
--    harvest/kill: no intents allowed (enforced by absence of rows).
-- -------------------------------------------------------------------
INSERT INTO tier_intent_rules (id, tier, intent_id)
SELECT UUID(), 'hero', intent_id FROM intent_taxonomy;

INSERT INTO tier_intent_rules (id, tier, intent_id)
SELECT UUID(), 'support', intent_id FROM intent_taxonomy;

-- -------------------------------------------------------------------
-- 4. material_wikidata – reference materials (from spec)
-- -------------------------------------------------------------------
INSERT INTO material_wikidata (id, material_id, material_name, wikidata_qid, wikidata_uri, ai_signal, is_active)
VALUES
  (UUID(), 'MAT-BORO-GLASS', 'Borosilicate Glass', 'Q190117',
   'https://www.wikidata.org/entity/Q190117',
   'Signals heat resistance and durability', TRUE),
  (UUID(), 'MAT-OPAL-GLASS', 'Opal Glass', 'Q223425',
   'https://www.wikidata.org/entity/Q223425',
   'Signals light diffusion for soft light queries', TRUE),
  (UUID(), 'MAT-COTTON', 'Cotton Fabric', 'Q11457',
   'https://www.wikidata.org/entity/Q11457',
   'Signals natural material for eco-conscious queries', TRUE),
  (UUID(), 'MAT-BRASS', 'Brass', 'Q39782',
   'https://www.wikidata.org/entity/Q39782',
   'Signals premium and period-style', TRUE),
  (UUID(), 'MAT-POLYCARB', 'Polycarbonate', 'Q146439',
   'https://www.wikidata.org/entity/Q146439',
   'Signals impact resistance and child safety', TRUE);

