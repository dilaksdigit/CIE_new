-- Add canonical content fields to legacy skus table for G4/G7 gate and workflow tests.
-- Safe to run: ADD COLUMN only if not present (MySQL 8.0.12+ supports IF NOT EXISTS for ADD COLUMN in some setups; otherwise run once).

ALTER TABLE skus ADD COLUMN ai_answer_block VARCHAR(300) NULL AFTER long_description;
ALTER TABLE skus ADD COLUMN expert_authority TEXT NULL AFTER ai_answer_block;
