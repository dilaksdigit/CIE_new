CREATE TABLE tier_history (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 sku_id CHAR(36) NOT NULL,
 old_tier TEXT,
 new_tier TEXT NOT NULL,
 reason TEXT,
 margin_percent DECIMAL(5, 2),
 annual_volume INT,
 changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 changed_by CHAR(36),
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE,
 FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);
