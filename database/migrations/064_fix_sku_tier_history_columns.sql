-- SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.2
-- NOTE: sku_tier_history is the canonical spec table name.
-- The audit check 'tier_assignments' refers to this table.

CREATE TABLE IF NOT EXISTS sku_tier_history (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id CHAR(36) NOT NULL,
    old_tier ENUM('hero','support','harvest','kill') NULL,
    new_tier ENUM('hero','support','harvest','kill') NOT NULL,
    reason ENUM('auto_recalc','manual_override','approval','erp_sync','other') NOT NULL DEFAULT 'other',
    approved_by CHAR(36) NULL,
    second_approver CHAR(36) NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sth_sku (sku_id),
    INDEX idx_sth_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sku_tier_history
    ADD COLUMN IF NOT EXISTS approved_by CHAR(36) NULL,
    ADD COLUMN IF NOT EXISTS second_approver CHAR(36) NULL;
