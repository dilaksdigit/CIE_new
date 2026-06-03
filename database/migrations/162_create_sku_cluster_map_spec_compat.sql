-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Compatibility table for explicit SKU↔cluster relationship mapping.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sku_cluster_map (
  id         CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  sku_id     VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  cluster_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  is_primary BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sku_cluster_map_sku
    FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE,
  CONSTRAINT fk_sku_cluster_map_cluster
    FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id) ON DELETE CASCADE,
  UNIQUE KEY uq_sku_cluster_map (sku_id, cluster_id),
  INDEX idx_sku_cluster_map_cluster (cluster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
