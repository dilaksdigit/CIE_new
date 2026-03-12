-- SOURCE: CIE_Master_Developer_Build_Spec Section 5 (Business Rules Config Layer); GATE-09
-- G7 channel readiness thresholds — no hard-coded values in application code.

INSERT INTO business_rules (id, rule_key, value, value_type, description) VALUES
(UUID(), 'channel.shopify_readiness_threshold', '85', 'integer', 'Shopify channel readiness minimum (G7)'),
(UUID(), 'channel.gmc_readiness_threshold', '85', 'integer', 'GMC channel readiness minimum (G7)')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
