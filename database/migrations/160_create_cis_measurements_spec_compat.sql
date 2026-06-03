-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Compatibility table for CIS D+15 / D+30 row-based measurements.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cis_measurements (
  id                   CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  sku_id               VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  measurement_day      ENUM('15','30') NOT NULL,
  baseline_impressions INT NULL,
  post_impressions     INT NULL,
  baseline_position    DECIMAL(8,2) NULL,
  post_position        DECIMAL(8,2) NULL,
  cis_score            DECIMAL(8,2) NULL,
  measured_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cis_measurements_sku
    FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE,
  UNIQUE KEY uq_cis_measurements_sku_day (sku_id, measurement_day),
  INDEX idx_cis_measurements_measured_at (measured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
