-- SOURCE: CIE_Master_Developer_Build_Spec.docx §10.2 — GA4 weekly pull
-- Stores GA4 landing page performance per 7-day window (Organic Search only).

CREATE TABLE IF NOT EXISTS ga4_landing_performance (
  id                INT           NOT NULL ,
  landing_page      VARCHAR(1000) NOT NULL,
  window_end        DATE          NOT NULL,
  sessions          INT           NULL,
  conversion_rate   DECIMAL(6,4)  NULL,
  revenue           DECIMAL(12,2) NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),

  INDEX idx_ga4_landing_page (landing_page(100))
);
