-- 037_add_ai_answer_block_and_expert_authority_to_skus.sql
-- Adds ai_answer_block and expert_authority columns to skus table.

SET NAMES utf8mb4;
ALTER TABLE skus
ADD COLUMN ai_answer_block VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
ADD COLUMN expert_authority TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
