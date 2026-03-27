SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.4
-- Align GA4 D+15/D+30 column names and precisions to spec, additive where needed.

ALTER TABLE gsc_baselines
  ADD COLUMN IF NOT EXISTS d15_organic_sessions INT NULL AFTER d15_position,
  MODIFY COLUMN d15_conversion_rate DECIMAL(8,6) NULL,
  ADD COLUMN IF NOT EXISTS d30_organic_sessions INT NULL AFTER d30_position,
  MODIFY COLUMN d30_conversion_rate DECIMAL(8,6) NULL,
  ADD COLUMN IF NOT EXISTS d30_revenue_organic DECIMAL(12,2) NULL AFTER d30_conversion_rate,
  MODIFY COLUMN cis_score DECIMAL(6,2) NULL;
