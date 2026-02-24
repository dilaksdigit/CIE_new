CREATE TABLE audit_log (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 user_id CHAR(36),
 entity_type VARCHAR(50) NOT NULL,
 entity_id CHAR(36) NOT NULL,
 action VARCHAR(50) NOT NULL,
 field_name VARCHAR(100),
 old_value TEXT,
 new_value TEXT,
 ip_address VARCHAR(45),
 user_agent TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_entity (entity_type, entity_id),
 INDEX idx_user (user_id),
 INDEX idx_created_at (created_at)
);
