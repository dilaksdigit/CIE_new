-- Add missing user_id column to validation_logs to match application inserts
-- Fixes: "Unknown column 'user_id' in 'field list'" when writing validation results.
-- Safe for existing installs that already have the column: run once in migration order.

ALTER TABLE validation_logs
  ADD COLUMN user_id CHAR(36) NULL AFTER sku_id;

ALTER TABLE validation_logs
  ADD CONSTRAINT fk_validation_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

