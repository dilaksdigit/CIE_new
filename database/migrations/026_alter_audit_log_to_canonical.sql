-- Align audit_log structure with canonical CIE spec (v2.3.2)
-- Non-destructive: adds new columns and tightens enums without dropping data.

-- 1) Add actor_id, actor_role, and canonical timestamp column if missing
ALTER TABLE audit_log
  ADD COLUMN IF NOT EXISTS actor_id VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS actor_role VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS `timestamp` TIMESTAMP NULL;

-- 2) Tighten action column to canonical ENUM
ALTER TABLE audit_log
  MODIFY COLUMN action ENUM(
    'create','update','delete','publish','validate','tier_change',
    'gate_pass','gate_fail','audit_run','brief_generated',
    'escalation','login','permission_change'
  ) NOT NULL;

-- 3) Ensure entity_id can hold business IDs (up to 50 chars)
ALTER TABLE audit_log
  MODIFY COLUMN entity_id VARCHAR(50) NOT NULL;

-- NOTE: DB-level immutability (no UPDATE/DELETE) should be enforced
-- via privileges, outside this migration, e.g.:
--   REVOKE UPDATE, DELETE ON audit_log FROM app_user;

