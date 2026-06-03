-- Add erp_sync_date to track when each SKU was last updated by the ERP sync.
-- SOURCE: openapi.yaml ErpSyncPayload schema; CIE_Integration_Specification.pdf §1.2

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'erp_sync_date'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN erp_sync_date TIMESTAMP NULL DEFAULT NULL AFTER erp_return_rate_pct',
  'SELECT 1'
);
-- TODO(pg): removed MySQL PREPARE/EXECUTE block;
