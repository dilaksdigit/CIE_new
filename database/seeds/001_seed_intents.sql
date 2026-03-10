-- THE 9 LOCKED INTENTS - These are NEVER editable by users
-- Aligned with chk_intent_name_locked CHECK constraint in 005_create_intents_table.sql
-- and canonical intent_taxonomy in 007_seed_canonical_cie.sql
INSERT INTO intents (id, name, display_name, description, is_locked, sort_order) VALUES
(UUID(), 'problem_solving', 'Problem-Solving', 'User has a problem, needs product to solve it', true, 1),
(UUID(), 'comparison', 'Comparison', 'User evaluating alternatives', true, 2),
(UUID(), 'compatibility', 'Compatibility', 'User confirming fit with existing setup', true, 3),
(UUID(), 'inspiration', 'Inspiration', 'User browsing for ideas and style guidance', true, 4),
(UUID(), 'specification', 'Specification', 'User needs technical details', true, 5),
(UUID(), 'installation', 'Installation', 'User needs help installing or setting up', true, 6),
(UUID(), 'safety_compliance', 'Safety/Compliance', 'User needs safety, compliance, or regulatory detail', true, 7),
(UUID(), 'replacement', 'Replacement', 'User needs a replacement part or consumable', true, 8),
(UUID(), 'bulk_trade', 'Bulk/Trade', 'User is a trade or bulk buyer evaluating quantities', true, 9);
