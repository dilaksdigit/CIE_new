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

DROP TRIGGER IF EXISTS trg_intent_taxonomy_max_rows;
DELIMITER //
CREATE TRIGGER trg_intent_taxonomy_max_rows
BEFORE INSERT ON intent_taxonomy
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM intent_taxonomy) >= 9 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
            'intent_taxonomy is locked to exactly 9 rows. Changes require a formal spec change per quarterly review. Error: CIE_G2_INVALID_INTENT';
    END IF;
END//
DELIMITER ;

-- ── TRIGGER 2: Block UPDATE of intent_key (immutability) ──────────────────────
-- intent_key values are immutable once seeded.
-- "intent_id: 1-9. Immutable." — CIE_v231_Developer_Build_Pack.pdf
-- No application layer should UPDATE an intent_key; this enforces it structurally.

DROP TRIGGER IF EXISTS trg_intent_key_immutable;
DELIMITER //
CREATE TRIGGER trg_intent_key_immutable
BEFORE UPDATE ON intent_taxonomy
FOR EACH ROW
BEGIN
    IF NEW.intent_key <> OLD.intent_key THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
            'intent_key is immutable. Taxonomy values cannot be renamed. Changes require a formal spec change per quarterly review.';
    END IF;
END//
DELIMITER ;

-- ── TRIGGER 3: Block DELETE from intent_taxonomy ──────────────────────────────
-- Rows cannot be removed. Taxonomy is locked.
-- Mirrors audit_log delete prevention pattern (042_audit_log_immutability_triggers.sql).

DROP TRIGGER IF EXISTS trg_intent_taxonomy_no_delete;
DELIMITER //
CREATE TRIGGER trg_intent_taxonomy_no_delete
BEFORE DELETE ON intent_taxonomy
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
        'Rows cannot be deleted from intent_taxonomy. The taxonomy is locked to exactly 9 rows. Changes require a formal spec change per quarterly review.';
END//
DELIMITER ;

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
