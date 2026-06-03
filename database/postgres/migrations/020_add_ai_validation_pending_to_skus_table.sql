ALTER TABLE skus ADD COLUMN ai_validation_pending BOOLEAN DEFAULT false AFTER lock_version;
