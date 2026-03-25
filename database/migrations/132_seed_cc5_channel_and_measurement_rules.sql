-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — ChannelGovernor AI readiness component points; CHS competitive gap; audit scale
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_answer_block_high_pts', '25', 'integer', 'AI readiness component: answer block meets min length'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_answer_block_high_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_answer_block_low_pts', '15', 'integer', 'AI readiness component: answer block below min length but non-empty'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_answer_block_low_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_faq_full_pts', '20', 'integer', 'AI readiness: FAQ count ≥ 3'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_faq_full_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_faq_partial_pts', '10', 'integer', 'AI readiness: FAQ count 1–2'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_faq_partial_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_safety_signal_pts', '15', 'integer', 'AI readiness: expert_authority contains compliance signal'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_safety_signal_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_safety_weak_pts', '8', 'integer', 'AI readiness: expert_authority non-empty without strong signal'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_safety_weak_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_comparison_full_pts', '15', 'integer', 'AI readiness: best_for and not_for both present'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_comparison_full_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_comparison_partial_pts', '8', 'integer', 'AI readiness: one of best_for / not_for'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_comparison_partial_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_structured_full_pts', '15', 'integer', 'AI readiness: Wikidata / structured data present'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_structured_full_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_structured_partial_pts', '8', 'integer', 'AI readiness: structured baseline without Wikidata'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_structured_partial_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_citation_rate_factor', '0.10', 'float', 'Multiplier applied to citation rate (0–100) before cap'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_citation_rate_factor');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.ai_readiness_citation_max_pts', '10', 'integer', 'Cap for citation-derived AI readiness sub-score'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_citation_max_pts');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'semrush.gap_position_threshold', '10', 'integer', 'Positions null or greater than this count as competitive gap keywords (CLAUDE.md §15)'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'semrush.gap_position_threshold');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'audit.citation_score_scale_max', '3', 'integer', 'Max raw citation score in audit (0–3 scale) for CHS normalization'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.citation_score_scale_max');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'readiness.citation_component_scale_multiplier', '10', 'integer', 'Scale channel readiness citation sub-component for API display'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'readiness.citation_component_scale_multiplier');
