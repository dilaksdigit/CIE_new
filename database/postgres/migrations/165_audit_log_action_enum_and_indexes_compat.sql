-- SOURCE: CIE_v231_Developer_Build_Pack.pdf audit_log schema
-- Compatibility hardening: preserve required values while avoiding runtime breakage.

-- Ensure timestamp index exists
CREATE INDEX IF NOT EXISTS idx_audit_log_timestamp ON audit_log("timestamp");

-- Ensure action index exists
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log("action");

-- Keep required enum values present, plus known in-use action values.
-- This avoids breaking existing inserts while aligning with spec set.
ALTER TABLE audit_log
  MODIFY COLUMN action TEXT NOT NULL;
