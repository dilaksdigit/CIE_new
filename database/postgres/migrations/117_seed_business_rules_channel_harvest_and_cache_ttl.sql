-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3
-- FIX: DB-21 — channel.harvest_threshold + system.business_rules_cache_ttl (see ChannelGovernorService + config cie.business_rules_cache_ttl)

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles) VALUES
('channel.harvest_threshold', '50', 'integer', 'channel', 'Harvest Threshold', 'Minimum readiness score for harvest tier channel COMPETE decision', 'admin'),
('system.business_rules_cache_ttl', '300', 'integer', 'system', 'Business Rules Cache TTL', 'Documented mirror of config cie.business_rules_cache_ttl (seconds); cache uses config in BusinessRulesService', 'admin')
ON DUPLICATE KEY UPDATE
  rule_value = VALUES(rule_value),
  data_type = VALUES(data_type),
  description = VALUES(description),
  module = VALUES(module),
  label = VALUES(label),
  approver_roles = VALUES(approver_roles),
  last_changed_at = CURRENT_TIMESTAMP;
