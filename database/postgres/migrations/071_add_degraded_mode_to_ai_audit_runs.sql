-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1 — Task C2 degraded_mode on ai_audit_runs

ALTER TABLE ai_audit_runs
  ADD COLUMN degraded_mode BOOLEAN NOT NULL DEFAULT 0;
