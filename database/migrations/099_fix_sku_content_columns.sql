-- SOURCE: Master Spec §6.1 (content parity), MySQL JSON adaptation.

SET NAMES utf8mb4;

ALTER TABLE sku_content
  ADD COLUMN IF NOT EXISTS vector_status ENUM('pass','fail','pending') DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS content_version INT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS last_published_at TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS json_ld JSON NULL COMMENT 'Auto-generated, never hand-edited',
  ADD COLUMN IF NOT EXISTS alt_text VARCHAR(200) NULL,
  ADD COLUMN IF NOT EXISTS product_description TEXT NULL,
  ADD COLUMN IF NOT EXISTS faq JSON NULL COMMENT 'Array of {question, answer} objects';

ALTER TABLE sku_content
  MODIFY COLUMN answer_block VARCHAR(400) NULL;
