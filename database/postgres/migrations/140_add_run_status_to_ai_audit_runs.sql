-- SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 2 §2.1 — persist OpenAPI run_status (complete|partial|failed)

ALTER TABLE ai_audit_runs
  ADD COLUMN run_status TEXT NULL
    COMMENT 'Engine quorum outcome per Hardening Patch 2 §2.1'
    AFTER quorum_met;
