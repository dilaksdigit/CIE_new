SET NAMES utf8mb4;

-- SOURCE: CLAUDE.md §9, §13 — semrush_content_snapshots auto-created when content is published.
-- Snapshots track 30 days then auto-conclude (concluded_at set by separate process; not implemented here).

CREATE TABLE IF NOT EXISTS semrush_content_snapshots (
  id                INT           NOT NULL AUTO_INCREMENT,
  sku_id            CHAR(36)      NOT NULL,
  import_batch_id   CHAR(36)      NULL COMMENT 'Links to semrush_imports.import_batch_id',
  snapshot_date     TIMESTAMP     NOT NULL COMMENT 'UTC',
  concluded_at      TIMESTAMP     NULL     COMMENT 'UTC; set when 30-day window closes',
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC',
  PRIMARY KEY (id),
  INDEX idx_semrush_snapshots_sku (sku_id),
  INDEX idx_semrush_snapshots_date (snapshot_date),
  CONSTRAINT fk_semrush_content_snapshots_sku
    FOREIGN KEY (sku_id) REFERENCES skus(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
