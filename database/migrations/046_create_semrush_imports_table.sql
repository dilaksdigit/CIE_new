SET NAMES utf8mb4;

-- SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx Section 3.1 (Table Definition)

CREATE TABLE semrush_imports (
  id INT NOT NULL AUTO_INCREMENT,
  import_batch VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,   -- ISO date: '2026-02-19'
  keyword VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  position INT NULL,
  prev_position INT NULL,
  search_volume INT NULL,
  keyword_diff INT NULL,
  url VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  traffic_pct DECIMAL(6,2) NULL,
  trend VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  competitor_position INT NULL,
  import_batch_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  imported_by VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_batch (import_batch),
  INDEX idx_keyword (keyword(100)),
  INDEX idx_batch_keyword (import_batch, keyword(100)),
  INDEX idx_batch_id (import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
