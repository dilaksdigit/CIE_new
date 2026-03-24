-- Add missing tier gate enum values for validation_logs.gate_type
-- Fixes: SQLSTATE[01000] Data truncated for column 'gate_type' when writing G6_TIER_TAG / G6_1_TIER_LOCK
-- Run with: mysql -u <user> -p <db> < 094_add_validation_logs_tier_gate_types.sql

SET NAMES utf8mb4;

ALTER TABLE validation_logs
MODIFY COLUMN gate_type ENUM(
    'G1_BASIC_INFO',
    'G2_IMAGES',
    'G2_INTENT',
    'G3_SEO',
    'G3_SECONDARY_INTENT',
    'G4_ANSWER_BLOCK',
    'G4_VECTOR',
    'G5_VECTOR',
    'G5_BEST_NOT_FOR',
    'G5_TECHNICAL',
    'G6_COMMERCIAL',
    'G6_COMMERCIAL_POLICY',
    'G6_DESCRIPTION_QUALITY',
    'G6_TIER_TAG',
    'G6_1_TIER_LOCK',
    'G7_EXPERT'
) NOT NULL;
