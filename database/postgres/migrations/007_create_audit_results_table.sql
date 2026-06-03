CREATE TABLE audit_results (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 sku_id CHAR(36) NOT NULL,
 engine_type TEXT NOT NULL,
 score INT,
 status TEXT DEFAULT 'SUCCESS',
 response_text TEXT,
 error_message TEXT,
 queried_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE
);
