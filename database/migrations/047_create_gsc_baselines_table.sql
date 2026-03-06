SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §9.4, §9.5, §10

CREATE TABLE IF NOT EXISTS gsc_baselines (
  id                       INT           NOT NULL AUTO_INCREMENT,
  sku_id                   CHAR(36)      NOT NULL,
  baseline_captured_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- GSC 14-day averages
  baseline_impressions     DECIMAL(10,2) NULL,
  baseline_clicks          DECIMAL(10,2) NULL,
  baseline_ctr             DECIMAL(6,4)  NULL,
  baseline_avg_position    DECIMAL(6,2)  NULL,

  -- GA4 metrics (written by second call)
  baseline_organic_sessions INT          NULL,
  baseline_conversion_rate  DECIMAL(6,4) NULL,
  baseline_revenue          DECIMAL(12,2) NULL,

  -- Status flags
  gsc_status               VARCHAR(20)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',   -- 'captured' | 'unbaselined'
  ga4_status               VARCHAR(20)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',

  change_id                VARCHAR(100)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,

  PRIMARY KEY (id),
  INDEX idx_gsc_baselines_sku (sku_id),
  CONSTRAINT fk_gsc_baselines_sku
    FOREIGN KEY (sku_id) REFERENCES skus(id)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
