SET NAMES utf8mb4;

CREATE TABLE clusters (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 intent_statement TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
