-- SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.2
-- NOTE: sku_tier_history is the canonical spec table name.
-- The audit check 'tier_assignments' refers to this table.

CREATE TABLE IF NOT EXISTS sku_tier_history (
    id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
    sku_id CHAR(36) NOT NULL,
    old_tier TEXT NULL,
    new_tier TEXT NOT NULL,
    reason TEXT NOT NULL DEFAULT 'other',
    approved_by CHAR(36) NULL,
    second_approver CHAR(36) NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE sku_tier_history
    ADD COLUMN IF NOT EXISTS approved_by CHAR(36) NULL,
    ADD COLUMN IF NOT EXISTS second_approver CHAR(36) NULL;
