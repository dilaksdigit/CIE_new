-- 037_add_ai_answer_block_and_expert_authority_to_skus.sql
-- Adds ai_answer_block and expert_authority columns to skus table.

ALTER TABLE skus
ADD COLUMN ai_answer_block VARCHAR(300) NULL,
ADD COLUMN expert_authority TEXT NULL;
