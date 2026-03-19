-- SOURCE: CLAUDE.md §12 — correct column names in ai_audit_results
-- Rename response_snippet → response_hash, created_at → run_date

SET NAMES utf8mb4;

ALTER TABLE ai_audit_results
  RENAME COLUMN response_snippet TO response_hash,
  RENAME COLUMN created_at TO run_date;
