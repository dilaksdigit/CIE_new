-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Check 9.5 — CIS status field set to 'complete' at D+30.

ALTER TABLE gsc_baselines
  ADD COLUMN cis_status VARCHAR(20) NULL DEFAULT NULL
  AFTER cis_score;
