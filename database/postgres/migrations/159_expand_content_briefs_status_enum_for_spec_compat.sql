-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Compatibility-only fix: include spec status values without removing current runtime values.

ALTER TABLE content_briefs
  MODIFY COLUMN status TEXT NOT NULL DEFAULT 'open';
