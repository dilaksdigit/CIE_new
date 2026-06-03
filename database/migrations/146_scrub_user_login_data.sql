-- Scrub all user login/sign-up details from database (PII removal)
-- - Anonymise user emails
-- - Remove reusable password hashes
-- - Remove historical login audit entries

UPDATE users
SET
  email = CONCAT('redacted+', LEFT(id, 8), '@example.invalid'),
  password_hash = 'REDACTED',
  is_active = 0;

-- SOURCE: CLAUDE.md Section 9 — audit_log is IMMUTABLE
-- SOURCE: CIE_v231_Developer_Build_Pack.pdf — audit_log: no UPDATE or DELETE permitted
-- REMOVED: audit_log DELETE statement that was present here.
-- audit_log rows CANNOT be deleted. The immutability trigger will block any attempt.
-- If login data scrubbing is required for GDPR compliance, this must be escalated
-- to the project owner as a GAP_LOG.md entry — it is not solvable at migration level
-- without a spec change. Do not re-add any DELETE or UPDATE targeting audit_log.

