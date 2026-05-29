SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description)
VALUES
  (UUID(), 'readiness.weight_answer_block', '25', 'integer', 'Readiness component weight: answer block'),
  (UUID(), 'readiness.weight_faq_coverage', '20', 'integer', 'Readiness component weight: FAQ coverage'),
  (UUID(), 'readiness.weight_safety_depth', '15', 'integer', 'Readiness component weight: safety depth'),
  (UUID(), 'readiness.weight_cross_sku_comparison', '15', 'integer', 'Readiness component weight: cross-SKU comparison'),
  (UUID(), 'readiness.weight_structured_data', '15', 'integer', 'Readiness component weight: structured data'),
  (UUID(), 'readiness.weight_citation_score', '10', 'integer', 'Readiness component weight: citation score')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
