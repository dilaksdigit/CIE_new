-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6 — gsc_baselines table

-- DB-18 Data Migration Required
-- Old table: cis_measurements (denormalized D15/D30 columns per row)
-- New table: gsc_baselines (measurement_status enum, same columns)
-- Action: Migrate existing rows to gsc_baselines before dropping cis_measurements.
-- Escalate to project owner before executing DROP.

CREATE TABLE IF NOT EXISTS gsc_baselines (
    baseline_id                 CHAR(36)       PRIMARY KEY DEFAULT (UUID()),
    sku_id                      CHAR(36)       NOT NULL,
    captured_at                 TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    triggered_by                CHAR(36)       NULL,
    baseline_impressions        INT            NULL,
    baseline_clicks             INT            NULL,
    baseline_ctr                DECIMAL(6,4)   NULL,
    baseline_avg_position       DECIMAL(8,2)   NULL,
    baseline_organic_sessions   INT            NULL,
    baseline_conversion_rate    DECIMAL(8,6)   NULL,
    baseline_revenue_organic    DECIMAL(12,2)  NULL,
    baseline_bounce_rate        DECIMAL(6,4)   NULL,
    d15_impressions             INT            NULL,
    d15_ctr                     DECIMAL(6,4)   NULL,
    d15_avg_position            DECIMAL(8,2)   NULL,
    d15_organic_sessions        INT            NULL,
    d15_conversion_rate         DECIMAL(8,6)   NULL,
    d30_impressions             INT            NULL,
    d30_ctr                     DECIMAL(6,4)   NULL,
    d30_avg_position            DECIMAL(8,2)   NULL,
    d30_organic_sessions        INT            NULL,
    d30_conversion_rate         DECIMAL(8,6)   NULL,
    d30_revenue_organic         DECIMAL(12,2)  NULL,
    cis_score                   DECIMAL(6,2)   NULL,
    measurement_status          ENUM('pending','d15_captured','d30_captured','complete')
                                NOT NULL DEFAULT 'pending',
    FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE,
    INDEX idx_gbs_sku (sku_id),
    INDEX idx_gbs_status (measurement_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
