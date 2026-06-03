-- SOURCE: CIE_Master_Developer_Build_Spec.docx §14.1 + §5 — queue priority bonuses (Phase 3.4: queue reads BusinessRules)

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'queue.decay_critical_bonus', '100', 'integer', 'queue', 'Queue Decay Critical Bonus', 'Priority points for auto_brief/escalated decay status', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.decay_critical_bonus');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'queue.decay_alert_bonus', '60', 'integer', 'queue', 'Queue Decay Alert Bonus', 'Priority points for alert decay status', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.decay_alert_bonus');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'queue.low_chs_bonus', '40', 'integer', 'queue', 'Queue Low CHS Bonus', 'Priority points when CHS below amber threshold', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.low_chs_bonus');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'queue.hero_readiness_gap_bonus', '35', 'integer', 'queue', 'Queue Hero Readiness Gap Bonus', 'Priority points for Hero SKU below readiness min', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.hero_readiness_gap_bonus');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'queue.hero_missing_answer_bonus', '30', 'integer', 'queue', 'Queue Hero Missing Answer Bonus', 'Priority points for Hero SKU without answer_block', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.hero_missing_answer_bonus');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'queue.open_brief_bonus', '25', 'integer', 'queue', 'Queue Open Brief Bonus', 'Priority points for SKU with open content brief', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'queue.open_brief_bonus');
