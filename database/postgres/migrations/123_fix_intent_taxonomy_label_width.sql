-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.2 intent_taxonomy
-- FIX: DB-06 — Widen label column from VARCHAR(50) to VARCHAR(100) per spec (additive width only).

ALTER TABLE intent_taxonomy
  MODIFY COLUMN label VARCHAR(100) NOT NULL;
