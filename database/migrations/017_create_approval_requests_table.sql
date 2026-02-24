CREATE TABLE approval_requests (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    requester_id CHAR(36) NOT NULL,
    entity_type ENUM('SKU_TIER', 'BULK_CLUSTER', 'POLICY_OVERRIDE') NOT NULL,
    entity_id CHAR(36) NOT NULL,
    requested_change JSON NOT NULL, -- e.g. {"old_tier": "KILL", "new_tier": "HERO"}
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') DEFAULT 'PENDING',
    finance_approver_id CHAR(36),
    commercial_approver_id CHAR(36),
    finance_approved_at TIMESTAMP NULL,
    commercial_approved_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (finance_approver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (commercial_approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_entity (entity_type, entity_id)
);
