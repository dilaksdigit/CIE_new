ALTER TABLE intents ADD COLUMN tier_access JSON DEFAULT NULL COMMENT 'Array of allowed tiers for G6.1 enforcement';
