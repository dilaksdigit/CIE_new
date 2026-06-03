-- CIE v2.3.2 remediation: in-app decay notifications + revenue-at-risk flag

CREATE TABLE IF NOT EXISTS notifications (
  id CHAR(36) PRIMARY KEY,
  notifiable_type VARCHAR(255) NOT NULL,
  notifiable_id VARCHAR(50) NOT NULL,
  type VARCHAR(255) NOT NULL,
  data JSON NOT NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE skus
  ADD COLUMN IF NOT EXISTS revenue_at_risk BOOLEAN NOT NULL DEFAULT FALSE;
