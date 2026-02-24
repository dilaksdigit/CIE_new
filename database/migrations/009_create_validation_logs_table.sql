CREATE TABLE validation_logs (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 sku_id CHAR(36) NOT NULL,
 gate_type ENUM('G1_BASIC_INFO', 'G2_IMAGES', 'G3_SEO', 'G4_VECTOR', 'G5_TECHNICAL', 'G6_COMMERCIAL', 'G7_EXPERT') NOT NULL,
 passed BOOLEAN NOT NULL,
 reason TEXT,
 is_blocking BOOLEAN DEFAULT true,
 similarity_score DECIMAL(5, 4),
 validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 validated_by CHAR(36),
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE,
 FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_sku_gate (sku_id, gate_type),
 INDEX idx_validated_at (validated_at)
);
