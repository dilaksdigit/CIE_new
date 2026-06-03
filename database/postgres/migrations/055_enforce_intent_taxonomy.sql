-- SOURCE: CIE_v231_Developer_Build_Pack.pdf — intent_taxonomy spec
--         "Exactly 9 rows. Locked. Changes require quarterly review."
-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6 — DB enforcement policy
-- SOURCE: CLAUDE.md §6 — The 9-Intent Taxonomy (locked, primary authority)
-- Pattern: mirrors audit_log immutability triggers (042_audit_log_immutability_triggers.sql)
--          per CIE_v231_Developer_Build_Pack.pdf §7.2

-- ── CHECK CONSTRAINT: intent_key restricted to canonical 9 values ──────────────
-- Structural enforcement at the column level. Prevents any INSERT or UPDATE
-- with an intent_key value outside the locked taxonomy.

ALTER TABLE intent_taxonomy
    ADD CONSTRAINT chk_intent_key_locked CHECK (
        intent_key IN (
            'compatibility',
            'comparison',
            'problem_solving',
            'inspiration',
            'specification',
            'installation',
            'safety_compliance',
            'replacement',
            'bulk_trade'
        )
    );

-- ── TRIGGER 1: Enforce maximum 9 rows ─────────────────────────────────────────
-- The taxonomy is locked to exactly 9 intents.
-- Any INSERT that would create a 10th row is rejected at the database level.

-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required

-- ── TRIGGER 2: Block UPDATE of intent_key (immutability) ──────────────────────
-- intent_key values are immutable once seeded.
-- "intent_id: 1-9. Immutable." — CIE_v231_Developer_Build_Pack.pdf
-- No application layer should UPDATE an intent_key; this enforces it structurally.

-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required

-- ── TRIGGER 3: Block DELETE from intent_taxonomy ──────────────────────────────
-- Rows cannot be removed. Taxonomy is locked.
-- Mirrors audit_log delete prevention pattern (042_audit_log_immutability_triggers.sql).

-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required

-- ── VERIFY post-migration ─────────────────────────────────────────────────────
-- Run this SELECT after applying this migration to confirm enforcement is live.
-- Expected: 9 rows, all keys matching the locked taxonomy.

-- SELECT intent_key, label FROM intent_taxonomy ORDER BY intent_id;
-- Expected output:
--   compatibility      | Compatibility
--   comparison         | Comparison
--   problem_solving    | Problem-Solving
--   inspiration        | Inspiration
--   specification      | Specification
--   installation       | Installation
--   safety_compliance  | Safety/Compliance
--   replacement        | Replacement
--   bulk_trade         | Bulk/Trade
