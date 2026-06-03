-- Ensure skus.ai_answer_block can store 250–300 chars (G4 gate). Fixes truncation if column was created shorter.

ALTER TABLE skus MODIFY COLUMN ai_answer_block VARCHAR(300) NULL;
