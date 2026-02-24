CREATE TABLE audit_results (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 sku_id CHAR(36) NOT NULL,
 engine_type ENUM('PERPLEXITY', 'OPENAI', 'ANTHROPIC', 'GEMINI') NOT NULL,
 score INT,
 status ENUM('SUCCESS', 'TIMEOUT', 'ERROR', 'UNAVAILABLE') DEFAULT 'SUCCESS',
 response_text TEXT,
 error_message TEXT,
 queried_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE CASCADE,
 INDEX idx_sku_date (sku_id, queried_at),
 INDEX idx_engine (engine_type)
);
