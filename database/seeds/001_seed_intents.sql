-- THE 9 LOCKED INTENTS - These are NEVER editable by users
-- SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — canonical keys (aligned with chk_intent_name_locked after migration 082)
-- and canonical intent_taxonomy in 007_seed_canonical_cie.sql
INSERT INTO intents (id, name, display_name, description, is_locked, sort_order) VALUES
(UUID(), 'problem_solving', 'Problem-Solving', 'User has a problem, needs product to solve it', true, 1),
(UUID(), 'comparison', 'Comparison', 'User evaluating alternatives', true, 2),
(UUID(), 'compatibility', 'Compatibility', 'User confirming fit with existing setup', true, 3),
(UUID(), 'specification', 'Specification', 'User needs technical details', true, 4),
(UUID(), 'installation', 'Installation / How-To', 'User needs help installing or setting up', true, 5),
(UUID(), 'troubleshooting', 'Troubleshooting', 'User diagnosing or fixing a fault or poor performance', true, 6),
(UUID(), 'inspiration', 'Inspiration / Style', 'User browsing for ideas and style guidance', true, 7),
(UUID(), 'regulatory', 'Regulatory / Safety', 'User needs safety, compliance, or regulatory detail', true, 8),
(UUID(), 'replacement', 'Replacement / Refill', 'User needs a replacement part or consumable', true, 9);
