-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Compatibility table for CIS D+15 / D+30 row-based measurements.

CREATE TABLE IF NOT EXISTS cis_measurements (
  id                   CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  sku_id               VARCHAR(50) NOT NULL,
  measurement_day      TEXT NOT NULL,
  baseline_impressions INT NULL,
  post_impressions     INT NULL,
  baseline_position    DECIMAL(8,2) NULL,
  post_position        DECIMAL(8,2) NULL,
  cis_score            DECIMAL(8,2) NULL,
  measured_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cis_measurements_sku
    FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE
);
