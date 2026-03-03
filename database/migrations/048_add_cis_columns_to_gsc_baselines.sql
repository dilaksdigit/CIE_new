-- SOURCE: CIE_Master_Developer_Build_Spec.docx Layer L8 (D+15 / D+30 CIS)

ALTER TABLE gsc_baselines
  ADD COLUMN d15_impressions        DECIMAL(10,2) NULL AFTER baseline_revenue,
  ADD COLUMN d15_clicks             DECIMAL(10,2) NULL AFTER d15_impressions,
  ADD COLUMN d15_ctr                DECIMAL(6,4)  NULL AFTER d15_clicks,
  ADD COLUMN d15_position           DECIMAL(6,2)  NULL AFTER d15_ctr,
  ADD COLUMN d15_sessions           INT           NULL AFTER d15_position,
  ADD COLUMN d15_conversion_rate    DECIMAL(6,4)  NULL AFTER d15_sessions,
  ADD COLUMN d15_revenue            DECIMAL(12,2) NULL AFTER d15_conversion_rate,

  ADD COLUMN d30_impressions        DECIMAL(10,2) NULL AFTER d15_revenue,
  ADD COLUMN d30_clicks             DECIMAL(10,2) NULL AFTER d30_impressions,
  ADD COLUMN d30_ctr                DECIMAL(6,4)  NULL AFTER d30_clicks,
  ADD COLUMN d30_position           DECIMAL(6,2)  NULL AFTER d30_ctr,
  ADD COLUMN d30_sessions           INT           NULL AFTER d30_position,
  ADD COLUMN d30_conversion_rate    DECIMAL(6,4)  NULL AFTER d30_sessions,
  ADD COLUMN d30_revenue            DECIMAL(12,2) NULL AFTER d30_conversion_rate,

  ADD COLUMN cis_score              DECIMAL(8,4)  NULL AFTER d30_revenue;

