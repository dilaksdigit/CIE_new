-- v2.3.2 Hardening Patch Tables
-- Adds support tables for fail-soft validation, FAQ templates, and cluster governance.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- 1. validation_retry_queue
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS validation_retry_queue (
  id            CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  sku_id        VARCHAR(50) NOT NULL,
  gate_code     VARCHAR(10) NOT NULL,
  retry_count   INT NOT NULL DEFAULT 0,
  next_retry_at TIMESTAMP NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_validation_retry_sku_gate (sku_id, gate_code),
  INDEX idx_validation_retry_next_retry (next_retry_at)
);

-- -------------------------------------------------------------------
-- 2. faq_templates
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faq_templates (
  id            CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  product_class VARCHAR(50) NOT NULL,
  question      TEXT NOT NULL,
  is_required   BOOLEAN NOT NULL DEFAULT TRUE,
  display_order INT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_faq_templates_product_class (product_class),
  INDEX idx_faq_templates_display_order (display_order)
);

-- -------------------------------------------------------------------
-- 3. sku_faqs
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sku_faqs (
  id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  sku_id      VARCHAR(50) NOT NULL,
  template_id CHAR(36) NULL,
  answer      TEXT NOT NULL,
  approved    BOOLEAN NOT NULL DEFAULT FALSE,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
              ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sku_faqs_sku (sku_id),
  INDEX idx_sku_faqs_template (template_id)
  -- Template FK is optional to allow ad-hoc FAQs:
  -- FOREIGN KEY (template_id) REFERENCES faq_templates(id) ON DELETE SET NULL
);

-- -------------------------------------------------------------------
-- 4. cluster_review_log
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cluster_review_log (
  id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  cluster_id  VARCHAR(50) NOT NULL,
  review_date DATE NOT NULL,
  sku_count   INT NOT NULL,
  decision    ENUM('keep', 'merge', 'archive') NOT NULL,
  reviewed_by VARCHAR(100) NOT NULL,
  notes       TEXT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cluster_review_cluster (cluster_id),
  INDEX idx_cluster_review_date (review_date)
);

SET FOREIGN_KEY_CHECKS = 1;

