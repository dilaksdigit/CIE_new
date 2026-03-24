-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.2 intent_taxonomy
-- FIX: DB-06 — Add missing is_active column

SET NAMES utf8mb4;

ALTER TABLE intent_taxonomy
  ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;
