-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5, §5.2, §7, §18
-- Phase 5 hardening rules (additive only). Uses existing business_rules schema.
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description) VALUES
(UUID(), 'gates.description_min_words', '50', 'integer', 'Minimum word count for product description (G6 gate)'),
(UUID(), 'gates.title_max_chars', '250', 'integer', 'Maximum character length for generated titles'),
(UUID(), 'gates.meta_desc_max_chars', '160', 'integer', 'Maximum character length for meta description'),
(UUID(), 'maturity.ai_visibility_max', '15', 'integer', 'Maximum points for AI visibility maturity component'),
(UUID(), 'maturity.core_pillar_points', '10', 'integer', 'Points per core pillar in maturity scoring'),
(UUID(), 'maturity.channel_max', '25', 'integer', 'Maximum points for channel readiness maturity component'),
(UUID(), 'maturity.authority_expert_points', '10', 'integer', 'Points for expert authority in maturity scoring'),
(UUID(), 'maturity.authority_wikidata_points', '5', 'integer', 'Points for Wikidata linkage in maturity scoring'),
(UUID(), 'maturity.authority_cert_points', '5', 'integer', 'Points for certifications in maturity scoring'),
(UUID(), 'kpi.hero_ctr_target_pct', '8', 'integer', 'Target CTR percentage for Hero SKUs'),
(UUID(), 'kpi.hero_citation_target_pct', '6', 'integer', 'Target AI citation rate percentage for Hero SKUs'),
(UUID(), 'bulk.batch_limit', '500', 'integer', 'Maximum SKUs per bulk operation batch')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
