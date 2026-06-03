CREATE TABLE cluster_vectors (
 id CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
 cluster_id CHAR(36) NOT NULL,
 vector JSON NOT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (cluster_id) REFERENCES clusters(id) ON DELETE CASCADE
);
