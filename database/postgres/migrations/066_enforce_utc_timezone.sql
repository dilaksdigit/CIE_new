-- SOURCE: CLAUDE.md Section 9 — All timestamps: TIMESTAMP type, UTC timezone

SET GLOBAL time_zone = '+00:00';
SET SESSION time_zone = '+00:00';

SELECT @@global.time_zone, @@session.time_zone;
