-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Check 9.5 — CIS status field set to 'complete' at D+30.

ALTER TABLE gsc_baselines
  ADD COLUMN cis_status VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
  AFTER cis_score;
