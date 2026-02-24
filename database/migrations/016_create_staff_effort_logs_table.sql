CREATE TABLE staff_effort_logs (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    sku_id CHAR(36),
    category_id CHAR(36),
    tier ENUM('HERO', 'SUPPORT', 'HARVEST', 'KILL') NOT NULL,
    hours_spent DECIMAL(5, 2) NOT NULL,
    activity_type VARCHAR(100), -- e.g., 'CONTENT_WRITE', 'QA_REVIEW', 'GATE_FIX'
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, logged_at),
    INDEX idx_tier_date (tier, logged_at),
    INDEX idx_sku (sku_id)
);
