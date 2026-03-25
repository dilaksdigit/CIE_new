-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — channel score deltas in business_rules (Shopify + GMC per CLAUDE.md §4 DECISION-001)
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.delta_shopify', '10', 'integer', 'SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — score delta for Shopify channel (DECISION-001)'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.delta_shopify');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'channels.delta_gmc', '7', 'integer', 'SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — score delta for GMC channel (DECISION-001)'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'channels.delta_gmc');

INSERT INTO business_rules (id, rule_key, value, value_type, description)
SELECT UUID(), 'audit.engine_count', '4', 'integer', 'SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 2 — AI audit engine count for quorum denominator'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM business_rules WHERE rule_key = 'audit.engine_count');
