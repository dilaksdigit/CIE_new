-- SOURCE: CIE_Integration_Specification.pdf §1.2 — ERP flags for incomplete/defaulted payload fields
-- ADDITIVE ONLY — no drops

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'erp_data_incomplete'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN erp_data_incomplete TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''SOURCE: CIE_v232_Cloud_Briefing §11 — null/default ERP fields'' AFTER erp_return_rate_pct',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
