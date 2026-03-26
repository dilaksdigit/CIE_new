-- SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 2 §2.1 — persist OpenAPI run_status (complete|partial|failed)
SET NAMES utf8mb4;

ALTER TABLE ai_audit_runs
  ADD COLUMN run_status ENUM('complete', 'partial', 'failed') NULL
    COMMENT 'Engine quorum outcome per Hardening Patch 2 §2.1'
    AFTER quorum_met;
