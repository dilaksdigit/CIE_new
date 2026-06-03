-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — ChannelGovernor AI readiness component points; CHS competitive gap; audit scale

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_answer_block_high_pts', '25', 'integer', 'channel', 'AI Readiness Answer Block High Points', 'AI readiness component: answer block meets min length', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_answer_block_high_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_answer_block_low_pts', '15', 'integer', 'channel', 'AI Readiness Answer Block Low Points', 'AI readiness component: answer block below min length but non-empty', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_answer_block_low_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_faq_full_pts', '20', 'integer', 'channel', 'AI Readiness FAQ Full Points', 'AI readiness: FAQ count ≥ 3', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_faq_full_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_faq_partial_pts', '10', 'integer', 'channel', 'AI Readiness FAQ Partial Points', 'AI readiness: FAQ count 1–2', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_faq_partial_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_safety_signal_pts', '15', 'integer', 'channel', 'AI Readiness Safety Signal Points', 'AI readiness: expert_authority contains compliance signal', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_safety_signal_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_safety_weak_pts', '8', 'integer', 'channel', 'AI Readiness Safety Weak Points', 'AI readiness: expert_authority non-empty without strong signal', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_safety_weak_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_comparison_full_pts', '15', 'integer', 'channel', 'AI Readiness Comparison Full Points', 'AI readiness: best_for and not_for both present', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_comparison_full_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_comparison_partial_pts', '8', 'integer', 'channel', 'AI Readiness Comparison Partial Points', 'AI readiness: one of best_for / not_for', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_comparison_partial_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_structured_full_pts', '15', 'integer', 'channel', 'AI Readiness Structured Full Points', 'AI readiness: Wikidata / structured data present', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_structured_full_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_structured_partial_pts', '8', 'integer', 'channel', 'AI Readiness Structured Partial Points', 'AI readiness: structured baseline without Wikidata', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_structured_partial_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_citation_rate_factor', '0.10', 'decimal', 'channel', 'AI Readiness Citation Rate Factor', 'Multiplier applied to citation rate (0–100) before cap', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_citation_rate_factor');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'channels.ai_readiness_citation_max_pts', '10', 'integer', 'channel', 'AI Readiness Citation Max Points', 'Cap for citation-derived AI readiness sub-score', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.ai_readiness_citation_max_pts');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'semrush.gap_position_threshold', '10', 'integer', 'semrush', 'Semrush Gap Position Threshold', 'Positions null or greater than this count as competitive gap keywords (CLAUDE.md §15)', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'semrush.gap_position_threshold');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'audit.citation_score_scale_max', '3', 'integer', 'audit', 'Audit Citation Score Scale Max', 'Max raw citation score in audit (0–3 scale) for CHS normalization', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.citation_score_scale_max');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'readiness.citation_component_scale_multiplier', '10', 'integer', 'readiness', 'Readiness Citation Component Scale Multiplier', 'Scale channel readiness citation sub-component for API display', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'readiness.citation_component_scale_multiplier');
