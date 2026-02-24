-- Delete all sample SKU data (from sample JSON + workflow test runs).
-- Related rows in sku_intents, validation_logs, content_briefs, etc. are removed by CASCADE.
-- Run from project root: mysql -u user -p dbname < database/scripts/delete_sample_skus.sql

DELETE FROM skus
WHERE sku_code IN (
  'CBL-BLK-3C-1M',
  'CBL-GLD-3C-1M',
  'CBL-WHT-2C-3M',
  'CBL-RED-3C-2M',
  'SHD-TPE-DRM-35',
  'SHD-GLS-CNE-20',
  'BLB-LED-E27-4W',
  'BLB-LED-B22-8W',
  'PND-SET-BRS-3L',
  'FLR-ARC-BLK-175'
)
OR sku_code LIKE 'CBL-BLK-3C-1M-%';
