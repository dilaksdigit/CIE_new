-- CIE v2.3.2 – G6.1: Harvest SKUs may only use specification plus one other intent.
-- SOURCE: ENF§8.3 — Harvest allowed_intents [1, 3, 4] = problem_solving, compatibility, specification. Installation is NOT in the set.
-- Add 'harvest' to tier_access for specification only (not installation).

UPDATE intent_taxonomy SET tier_access = JSON_ARRAY('hero','support','harvest') WHERE intent_key = 'specification';
