SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §9.2 — GSC weekly pull
-- Stores GSC URL performance per 7-day window (impressions, clicks, ctr, avg_position).

CREATE TABLE IF NOT EXISTS url_performance (
  id                INT           NOT NULL AUTO_INCREMENT,
  url               VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  window_end        DATE          NOT NULL,
  impressions       DECIMAL(10,2) NULL,
  clicks            DECIMAL(10,2) NULL,
  ctr               DECIMAL(6,4)  NULL,
  avg_position      DECIMAL(6,2)  NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_url_performance_window (window_end),
  INDEX idx_url_performance_url (url(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
