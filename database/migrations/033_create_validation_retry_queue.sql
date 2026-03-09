SET NAMES utf8mb4;

-- CIE v2.3.2 Fail-Soft: vector retry queue for embedding API failures
-- SOURCE: CIE_v232_Hardening_Addendum.pdf §1.2

DROP TABLE IF EXISTS validation_retry_queue;

CREATE TABLE IF NOT EXISTS vector_retry_queue (
    id            CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sku_id        VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    description   TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    cluster_id    VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    retry_count   SMALLINT NOT NULL DEFAULT 0,
    max_retries   SMALLINT NOT NULL DEFAULT 5,
    next_retry_at TIMESTAMP NOT NULL DEFAULT (NOW() + INTERVAL 5 MINUTE),
    status        VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued'
                  CHECK (status IN ('queued','processing','resolved','failed')),
    error_message VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at   TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_retry_status ON vector_retry_queue(status, next_retry_at);

ALTER TABLE sku_gate_status
    MODIFY COLUMN status VARCHAR(20) NOT NULL;
-- Valid values: pass, fail, not_applicable, pending
