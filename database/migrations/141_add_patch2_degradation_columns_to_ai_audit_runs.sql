-- CIE v2.3.2 Hardening Addendum Patch 2: AI Audit Degradation Rules
-- Spec: CIE_v232_Hardening_Addendum.pdf Section 2.4
SET NAMES utf8mb4;

ALTER TABLE ai_audit_runs
  ADD COLUMN IF NOT EXISTS engines_responded SMALLINT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS engines_failed JSON DEFAULT ('[]'),
  ADD COLUMN IF NOT EXISTS quorum_met BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS questions_scored SMALLINT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS decay_action ENUM('advanced','paused','frozen') DEFAULT 'frozen';

ALTER TABLE ai_audit_results
  ADD COLUMN IF NOT EXISTS skip_reason VARCHAR(50) DEFAULT NULL;
