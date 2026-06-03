CREATE TABLE sku_intents (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 sku_id CHAR(36) NOT NULL,
 intent_id CHAR(36) NOT NULL,
 cluster_id CHAR(36) NOT NULL,
 is_primary BOOLEAN DEFAULT false,
 assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE,
 FOREIGN KEY (intent_id) REFERENCES intents(id) ON DELETE CASCADE,
 FOREIGN KEY (cluster_id) REFERENCES clusters(id) ON DELETE CASCADE
);
