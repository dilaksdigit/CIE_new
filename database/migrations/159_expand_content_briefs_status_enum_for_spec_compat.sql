-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Compatibility-only fix: include spec status values without removing current runtime values.

SET NAMES utf8mb4;

ALTER TABLE content_briefs
  MODIFY COLUMN status ENUM(
    'open',
    'draft',
    'active',
    'in_progress',
    'completed',
    'overdue'
  ) NOT NULL DEFAULT 'open';
