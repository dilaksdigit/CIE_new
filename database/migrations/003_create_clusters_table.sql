CREATE TABLE clusters (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 name VARCHAR(255) NOT NULL,
 intent_statement TEXT NOT NULL,
 primary_intent_id CHAR(36),
 centroid_vector JSON,
 last_vector_update TIMESTAMP NULL,
 is_locked BOOLEAN DEFAULT false,
 requires_approval BOOLEAN DEFAULT true,
 approval_status ENUM('DRAFT', 'PENDING', 'APPROVED', 'REJECTED') DEFAULT 'APPROVED',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 created_by CHAR(36),
 INDEX idx_approval_status (approval_status),
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
