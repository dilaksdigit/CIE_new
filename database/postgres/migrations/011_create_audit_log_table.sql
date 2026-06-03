CREATE TABLE audit_log (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
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
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
