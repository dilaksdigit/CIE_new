-- SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §3
-- SOURCE: CIE_v232_UI_Restructure_Instructions.docx §5 Step 2
-- FIX: DB-07 — Idempotent seed for the two business user accounts (writer + KPI reviewer).
--
-- NOTE: 044_seed_v232_writer_reviewer_users.sql is the primary historical seed (same emails
--   and bcrypt). This migration re-applies the same data for databases where 044 was not
--   applied or user_roles pivot rows were cleared, without duplicating spec-incompatible columns.
-- Schema matches 001_create_users_table.sql: password_hash, first_name, last_name; roles via user_roles.
-- Default password for seeded accounts (dev only): "password" — same hash as migration 044.

SET NAMES utf8mb4;

INSERT INTO users (id, email, password_hash, first_name, last_name, is_active)
VALUES
(UUID(), 'writer@cie.internal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Writer', 'User', TRUE),
(UUID(), 'kpi_reviewer@cie.internal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'KPI Reviewer', 'User', TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Writer: CONTENT_EDITOR + PRODUCT_SPECIALIST
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.email = 'writer@cie.internal.com' AND r.name IN ('CONTENT_EDITOR', 'PRODUCT_SPECIALIST');

-- Reviewer: CONTENT_LEAD + SEO_GOVERNOR
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.email = 'kpi_reviewer@cie.internal.com' AND r.name IN ('CONTENT_LEAD', 'SEO_GOVERNOR');
