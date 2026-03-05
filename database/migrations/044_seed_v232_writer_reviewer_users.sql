-- CIE v2.3.2 – Seed user accounts: admin (ADMIN), writer (CONTENT_EDITOR + PRODUCT_SPECIALIST), reviewer (CONTENT_LEAD + SEO_GOVERNOR).
-- Assumes roles exist from 002_seed_roles.sql. Password hash is for password "password"; replace in production.

INSERT INTO users (id, email, password_hash, first_name, last_name, is_active) VALUES
(UUID(), 'admin@cie.example.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin',   'User',  true),
(UUID(), 'writer@cie.example.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Writer',  'User',  true),
(UUID(), 'reviewer@cie.example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reviewer', 'User',  true)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Admin: ADMIN
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.email = 'admin@cie.example.com' AND r.name = 'ADMIN';

-- Writer: CONTENT_EDITOR + PRODUCT_SPECIALIST
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.email = 'writer@cie.example.com' AND r.name IN ('CONTENT_EDITOR', 'PRODUCT_SPECIALIST');

-- Reviewer: CONTENT_LEAD + SEO_GOVERNOR
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.email = 'reviewer@cie.example.com' AND r.name IN ('CONTENT_LEAD', 'SEO_GOVERNOR');
