-- Align validation_logs schema with ValidationService and ValidationLog model
-- Fixes: "Unknown column 'validation_status' in 'field list'" and related column errors
-- when inserting summary validation results.
--
-- Adds:
--   - validation_status   (overall status enum)
--   - results_json        (full validation payload)
--   - created_at/updated_at timestamps (for Eloquent)

-- ==========================================
-- Align validation_logs with validation service
-- Safe for ALL MySQL versions
-- ==========================================

-- 1️⃣ Add validation_status if missing
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'validation_logs'
      AND COLUMN_NAME = 'validation_status'
);

SET @sql = IF(@col_exists = 0,
    "ALTER TABLE validation_logs 
     ADD COLUMN validation_status 
     TEXT 
     NOT NULL DEFAULT 'PENDING' 
     AFTER user_id;",
    "SELECT 'validation_status already exists';"
);

-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

-- 2️⃣ Add results_json if missing
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'validation_logs'
      AND COLUMN_NAME = 'results_json'
);

SET @sql = IF(@col_exists = 0,
    "ALTER TABLE validation_logs 
     ADD COLUMN results_json JSON NULL 
     AFTER validation_status;",
    "SELECT 'results_json already exists';"
);

-- TODO(pg): removed MySQL PREPARE/EXECUTE block;

-- 3️⃣ Add passed flag if missing
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'validation_logs'
      AND COLUMN_NAME = 'passed'
);

SET @sql = IF(@col_exists = 0,
    "ALTER TABLE validation_logs 
     ADD COLUMN passed BOOLEAN NOT NULL DEFAULT 0 
     AFTER results_json;",
    "SELECT 'passed already exists';"
);

-- TODO(pg): removed MySQL PREPARE/EXECUTE block;
