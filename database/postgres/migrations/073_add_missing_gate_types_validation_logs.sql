-- Add missing gate_type enum values to validation_logs (G5_BEST_NOT_FOR, G6_DESCRIPTION_QUALITY)
-- Fixes: "Data truncated for column 'gate_type' at row 1" when validating (e.g. G5 Best-For/Not-For)
-- Run this if you already applied 050 before it was updated: mysql -u user -p db < 073_add_missing_gate_types_validation_logs.sql

ALTER TABLE validation_logs
MODIFY COLUMN gate_type TEXT NOT NULL;
