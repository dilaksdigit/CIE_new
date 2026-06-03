-- SOURCE: CIE_Master_Developer_Build_Spec §5.3 — per-tier max secondary intents (G3); additive seed only
SET NAMES utf8mb4;

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles) VALUES
('gates.hero_max_secondary', '3', 'integer', 'gates', 'Hero Max Secondary Intents', 'Maximum secondary intents for Hero tier (G3)', 'admin'),
('gates.support_max_secondary', '2', 'integer', 'gates', 'Support Max Secondary Intents', 'Maximum secondary intents for Support tier (G3)', 'admin'),
('gates.harvest_max_secondary', '1', 'integer', 'gates', 'Harvest Max Secondary Intents', 'Maximum secondary intents for Harvest tier (G3)', 'admin')
ON DUPLICATE KEY UPDATE
  rule_value = VALUES(rule_value),
  data_type = VALUES(data_type),
  description = VALUES(description),
  module = VALUES(module),
  label = VALUES(label),
  approver_roles = VALUES(approver_roles),
  last_changed_at = CURRENT_TIMESTAMP;
