-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3; CIE_v2.3.1_Enforcement_Dev_Spec.pdf §3.2
-- FIX: TS-03 — Commercial priority formula scale factors in business_rules (no literals in TierCalculationService)

INSERT INTO business_rules (rule_key, rule_value, data_type, module, label, description, approver_roles) VALUES
('tier.cppc_inverse_scale', '10', 'decimal', 'tier', 'Tier CPPC Inverse Scale', 'Multiplier for (1/CPPC) term in commercial priority score', 'admin'),
('tier.velocity_log_scale', '25', 'decimal', 'tier', 'Tier Velocity Log Scale', 'Multiplier for log10(velocity) term in commercial priority score', 'admin'),
('tier.cppc_floor', '0.001', 'decimal', 'tier', 'Tier CPPC Floor', 'Minimum CPPC value before inverse to avoid division blow-up', 'admin'),
('tier.velocity_floor', '0.001', 'decimal', 'tier', 'Tier Velocity Floor', 'Minimum velocity before log10 in commercial priority score', 'admin'),
('tier.velocity_normalisation_min', '1', 'integer', 'tier', 'Tier Velocity Normalisation Min', 'Minimum cohort max-velocity when all velocities are zero', 'admin')
ON DUPLICATE KEY UPDATE
  rule_value = VALUES(rule_value),
  data_type = VALUES(data_type),
  description = VALUES(description),
  module = VALUES(module),
  label = VALUES(label),
  approver_roles = VALUES(approver_roles),
  last_changed_at = CURRENT_TIMESTAMP;
