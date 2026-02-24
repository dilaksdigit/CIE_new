-- CIE v2.3.2 Fail-Soft: validation retry queue for vector (and other gates) when API is down
-- Referenced by backend/php G4_VectorGate.php

CREATE TABLE IF NOT EXISTS validation_retry_queue (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id VARCHAR(50) NOT NULL,
    gate_code VARCHAR(20) NOT NULL,
    retry_count INT NOT NULL DEFAULT 0,
    next_retry_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_retry_sku_gate (sku_id, gate_code),
    INDEX idx_retry_next (next_retry_at)
);
