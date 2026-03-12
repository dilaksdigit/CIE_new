-- SOURCE: CIE Validation Report RBAC-05 | CLAUDE.md Section 7 (Tier System), Section 11 (RBAC)
-- Manual tier override requires sign-off from portfolio_holder (content_lead) AND finance before applying.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tier_change_requests (
    id                      CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id                  CHAR(36) NOT NULL,
    requested_tier          VARCHAR(20) NOT NULL,
    status                  ENUM('pending_portfolio_approval', 'pending_finance_approval', 'approved', 'rejected') NOT NULL DEFAULT 'pending_portfolio_approval',
    requested_by            CHAR(36) NULL,
    portfolio_approved_by   CHAR(36) NULL,
    portfolio_approved_at    TIMESTAMP NULL,
    finance_approved_by     CHAR(36) NULL,
    finance_approved_at     TIMESTAMP NULL,
    applied_at              TIMESTAMP NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tier_change_sku (sku_id),
    INDEX idx_tier_change_status (status),
    FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
