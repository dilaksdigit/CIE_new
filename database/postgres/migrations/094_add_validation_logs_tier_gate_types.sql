-- Add missing tier gate enum values for validation_logs.gate_type
-- Fixes: SQLSTATE[01000] Data truncated for column 'gate_type' when writing G6_TIER_TAG / G6_1_TIER_LOCK
-- Run with: mysql -u <user> -p <db> < 094_add_validation_logs_tier_gate_types.sql

ALTER TABLE validation_logs
MODIFY COLUMN gate_type TEXT NOT NULL;
