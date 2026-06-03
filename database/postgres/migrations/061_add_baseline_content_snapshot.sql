-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Check 9.7 — store original content at baseline for rollback.

ALTER TABLE gsc_baselines
  ADD COLUMN baseline_content_snapshot JSON NULL DEFAULT NULL
  AFTER cis_status;
