-- Ensure skus.ai_answer_block can store 250–300 chars (G4 gate). Fixes truncation if column was created shorter.
SET NAMES utf8mb4;

ALTER TABLE skus MODIFY COLUMN ai_answer_block VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
