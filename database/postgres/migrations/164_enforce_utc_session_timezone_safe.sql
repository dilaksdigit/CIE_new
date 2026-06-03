-- SOURCE: CLAUDE.md Section 9 — timestamps must use UTC.
-- Safe migration: enforce session-level UTC only (no SET GLOBAL).

-- MySQL/MariaDB session scope
SET SESSION time_zone = '+00:00';

-- Evidence probe (non-destructive)
SELECT @@session.time_zone AS session_time_zone_utc;
