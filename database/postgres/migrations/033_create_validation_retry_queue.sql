-- CIE v2.3.2 Fail-Soft: vector retry queue for embedding API failures
-- SOURCE: CIE_v232_Hardening_Addendum.pdf §1.2

DROP TABLE IF EXISTS validation_retry_queue;

CREATE TABLE IF NOT EXISTS vector_retry_queue (
    id            CHAR(36) PRIMARY KEY DEFAULT gen_random_uuid()::text,
    sku_id        VARCHAR(50) NOT NULL,
    description   TEXT NOT NULL,
    cluster_id    VARCHAR(50) NOT NULL,
    retry_count   SMALLINT NOT NULL DEFAULT 0,
    max_retries   SMALLINT NOT NULL DEFAULT 5,
    next_retry_at TIMESTAMP NOT NULL DEFAULT (NOW() + INTERVAL 5 MINUTE),
    status        VARCHAR(20) NOT NULL DEFAULT 'queued'
                  CHECK (status IN ('queued','processing','resolved','failed')),
    error_message VARCHAR(500),
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at   TIMESTAMP NULL
);

CREATE INDEX idx_retry_status ON vector_retry_queue(status, next_retry_at);

-- SOURCE: PG-native — status values enforced at application layer (TEXT column from prior migrations).
