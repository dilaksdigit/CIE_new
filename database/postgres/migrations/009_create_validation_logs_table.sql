CREATE TABLE validation_logs (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 sku_id CHAR(36) NOT NULL,
 gate_type TEXT NOT NULL,
 passed BOOLEAN NOT NULL,
 reason TEXT,
 is_blocking BOOLEAN DEFAULT true,
 similarity_score DECIMAL(5, 4),
 validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 validated_by CHAR(36),
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE,
 FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL
);
