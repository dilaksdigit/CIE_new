-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
CREATE TABLE IF NOT EXISTS gsc_data (
    id              CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    url             VARCHAR(500) NOT NULL,
    impressions     INT NOT NULL DEFAULT 0,
    clicks          INT NOT NULL DEFAULT 0,
    ctr             DECIMAL(8,6) NOT NULL DEFAULT 0,
    avg_position    DECIMAL(8,2),
    data_date       DATE NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gsc_url (url(255)),
    INDEX idx_gsc_date (data_date),
    UNIQUE KEY uq_gsc_url_date (url(255), data_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
