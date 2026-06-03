-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Compatibility table for explicit tier assignment history contract.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tier_assignments (
  id         CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  sku_id     VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  old_tier   ENUM('hero','support','harvest','kill') NULL,
  new_tier   ENUM('hero','support','harvest','kill') NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by CHAR(36) NULL,
  reason     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tier_assignments_sku
    FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE,
  INDEX idx_tier_assignments_changed_at (changed_at),
  INDEX idx_tier_assignments_sku (sku_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
