-- Seed missing business rules for AI remediation fixes
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description)
VALUES
  (UUID(), 'audit.engine_timeout_seconds', '30', 'integer', 'Per-engine timeout for weekly AI citation audit'),
  (UUID(), 'title.max_length', '70', 'integer', 'Maximum generated title length'),
  (UUID(), 'chs.red_threshold', '40', 'integer', 'CHS red threshold for strict queue prioritization')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
