SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §10.2 — GA4 weekly pull
-- Stores GA4 landing page performance per 7-day window (Organic Search only).

CREATE TABLE IF NOT EXISTS ga4_landing_performance (
  id                INT           NOT NULL AUTO_INCREMENT,
  landing_page      VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  window_end        DATE          NOT NULL,
  sessions          INT           NULL,
  conversion_rate   DECIMAL(6,4)  NULL,
  revenue           DECIMAL(12,2) NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_ga4_landing_window (window_end),
  INDEX idx_ga4_landing_page (landing_page(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
