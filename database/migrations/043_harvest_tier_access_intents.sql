-- CIE v2.3.2 – G6.1: Harvest SKUs may only use specification plus one other intent.
-- Add 'harvest' to tier_access for specification and installation so Harvest can have specification + one other.

UPDATE intent_taxonomy SET tier_access = JSON_ARRAY('hero','support','harvest') WHERE intent_key = 'specification';
UPDATE intent_taxonomy SET tier_access = JSON_ARRAY('hero','support','harvest') WHERE intent_key = 'installation';
