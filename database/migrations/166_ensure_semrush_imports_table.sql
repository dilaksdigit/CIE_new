-- SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.1; CLAUDE.md §13
-- FIX: greenfield init — ensure semrush_imports exists after migration 148 no-op (MySQL reference dialect)

CREATE TABLE IF NOT EXISTS semrush_imports (
  id INT NOT NULL AUTO_INCREMENT,
  import_batch VARCHAR(20) NOT NULL,
  keyword VARCHAR(500) NOT NULL,
  position INT NULL,
  prev_position INT NULL,
  search_volume INT NULL,
  keyword_diff INT NULL,
  keyword_difficulty INT NULL,
  url VARCHAR(1000) NULL,
  competitor_url VARCHAR(1000) NULL,
  traffic_pct DECIMAL(6,2) NULL,
  trend VARCHAR(200) NULL,
  intent VARCHAR(100) NULL,
  sku_code VARCHAR(100) NULL,
  cluster_id VARCHAR(100) NULL,
  competitor_position INT NULL,
  import_batch_id VARCHAR(36) NULL,
  imported_by VARCHAR(100) NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_keyword (keyword(100)),
  INDEX idx_batch_keyword (import_batch, keyword(100))
);
