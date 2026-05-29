-- CIE v2.3.2 remediation: in-app decay notifications + revenue-at-risk flag
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id CHAR(36) PRIMARY KEY,
  notifiable_type VARCHAR(255) NOT NULL,
  notifiable_id VARCHAR(50) NOT NULL,
  type VARCHAR(255) NOT NULL,
  data JSON NOT NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_notifications_notifiable (notifiable_type, notifiable_id),
  INDEX idx_notifications_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE skus
  ADD COLUMN IF NOT EXISTS revenue_at_risk BOOLEAN NOT NULL DEFAULT FALSE;
