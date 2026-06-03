-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Compatibility table for explicit tier assignment history contract.

CREATE TABLE IF NOT EXISTS tier_assignments (
  id         CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  sku_id     VARCHAR(50) NOT NULL,
  old_tier   TEXT NULL,
  new_tier   TEXT NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by CHAR(36) NULL,
  reason     VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tier_assignments_sku
    FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE
);
