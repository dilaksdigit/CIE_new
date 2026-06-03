CREATE TABLE channel_mappings (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 channel_name VARCHAR(100) NOT NULL,
 sku_id CHAR(36) NOT NULL,
 external_reference VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE
);
