-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.2 intent_taxonomy
-- FIX: DB-06 — Widen label column from VARCHAR(50) to VARCHAR(100) per spec (additive width only).

SET NAMES utf8mb4;

ALTER TABLE intent_taxonomy
  MODIFY COLUMN label VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
