SET NAMES utf8mb4;

-- SOURCE: CLAUDE.md R3 — no hard-coded thresholds
-- SOURCE: CIE_Master_Developer_Build_Spec.docx §4

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'decay.quorum_pause_minimum', '2', 'integer', 'decay', 'Decay Quorum Pause Minimum', 'Minimum responding engines to pause decay progression', 'admin'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'decay.quorum_pause_minimum');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'audit.citation_fuzzy_ratio_high', '0.8', 'decimal', 'audit', 'Audit Citation Fuzzy Ratio High', 'High fuzzy match threshold for citation score 3', 'admin'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.citation_fuzzy_ratio_high');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'audit.citation_fuzzy_ratio_low', '0.6', 'decimal', 'audit', 'Audit Citation Fuzzy Ratio Low', 'Low fuzzy match threshold for citation score 2', 'admin'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.citation_fuzzy_ratio_low');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'audit.min_engine_question_coverage', '15', 'integer', 'audit', 'Audit Min Engine Question Coverage', 'Minimum scored questions required per engine for aggregate inclusion', 'admin'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.min_engine_question_coverage');
