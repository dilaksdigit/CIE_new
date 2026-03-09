-- SOURCE: CLAUDE.md §9 — "All timestamps: TIMESTAMP type, UTC timezone"
-- Enforce UTC at the session level for all connections.

SET GLOBAL time_zone = '+00:00';
SET SESSION time_zone = '+00:00';
