-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5.3

INSERT IGNORE INTO business_rules
(rule_key, rule_value, data_type, module, label, approver_roles)
VALUES
('gates.validate_sla_ms', '500', 'integer', 'gates',
 'Gate validation SLA threshold in milliseconds', 'admin');
