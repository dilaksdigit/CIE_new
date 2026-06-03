CREATE TABLE erp_sync_log (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 status VARCHAR(50) NOT NULL,
 records_processed INT DEFAULT 0,
 errors INT DEFAULT 0,
 synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
