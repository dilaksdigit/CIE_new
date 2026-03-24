-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.5 audit_results
-- FIX: DB-15 — Add week_ending, consecutive_zero_weeks, is_available to ai_audit_results

SET NAMES utf8mb4;

ALTER TABLE ai_audit_results
  ADD COLUMN IF NOT EXISTS week_ending DATE NULL,
  ADD COLUMN IF NOT EXISTS consecutive_zero_weeks INTEGER NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS is_available BOOLEAN NOT NULL DEFAULT TRUE;
