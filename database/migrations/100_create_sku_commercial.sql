-- SOURCE: Master Spec §6.3.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sku_commercial (
  sku_id CHAR(36) NOT NULL,
  margin_pct DECIMAL(6,4) NULL,
  cppc DECIMAL(8,4) NULL,
  velocity_90d INT NULL,
  return_rate_pct DECIMAL(6,4) NULL,
  margin_class ENUM('low','medium','high','premium') NULL,
  erp_sync_date TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (sku_id),
  CONSTRAINT fk_sku_commercial_sku FOREIGN KEY (sku_id) REFERENCES sku_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
