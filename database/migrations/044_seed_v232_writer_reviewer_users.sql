-- CIE v2.3.2 – Seed user accounts: admin (ADMIN), writer (CONTENT_EDITOR + PRODUCT_SPECIALIST), reviewer (CONTENT_LEAD + SEO_GOVERNOR).
-- Assumes roles exist from 002_seed_roles.sql. Password hash is for password "password"; replace in production.

INSERT INTO users (id, email, password_hash, first_name, last_name, is_active) VALUES
(UUID(), 'writer@cie.internal.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Writer',  'User',  true),
(UUID(), 'kpi_reviewer@cie.internal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'KPI Reviewer', 'User',  true)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Writer: CONTENT_EDITOR + PRODUCT_SPECIALIST
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.email = 'writer@cie.internal.com' AND r.name IN ('CONTENT_EDITOR', 'PRODUCT_SPECIALIST');

-- Reviewer: CONTENT_LEAD + SEO_GOVERNOR
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.email = 'kpi_reviewer@cie.internal.com' AND r.name IN ('CONTENT_LEAD', 'SEO_GOVERNOR');
