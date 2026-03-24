-- SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §8.3 + §4.2
-- FIX: DB-06 — Correct intent_taxonomy labels to match spec exactly (UPDATE only; no row deletes)

SET NAMES utf8mb4;

UPDATE intent_taxonomy SET label = 'Problem-Solving' WHERE intent_key = 'problem_solving';
UPDATE intent_taxonomy SET label = 'Comparison' WHERE intent_key = 'comparison';
UPDATE intent_taxonomy SET label = 'Compatibility' WHERE intent_key = 'compatibility';
UPDATE intent_taxonomy SET label = 'Specification' WHERE intent_key = 'specification';
UPDATE intent_taxonomy SET label = 'Installation / How-To' WHERE intent_key = 'installation';
UPDATE intent_taxonomy SET label = 'Troubleshooting' WHERE intent_key = 'troubleshooting';
UPDATE intent_taxonomy SET label = 'Inspiration / Style' WHERE intent_key = 'inspiration';
UPDATE intent_taxonomy SET label = 'Regulatory / Safety' WHERE intent_key = 'regulatory';
UPDATE intent_taxonomy SET label = 'Replacement / Refill' WHERE intent_key = 'replacement';
