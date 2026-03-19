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
-- Use INSERT IGNORE so re-running this seed does not fail if rows already exist.
INSERT IGNORE INTO intent_taxonomy (intent_id, intent_key, label, definition, tier_access)
VALUES
  (1, 'problem_solving',   'Problem-Solving',   'User has a problem, needs product to solve it',          '["hero","support","harvest"]'),
  (2, 'comparison',        'Comparison',         'User evaluating alternatives',                           '["hero","support"]'),
  (3, 'compatibility',     'Compatibility',      'User confirming fit with existing setup',                '["hero","support","harvest"]'),
  (4, 'inspiration',       'Inspiration',        'User browsing for ideas and style guidance',             '["hero","support"]'),
  (5, 'specification',     'Specification',      'User needs technical details',                           '["hero","support","harvest"]'),
  (6, 'installation',      'Installation',       'User needs help installing or setting up',               '["hero","support"]'),
  (7, 'safety_compliance', 'Safety/Compliance',  'User needs safety, compliance, or regulatory detail',   '["hero","support"]'),
  (8, 'replacement',       'Replacement',        'User needs a replacement part or consumable',            '["hero","support"]'),
  (9, 'bulk_trade',        'Bulk/Trade',         'User is a trade or bulk buyer evaluating quantities',   '["hero","support"]');

-- -------------------------------------------------------------------
-- 2. Tier Types – 4 canonical tiers
-- -------------------------------------------------------------------
INSERT IGNORE INTO tier_types (id, tier_key, label, description) VALUES
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
INSERT IGNORE INTO tier_intent_rules (id, tier, intent_id)
SELECT UUID(), 'hero', intent_id FROM intent_taxonomy;

INSERT IGNORE INTO tier_intent_rules (id, tier, intent_id)
SELECT UUID(), 'support', intent_id FROM intent_taxonomy;

-- -------------------------------------------------------------------
-- 4. material_wikidata – reference materials (from spec)
-- -------------------------------------------------------------------
INSERT IGNORE INTO material_wikidata (id, material_id, material_name, wikidata_qid, wikidata_uri, ai_signal, is_active)
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

