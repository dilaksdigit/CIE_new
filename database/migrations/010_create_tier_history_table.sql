SET NAMES utf8mb4;

CREATE TABLE tier_history (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 sku_id CHAR(36) NOT NULL,
 old_tier ENUM('hero','support','harvest','kill'),
 new_tier ENUM('hero','support','harvest','kill') NOT NULL,
 reason TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 margin_percent DECIMAL(5, 2),
 annual_volume INT,
 changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 changed_by CHAR(36),
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE,
 FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_sku (sku_id),
 INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
