SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6.4
-- Additive-only: add missing GA4 baseline columns and measurement lifecycle enum.

ALTER TABLE gsc_baselines
  ADD COLUMN IF NOT EXISTS baseline_bounce_rate DECIMAL(6,4) NULL AFTER baseline_conversion_rate,
  ADD COLUMN IF NOT EXISTS baseline_revenue_organic DECIMAL(12,2) NULL AFTER baseline_bounce_rate,
  ADD COLUMN IF NOT EXISTS measurement_status ENUM('pending','d15_captured','d30_captured','complete')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' AFTER cis_score;
