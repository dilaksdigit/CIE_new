SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §2 (zero hard-coded values)
-- Add explicit small-improvement keys used by CIS D+30 scoring logic.

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'cis.position_small_improvement_threshold', '1', 'integer', 'cis', 'CIS Position Small Improvement Threshold', 'Minimum position improvement for small CIS points', 'admin'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'cis.position_small_improvement_threshold');

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles)
SELECT 'cis.position_small_improvement_points', '20', 'integer', 'cis', 'CIS Position Small Improvement Points', 'CIS points for small position improvement', 'admin'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'cis.position_small_improvement_points');
