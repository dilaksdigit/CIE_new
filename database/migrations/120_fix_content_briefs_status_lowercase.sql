-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.5 content_briefs
-- FIX: DB-16 — Convert status ENUM from uppercase to lowercase per spec
-- NOTE: 008_create_content_briefs_table.sql uses CANCELLED (not OVERDUE); spec ENUM has no cancelled — map after LOWER.

SET NAMES utf8mb4;

-- Step 1: Widen column temporarily to VARCHAR to preserve data
ALTER TABLE content_briefs MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open';

-- Step 2: Lowercase existing data
UPDATE content_briefs SET status = LOWER(status);

-- Normalize legacy 008 value into spec ENUM (required before Step 3)
UPDATE content_briefs SET status = 'completed' WHERE status = 'cancelled';

-- Step 3: Reapply correct ENUM
ALTER TABLE content_briefs MODIFY COLUMN status ENUM('open','in_progress','completed','overdue') NOT NULL DEFAULT 'open';
