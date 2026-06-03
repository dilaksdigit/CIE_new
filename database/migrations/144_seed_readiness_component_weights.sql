SET NAMES utf8mb4;

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
VALUES
  ('readiness.weight_answer_block', '25', 'integer', 'readiness', 'Readiness Weight Answer Block', 'Readiness component weight: answer block', 'admin'),
  ('readiness.weight_faq_coverage', '20', 'integer', 'readiness', 'Readiness Weight FAQ Coverage', 'Readiness component weight: FAQ coverage', 'admin'),
  ('readiness.weight_safety_depth', '15', 'integer', 'readiness', 'Readiness Weight Safety Depth', 'Readiness component weight: safety depth', 'admin'),
  ('readiness.weight_cross_sku_comparison', '15', 'integer', 'readiness', 'Readiness Weight Cross SKU Comparison', 'Readiness component weight: cross-SKU comparison', 'admin'),
  ('readiness.weight_structured_data', '15', 'integer', 'readiness', 'Readiness Weight Structured Data', 'Readiness component weight: structured data', 'admin'),
  ('readiness.weight_citation_score', '10', 'integer', 'readiness', 'Readiness Weight Citation Score', 'Readiness component weight: citation score', 'admin')
ON DUPLICATE KEY UPDATE
  rule_value = VALUES(rule_value),
  data_type = VALUES(data_type),
  description = VALUES(description),
  module = VALUES(module),
  label = VALUES(label),
  approver_roles = VALUES(approver_roles),
  last_changed_at = CURRENT_TIMESTAMP;
