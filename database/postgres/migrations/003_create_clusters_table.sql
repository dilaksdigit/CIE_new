CREATE TABLE clusters (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 name VARCHAR(255) NOT NULL,
 intent_statement TEXT NOT NULL,
 primary_intent_id CHAR(36),
 centroid_vector JSON,
 last_vector_update TIMESTAMP NULL,
 is_locked BOOLEAN DEFAULT false,
 requires_approval BOOLEAN DEFAULT true,
 approval_status TEXT DEFAULT 'APPROVED',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 created_by CHAR(36),

 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
