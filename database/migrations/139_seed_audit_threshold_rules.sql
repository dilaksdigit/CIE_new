SET NAMES utf8mb4;

-- SOURCE: CLAUDE.md R3 — no hard-coded thresholds
-- SOURCE: CIE_Master_Developer_Build_Spec.docx §4

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'decay.quorum_pause_minimum', '2', 'integer', 'Minimum responding engines to pause decay progression'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'decay.quorum_pause_minimum');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'audit.citation_fuzzy_ratio_high', '0.8', 'float', 'High fuzzy match threshold for citation score 3'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.citation_fuzzy_ratio_high');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'audit.citation_fuzzy_ratio_low', '0.6', 'float', 'Low fuzzy match threshold for citation score 2'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.citation_fuzzy_ratio_low');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'audit.min_engine_question_coverage', '15', 'integer', 'Minimum scored questions required per engine for aggregate inclusion'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.min_engine_question_coverage');
