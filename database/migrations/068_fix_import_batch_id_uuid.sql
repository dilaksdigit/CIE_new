-- SOURCE: CLAUDE.md Section 9 + Semrush Addendum (import_batch_id as UUID)
-- DB-06: Canonical MySQL UUID storage type CHAR(36)
SET NAMES utf8mb4;

ALTER TABLE semrush_imports
  MODIFY COLUMN import_batch_id CHAR(36) NOT NULL
  COMMENT 'UUID v4 per import batch';
