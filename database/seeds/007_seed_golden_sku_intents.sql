-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1, 7 (G2, G3)
-- SOURCE: CIE_v232_FINAL_Developer_Instruction.docx Phase 1 Task 1.2
-- SOURCE: CLAUDE.md Section 9 — Intent stored as ENUM matching the 9-intent taxonomy exactly
--
-- Seeds primary and secondary intents for the 10 golden SKUs.
-- Uses the v1 sku_intents table (used by gates) and the canonical sku_master + sku_secondary_intents tables.
--
-- Intent assignments (from golden_test_data.json use_case):
--   CBL-BLK-3C-1M  (Hero)    : primary=compatibility,  secondary=installation, specification
--   CBL-GLD-3C-1M  (Hero)    : primary=inspiration,     secondary=compatibility, specification
--   CBL-WHT-2C-3M  (Support) : primary=specification,  secondary=compatibility
--   SHD-TPE-DRM-35 (Hero)    : primary=problem_solving, secondary=comparison, replacement
--   SHD-GLS-CNE-20 (Hero)    : primary=comparison,     secondary=problem_solving, specification
--   BLB-LED-E27-4W (Support) : primary=compatibility,  secondary=specification
--   BLB-LED-B22-8W (Support) : primary=specification,  secondary=compatibility
--   PND-SET-BRS-3L (Hero)    : primary=problem_solving, secondary=inspiration, installation
--   CBL-RED-3C-2M  (Harvest) : primary=specification,  no secondary
--   FLR-ARC-BLK-175 (Kill)   : primary=specification,  no secondary

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Update skus.title to include intent keyword stems (required for G2 title-keyword check)
-- ---------------------------------------------------------------------------
-- 1. Update skus.title to include intent keyword stems (required for G2)
UPDATE skus SET title = 'Pendant Cable Set for Ceiling Lights - Compatibility & Safe Wiring | 3-Core Braided 1m E27'
WHERE sku_code = 'CBL-BLK-3C-1M';

UPDATE skus SET title = 'Statement Gold Pendant Cable for Period Properties | Inspiration & Braided 3-Core 1m E27'
WHERE sku_code = 'CBL-GLD-3C-1M';

UPDATE skus SET title = 'Replacement Flex Cable for Table and Floor Lamps | 2-Core White PVC 3m Specification'
WHERE sku_code = 'CBL-WHT-2C-3M';

UPDATE skus SET title = 'Warm Glare-Free Lighting for Living Rooms | Fabric Drum Shade Taupe 35cm Solution'
WHERE sku_code = 'SHD-TPE-DRM-35';

UPDATE skus SET title = 'Bright Focused Kitchen Pendant Lighting | Opal Glass Cone Shade 20cm E27 Comparison'
WHERE sku_code = 'SHD-GLS-CNE-20';

UPDATE skus SET title = 'LED Bulb for E27 Pendant and Table Lamps - Compatibility & Warm Filament Glow | 4W 2700K 470lm'
WHERE sku_code = 'BLB-LED-E27-4W';

UPDATE skus SET title = 'Bright LED Bulb for B22 Kitchen and Ceiling Lights | 8W 4000K Specification 806lm'
WHERE sku_code = 'BLB-LED-B22-8W';

UPDATE skus SET title = 'Statement Kitchen Island Lighting Solution | Brass 3-Light Pendant Cluster E27'
WHERE sku_code = 'PND-SET-BRS-3L';

-- CBL-RED-3C-2M already has "Specification" - leave as is
UPDATE skus SET title = 'Red Twisted 3-Core Pendant Cable 2m E27 – Product Specification'
WHERE sku_code = 'CBL-RED-3C-2M';
-- ---------------------------------------------------------------------------
-- 2. Seed sku_intents (v1 table) — primary intents
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(),
       s.id,
       i.id,
       s.primary_cluster_id,
       TRUE
FROM skus s
JOIN intents i ON i.name = 'compatibility'
WHERE s.sku_code = 'CBL-BLK-3C-1M';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'inspiration'
WHERE s.sku_code = 'CBL-GLD-3C-1M';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'CBL-WHT-2C-3M';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'problem_solving'
WHERE s.sku_code = 'SHD-TPE-DRM-35';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'comparison'
WHERE s.sku_code = 'SHD-GLS-CNE-20';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'compatibility'
WHERE s.sku_code = 'BLB-LED-E27-4W';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'BLB-LED-B22-8W';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'problem_solving'
WHERE s.sku_code = 'PND-SET-BRS-3L';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'CBL-RED-3C-2M';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, TRUE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'FLR-ARC-BLK-175';

-- ---------------------------------------------------------------------------
-- 3. Seed sku_intents (v1 table) — secondary intents
-- ---------------------------------------------------------------------------
-- CBL-BLK-3C-1M: installation, specification
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'installation'
WHERE s.sku_code = 'CBL-BLK-3C-1M';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'CBL-BLK-3C-1M';

-- CBL-GLD-3C-1M: compatibility, specification
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'compatibility'
WHERE s.sku_code = 'CBL-GLD-3C-1M';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'CBL-GLD-3C-1M';

-- CBL-WHT-2C-3M: compatibility
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'compatibility'
WHERE s.sku_code = 'CBL-WHT-2C-3M';

-- SHD-TPE-DRM-35: comparison, replacement
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'comparison'
WHERE s.sku_code = 'SHD-TPE-DRM-35';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'replacement'
WHERE s.sku_code = 'SHD-TPE-DRM-35';

-- SHD-GLS-CNE-20: problem_solving, specification
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'problem_solving'
WHERE s.sku_code = 'SHD-GLS-CNE-20';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'SHD-GLS-CNE-20';

-- BLB-LED-E27-4W: specification
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'specification'
WHERE s.sku_code = 'BLB-LED-E27-4W';

-- BLB-LED-B22-8W: compatibility
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'compatibility'
WHERE s.sku_code = 'BLB-LED-B22-8W';

-- PND-SET-BRS-3L: inspiration, installation
INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'inspiration'
WHERE s.sku_code = 'PND-SET-BRS-3L';

INSERT IGNORE INTO sku_intents (id, sku_id, intent_id, cluster_id, is_primary)
SELECT UUID(), s.id, i.id, s.primary_cluster_id, FALSE
FROM skus s JOIN intents i ON i.name = 'installation'
WHERE s.sku_code = 'PND-SET-BRS-3L';

-- CBL-RED-3C-2M (Harvest): no secondary intents
-- FLR-ARC-BLK-175 (Kill): no secondary intents

-- ---------------------------------------------------------------------------
-- 4. Seed canonical sku_master (required for sku_secondary_intents FK)
-- ---------------------------------------------------------------------------
INSERT INTO sku_master (sku_id, cluster_id, tier, primary_intent_id, status)
VALUES
  ('CBL-BLK-3C-1M',  'CLU-CBL-P-E27', 'hero',    3, 'draft'),
  ('CBL-GLD-3C-1M',  'CLU-CBL-P-E27', 'hero',    4, 'draft'),
  ('CBL-WHT-2C-3M',  'CLU-CBL-EXT',   'support', 5, 'draft'),
  ('SHD-TPE-DRM-35', 'CLU-SHD-FAB',   'hero',    1, 'draft'),
  ('SHD-GLS-CNE-20', 'CLU-SHD-GLS',   'hero',    2, 'draft'),
  ('BLB-LED-E27-4W', 'CLU-BLB-LED',   'support', 3, 'draft'),
  ('BLB-LED-B22-8W', 'CLU-BLB-LED',   'support', 5, 'draft'),
  ('PND-SET-BRS-3L', 'CLU-PND-CLU',   'hero',    1, 'draft'),
  ('CBL-RED-3C-2M',  'CLU-CBL-P-E27', 'harvest', 5, 'draft'),
  ('FLR-ARC-BLK-175','CLU-FLR-ARC',   'kill',    5, 'draft')
ON DUPLICATE KEY UPDATE
  cluster_id       = VALUES(cluster_id),
  tier             = VALUES(tier),
  primary_intent_id = VALUES(primary_intent_id);

-- ---------------------------------------------------------------------------
-- 5. Seed canonical sku_secondary_intents
-- ---------------------------------------------------------------------------
-- Hero/Support SKUs: min 1 secondary intent each
INSERT INTO sku_secondary_intents (id, sku_id, intent_id, ordinal)
VALUES
  (UUID(), 'CBL-BLK-3C-1M', 6, 1),
  (UUID(), 'CBL-BLK-3C-1M', 5, 2),
  (UUID(), 'CBL-GLD-3C-1M', 3, 1),
  (UUID(), 'CBL-GLD-3C-1M', 5, 2),
  (UUID(), 'CBL-WHT-2C-3M', 3, 1),
  (UUID(), 'SHD-TPE-DRM-35', 2, 1),
  (UUID(), 'SHD-TPE-DRM-35', 8, 2),
  (UUID(), 'SHD-GLS-CNE-20', 1, 1),
  (UUID(), 'SHD-GLS-CNE-20', 5, 2),
  (UUID(), 'BLB-LED-E27-4W', 5, 1),
  (UUID(), 'BLB-LED-B22-8W', 3, 1),
  (UUID(), 'PND-SET-BRS-3L', 4, 1),
  (UUID(), 'PND-SET-BRS-3L', 6, 2)
ON DUPLICATE KEY UPDATE
  ordinal = VALUES(ordinal);
