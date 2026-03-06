SET NAMES utf8mb4;

CREATE TABLE audit_log (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 user_id CHAR(36),
 entity_type VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 entity_id CHAR(36) NOT NULL,
 action VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 field_name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 old_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 new_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 ip_address VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 user_agent TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_entity (entity_type, entity_id),
 INDEX idx_user (user_id),
 INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
