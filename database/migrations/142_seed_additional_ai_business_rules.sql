-- Seed missing business rules for AI remediation fixes
SET NAMES utf8mb4;

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
VALUES
  ('audit.engine_timeout_seconds', '30', 'integer', 'audit', 'Audit Engine Timeout Seconds', 'Per-engine timeout for weekly AI citation audit', 'admin'),
  ('title.max_length', '70', 'integer', 'title', 'Title Max Length', 'Maximum generated title length', 'admin'),
  ('chs.red_threshold', '40', 'integer', 'chs', 'Chs Red Threshold', 'CHS red threshold for strict queue prioritization', 'admin')
ON DUPLICATE KEY UPDATE
  rule_value = VALUES(rule_value),
  data_type = VALUES(data_type),
  description = VALUES(description),
  module = VALUES(module),
  label = VALUES(label),
  approver_roles = VALUES(approver_roles),
  last_changed_at = CURRENT_TIMESTAMP;
