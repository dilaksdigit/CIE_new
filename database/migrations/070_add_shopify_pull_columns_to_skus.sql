-- Shopify product pull: full product data sync from Shopify into CIE SKUs.
-- Match by variant SKU when syncing; used by ChannelDeployService and N8N deploy.
-- Stores product ID, variant ID, pricing, status, content, and image URL.
-- SOURCE: Shopify product pull feature; does not alter deploy/publish flow.

SET NAMES utf8mb4;

-- shopify_product_id (link to Shopify product)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_product_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_product_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER updated_by',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_variant_id (the specific matched variant)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_variant_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_variant_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_product_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_synced_at (when we last pulled this SKU from Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_synced_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_synced_at TIMESTAMP NULL DEFAULT NULL AFTER shopify_variant_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_status (active / draft / archived)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_status'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_status VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_synced_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_title (product title as it appears on Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_title'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_status',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_handle (URL slug on Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_handle'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_handle VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_title',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_body_html (product description HTML from Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_body_html'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_body_html MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_handle',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_price (variant price from Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_price'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_price DECIMAL(10,2) NULL DEFAULT NULL AFTER shopify_body_html',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_compare_at_price (was/RRP price from Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_compare_at_price'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_compare_at_price DECIMAL(10,2) NULL DEFAULT NULL AFTER shopify_price',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_image_url (primary product image)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_image_url'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_image_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_compare_at_price',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_product_type (Shopify product type)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_product_type'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_product_type VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_image_url',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_vendor (vendor/brand from Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_vendor'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_vendor VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_product_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- shopify_tags (comma-separated tags from Shopify)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'shopify_tags'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE skus ADD COLUMN shopify_tags TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER shopify_vendor',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for lookups by Shopify product ID
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND INDEX_NAME = 'idx_skus_shopify_product_id'
);
SET @sql = IF(@idx_exists = 0,
  'CREATE INDEX idx_skus_shopify_product_id ON skus(shopify_product_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
