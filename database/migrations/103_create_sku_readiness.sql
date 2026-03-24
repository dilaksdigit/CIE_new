-- SOURCE: Master Spec §6.5.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sku_readiness (
  sku_id CHAR(36) NOT NULL,
  readiness_google_sge INT DEFAULT 0,
  readiness_amazon INT DEFAULT 0,
  readiness_ai INT DEFAULT 0,
  readiness_website INT DEFAULT 0,
  readiness_avg DECIMAL(6,2) NULL,
  computed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sku_id),
  CONSTRAINT fk_sku_readiness_sku FOREIGN KEY (sku_id) REFERENCES sku_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
