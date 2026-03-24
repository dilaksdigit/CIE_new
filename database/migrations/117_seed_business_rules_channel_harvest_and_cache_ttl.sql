-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3
-- FIX: DB-21 — channel.harvest_threshold + system.business_rules_cache_ttl (see ChannelGovernorService + config cie.business_rules_cache_ttl)

SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description) VALUES
(UUID(), 'channel.harvest_threshold', '50', 'integer', 'Minimum readiness score for harvest tier channel COMPETE decision'),
(UUID(), 'system.business_rules_cache_ttl', '300', 'integer', 'Documented mirror of config cie.business_rules_cache_ttl (seconds); cache uses config in BusinessRulesService')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
