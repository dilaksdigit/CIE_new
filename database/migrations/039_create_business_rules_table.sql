SET NAMES utf8mb4;

-- CIE v2.3.2 – business_rules table for configurable thresholds (no hard-coded values in engine).
-- All 53 rules seeded in 040_seed_business_rules.sql.

CREATE TABLE IF NOT EXISTS business_rules (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    rule_key VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
    value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    value_type ENUM('string','integer','float','boolean','json') NOT NULL DEFAULT 'string',
    description VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rule_key (rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
