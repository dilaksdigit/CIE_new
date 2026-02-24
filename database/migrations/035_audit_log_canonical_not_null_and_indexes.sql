-- ===================================================================
-- CIE v2.3.1 – audit_log full canonical compliance (Document 1)
-- ===================================================================
-- - actor_id, actor_role, timestamp NOT NULL (backfill from user_id/created_at)
-- - action enum includes operational values queued, queued_failed
-- - field_name VARCHAR(50), user_agent VARCHAR(300) per spec
-- - Indexes: idx_audit_actor, idx_audit_time, idx_audit_action
-- ===================================================================

-- 1) Backfill actor_id, actor_role, timestamp from existing data (requires 026 to have added these columns)
UPDATE audit_log
SET actor_id = COALESCE(actor_id, user_id, 'SYSTEM'),
    actor_role = COALESCE(actor_role, 'system'),
    `timestamp` = COALESCE(`timestamp`, created_at, CURRENT_TIMESTAMP)
WHERE actor_id IS NULL OR actor_role IS NULL OR `timestamp` IS NULL;

-- 2) Extend action enum to include operational values (app uses these)
ALTER TABLE audit_log
  MODIFY COLUMN action ENUM(
    'create','update','delete','publish','validate','tier_change',
    'gate_pass','gate_fail','audit_run','brief_generated',
    'escalation','login','permission_change',
    'queued','queued_failed'
  ) NOT NULL;

-- 3) Canonical column lengths (Document 1)
ALTER TABLE audit_log
  MODIFY COLUMN field_name VARCHAR(50) NULL,
  MODIFY COLUMN user_agent VARCHAR(300) NULL;

-- 4) NOT NULL for canonical required fields
ALTER TABLE audit_log
  MODIFY COLUMN actor_id VARCHAR(100) NOT NULL,
  MODIFY COLUMN actor_role VARCHAR(30) NOT NULL,
  MODIFY COLUMN `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 5) Canonical indexes (Document 1: idx_audit_entity, idx_audit_actor, idx_audit_time, idx_audit_action)
CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit_log(actor_id);
CREATE INDEX IF NOT EXISTS idx_audit_time ON audit_log(`timestamp`);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action);
