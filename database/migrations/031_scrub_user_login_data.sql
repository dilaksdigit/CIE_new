-- Scrub all user login/sign-up details from database (PII removal)
-- - Anonymise user emails
-- - Remove reusable password hashes
-- - Remove historical login audit entries

UPDATE users
SET
  email = CONCAT('redacted+', LEFT(id, 8), '@example.invalid'),
  password_hash = 'REDACTED',
  is_active = 0;

DELETE FROM audit_log
WHERE action = 'login';

