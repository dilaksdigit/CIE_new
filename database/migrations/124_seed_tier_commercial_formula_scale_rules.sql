-- SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3; CIE_v2.3.1_Enforcement_Dev_Spec.pdf §3.2
-- FIX: TS-03 — Commercial priority formula scale factors in business_rules (no literals in TierCalculationService)
SET NAMES utf8mb4;

INSERT INTO business_rules (id, rule_key, value, value_type, description) VALUES
(UUID(), 'tier.cppc_inverse_scale', '10', 'float', 'Multiplier for (1/CPPC) term in commercial priority score'),
(UUID(), 'tier.velocity_log_scale', '25', 'float', 'Multiplier for log10(velocity) term in commercial priority score'),
(UUID(), 'tier.cppc_floor', '0.001', 'float', 'Minimum CPPC value before inverse to avoid division blow-up'),
(UUID(), 'tier.velocity_floor', '0.001', 'float', 'Minimum velocity before log10 in commercial priority score'),
(UUID(), 'tier.velocity_normalisation_min', '1', 'integer', 'Minimum cohort max-velocity when all velocities are zero')
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
