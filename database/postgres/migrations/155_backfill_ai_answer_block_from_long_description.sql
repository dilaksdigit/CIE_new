-- SOURCE: 006_seed_dummy_skus.sql — G4 answer text was inserted into long_description before 008 set ai_answer_block.
-- Backfill empty ai_answer_block from long_description for existing rows (Writer Edit + gate validation).

UPDATE skus
SET ai_answer_block = long_description
WHERE (ai_answer_block IS NULL OR TRIM(ai_answer_block) = '')
  AND long_description IS NOT NULL
  AND TRIM(long_description) <> '';
