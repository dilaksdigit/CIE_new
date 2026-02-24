CREATE TABLE tier_history (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 sku_id CHAR(36) NOT NULL,
 old_tier ENUM('HERO', 'SUPPORT', 'HARVEST', 'KILL'),
 new_tier ENUM('HERO', 'SUPPORT', 'HARVEST', 'KILL') NOT NULL,
 reason TEXT,
 margin_percent DECIMAL(5, 2),
 annual_volume INT,
 changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 changed_by CHAR(36),
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE,
 FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_sku (sku_id),
 INDEX idx_changed_at (changed_at)
);
