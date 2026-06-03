-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5, §5.2, §7, §18
-- Phase 5 hardening rules (additive only). Uses existing business_rules schema.
SET NAMES utf8mb4;

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles) VALUES
('gates.description_min_words', '50', 'integer', 'gates', 'Gates Description Min Words', 'Minimum word count for product description (G6 gate)', 'admin'),
('gates.title_max_chars', '250', 'integer', 'gates', 'Gates Title Max Chars', 'Maximum character length for generated titles', 'admin'),
('gates.meta_desc_max_chars', '160', 'integer', 'gates', 'Gates Meta Desc Max Chars', 'Maximum character length for meta description', 'admin'),
('maturity.ai_visibility_max', '15', 'integer', 'maturity', 'Maturity Ai Visibility Max', 'Maximum points for AI visibility maturity component', 'admin'),
('maturity.core_pillar_points', '10', 'integer', 'maturity', 'Maturity Core Pillar Points', 'Points per core pillar in maturity scoring', 'admin'),
('maturity.channel_max', '25', 'integer', 'maturity', 'Maturity Channel Max', 'Maximum points for channel readiness maturity component', 'admin'),
('maturity.authority_expert_points', '10', 'integer', 'maturity', 'Maturity Authority Expert Points', 'Points for expert authority in maturity scoring', 'admin'),
('maturity.authority_wikidata_points', '5', 'integer', 'maturity', 'Maturity Authority Wikidata Points', 'Points for Wikidata linkage in maturity scoring', 'admin'),
('maturity.authority_cert_points', '5', 'integer', 'maturity', 'Maturity Authority Cert Points', 'Points for certifications in maturity scoring', 'admin'),
('kpi.hero_ctr_target_pct', '8', 'integer', 'kpi', 'Kpi Hero Ctr Target Pct', 'Target CTR percentage for Hero SKUs', 'admin'),
('kpi.hero_citation_target_pct', '6', 'integer', 'kpi', 'Kpi Hero Citation Target Pct', 'Target AI citation rate percentage for Hero SKUs', 'admin'),
('bulk.batch_limit', '500', 'integer', 'bulk', 'Bulk Batch Limit', 'Maximum SKUs per bulk operation batch', 'admin')
ON DUPLICATE KEY UPDATE
  rule_value = VALUES(rule_value),
  data_type = VALUES(data_type),
  description = VALUES(description),
  module = VALUES(module),
  label = VALUES(label),
  approver_roles = VALUES(approver_roles),
  last_changed_at = CURRENT_TIMESTAMP;
