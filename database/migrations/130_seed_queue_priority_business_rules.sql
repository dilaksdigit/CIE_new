-- SOURCE: CIE_Master_Developer_Build_Spec.docx §14.1 + §5 — queue priority bonuses (Phase 3.4: queue reads BusinessRules)
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'queue.decay_critical_bonus', '100', 'integer', 'Priority points for auto_brief/escalated decay status'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.decay_critical_bonus');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'queue.decay_alert_bonus', '60', 'integer', 'Priority points for alert decay status'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.decay_alert_bonus');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'queue.low_chs_bonus', '40', 'integer', 'Priority points when CHS below amber threshold'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.low_chs_bonus');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'queue.hero_readiness_gap_bonus', '35', 'integer', 'Priority points for Hero SKU below readiness min'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.hero_readiness_gap_bonus');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'queue.hero_missing_answer_bonus', '30', 'integer', 'Priority points for Hero SKU without answer_block'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.hero_missing_answer_bonus');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'queue.open_brief_bonus', '25', 'integer', 'Priority points for SKU with open content brief'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.open_brief_bonus');
