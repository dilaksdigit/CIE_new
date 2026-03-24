-- SOURCE: CIE_Master_Developer_Build_Spec §5.3 — per-tier max secondary intents (G3); additive seed only
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description) VALUES
(UUID(), 'gates.hero_max_secondary', '3', 'integer', 'Maximum secondary intents for Hero tier (G3)'),
(UUID(), 'gates.support_max_secondary', '2', 'integer', 'Maximum secondary intents for Support tier (G3)'),
(UUID(), 'gates.harvest_max_secondary', '1', 'integer', 'Maximum secondary intents for Harvest tier (G3)')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
