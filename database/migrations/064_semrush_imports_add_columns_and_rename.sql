-- SOURCE: CIE Validation Report DB-06 | CLAUDE.md Section 9; CIE_v232_Semrush_CSV_Import_Spec
SET NAMES utf8mb4;

-- Add missing columns
ALTER TABLE semrush_imports
  ADD COLUMN intent VARCHAR(100) NULL AFTER search_volume,
  ADD COLUMN sku_code VARCHAR(100) NULL AFTER intent,
  ADD COLUMN cluster_id VARCHAR(50) NULL AFTER sku_code;

-- Rename columns (use CHANGE for rename)
ALTER TABLE semrush_imports
  CHANGE COLUMN keyword_diff keyword_difficulty INT NULL,
  CHANGE COLUMN url competitor_url VARCHAR(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

-- FK: cluster_master.cluster_id is VARCHAR(50)
ALTER TABLE semrush_imports
  ADD CONSTRAINT fk_semrush_cluster
  FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id) ON DELETE SET NULL;
