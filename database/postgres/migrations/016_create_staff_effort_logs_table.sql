CREATE TABLE staff_effort_logs (
    id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
    user_id CHAR(36) NOT NULL,
    sku_id CHAR(36),
    category_id CHAR(36),
    tier TEXT NOT NULL,
    hours_spent DECIMAL(5, 2) NOT NULL,
    activity_type VARCHAR(100), -- e.g., 'CONTENT_WRITE', 'QA_REVIEW', 'GATE_FIX'
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE SET NULL
);
