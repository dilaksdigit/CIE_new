-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1 — Task C2 degraded_mode on ai_audit_runs
SET NAMES utf8mb4;

ALTER TABLE ai_audit_runs
  ADD COLUMN degraded_mode TINYINT(1) NOT NULL DEFAULT 0;
