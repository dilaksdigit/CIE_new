-- ============================================================
-- SOURCE: CLAUDE.md §9 — "Tier stored as ENUM('hero','support','harvest','kill') — lowercase, no variants"
-- Corrective patch for databases that were created with uppercase tier ENUMs.
-- ============================================================

-- PATCH: skus.tier — lowercase ENUM per CLAUDE.md §9
ALTER TABLE skus
  MODIFY COLUMN tier ENUM('hero','support','harvest','kill') NOT NULL;

-- PATCH: tier_history.old_tier, new_tier — lowercase ENUM per CLAUDE.md §9
ALTER TABLE tier_history
  MODIFY COLUMN old_tier ENUM('hero','support','harvest','kill') NULL,
  MODIFY COLUMN new_tier ENUM('hero','support','harvest','kill') NOT NULL;

-- PATCH: staff_effort_logs.tier — lowercase ENUM per CLAUDE.md §9
ALTER TABLE staff_effort_logs
  MODIFY COLUMN tier ENUM('hero','support','harvest','kill') NOT NULL;
