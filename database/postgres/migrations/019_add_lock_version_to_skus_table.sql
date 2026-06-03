ALTER TABLE skus ADD COLUMN lock_version INT DEFAULT 1 AFTER updated_by;
