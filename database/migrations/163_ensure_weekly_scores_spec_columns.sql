-- SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx Section 1
-- Compatibility-only fix: ensure weekly_scores has spec column names.

SET NAMES utf8mb4;

-- Ensure week_start_date exists (rename from week_start when needed)
SET @has_week_start := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'weekly_scores'
    AND COLUMN_NAME = 'week_start'
);
SET @has_week_start_date := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'weekly_scores'
    AND COLUMN_NAME = 'week_start_date'
);

SET @sql := IF(
  @has_week_start = 1 AND @has_week_start_date = 0,
  'ALTER TABLE weekly_scores CHANGE COLUMN week_start week_start_date DATE NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE weekly_scores
  ADD COLUMN IF NOT EXISTS writer_user_id CHAR(36) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS created_by CHAR(36) NULL AFTER notes;
