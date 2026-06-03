-- SOURCE: CIE_Master_Developer_Build_Spec Section 5 (Business Rules Config Layer); GATE-09
-- G7 channel readiness thresholds — no hard-coded values in application code.

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles) VALUES
('channel.shopify_readiness_threshold', '85', 'integer', 'channel', 'Shopify Readiness Threshold', 'Shopify channel readiness minimum (G7)', 'admin'),
('channel.gmc_readiness_threshold', '85', 'integer', 'channel', 'GMC Readiness Threshold', 'GMC channel readiness minimum (G7)', 'admin')
ON DUPLICATE KEY UPDATE
  rule_value = VALUES(rule_value),
  data_type = VALUES(data_type),
  description = VALUES(description),
  module = VALUES(module),
  label = VALUES(label),
  approver_roles = VALUES(approver_roles),
  last_changed_at = CURRENT_TIMESTAMP;
