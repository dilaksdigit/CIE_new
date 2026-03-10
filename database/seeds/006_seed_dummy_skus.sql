-- 006_seed_dummy_skus.sql

-- Clusters setup (INSERT IGNORE ensures we don't duplicate if they exist)
-- SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.2 — canonical table is cluster_master
INSERT IGNORE INTO cluster_master (cluster_id, category, intent_statement, intent_vector) VALUES 
('CLU-CBL-P-E27', 'cables', 'Connect and power a pendant light fitting safely and stylishly', '[]'),
('CLU-CBL-EXT', 'cables', 'Extend or replace existing lamp cable safely', '[]'),
('CLU-SHD-FAB', 'lampshades', 'Create warm, even, glare-free lighting in living spaces', '[]'),
('CLU-SHD-GLS', 'lampshades', 'Provide bright, focused-yet-diffused lighting with a premium material finish', '[]'),
('CLU-BLB-LED', 'bulbs', 'Find the right bulb for existing lamp fittings', '[]'),
('CLU-PND-CLU', 'pendants', 'Create a statement multi-light pendant display for dining or kitchen islands', '[]'),
('CLU-FLR-ARC', 'floor_lamps', 'Floor lamps with an arc design', '[]');

-- Seed the `clusters` table (INSERT only if name does not already exist — no UNIQUE on name)
INSERT INTO clusters (id, name, intent_statement, category)
SELECT UUID(), 'CLU-CBL-P-E27', 'Connect and power a pendant light fitting safely and stylishly', 'cables'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clusters WHERE name = 'CLU-CBL-P-E27');

INSERT INTO clusters (id, name, intent_statement, category)
SELECT UUID(), 'CLU-CBL-EXT', 'Extend or replace existing lamp cable safely', 'cables'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clusters WHERE name = 'CLU-CBL-EXT');

INSERT INTO clusters (id, name, intent_statement, category)
SELECT UUID(), 'CLU-SHD-FAB', 'Create warm, even, glare-free lighting in living spaces', 'lampshades'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clusters WHERE name = 'CLU-SHD-FAB');

INSERT INTO clusters (id, name, intent_statement, category)
SELECT UUID(), 'CLU-SHD-GLS', 'Provide bright, focused-yet-diffused lighting with a premium material finish', 'lampshades'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clusters WHERE name = 'CLU-SHD-GLS');

INSERT INTO clusters (id, name, intent_statement, category)
SELECT UUID(), 'CLU-BLB-LED', 'Find the right bulb for existing lamp fittings', 'bulbs'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clusters WHERE name = 'CLU-BLB-LED');

INSERT INTO clusters (id, name, intent_statement, category)
SELECT UUID(), 'CLU-PND-CLU', 'Create a statement multi-light pendant display for dining or kitchen islands', 'pendants'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clusters WHERE name = 'CLU-PND-CLU');

INSERT INTO clusters (id, name, intent_statement, category)
SELECT UUID(), 'CLU-FLR-ARC', 'Floor lamps with an arc design', 'floor_lamps'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clusters WHERE name = 'CLU-FLR-ARC');

-- Look up actual cluster IDs (works whether newly inserted or pre-existing)
SELECT id INTO @clu_cbl_pe27 FROM clusters WHERE name = 'CLU-CBL-P-E27' LIMIT 1;
SELECT id INTO @clu_cbl_ext  FROM clusters WHERE name = 'CLU-CBL-EXT'   LIMIT 1;
SELECT id INTO @clu_shd_fab  FROM clusters WHERE name = 'CLU-SHD-FAB'   LIMIT 1;
SELECT id INTO @clu_shd_gls  FROM clusters WHERE name = 'CLU-SHD-GLS'   LIMIT 1;
SELECT id INTO @clu_blb_led  FROM clusters WHERE name = 'CLU-BLB-LED'   LIMIT 1;
SELECT id INTO @clu_pnd_clu  FROM clusters WHERE name = 'CLU-PND-CLU'   LIMIT 1;
SELECT id INTO @clu_flr_arc  FROM clusters WHERE name = 'CLU-FLR-ARC'   LIMIT 1;

-- SKUs population (lowercase tier values per ENUM definition in 004_create_skus_table.sql)
-- ON DUPLICATE KEY UPDATE (on sku_code UNIQUE) refreshes cluster link, readiness, and citation on re-runs.
INSERT INTO skus (id, sku_code, title, tier, primary_cluster_id, meta_description, long_description, current_price, cost, margin_percent, annual_volume, erp_cppc, erp_return_rate_pct, readiness_score, score_citation, validation_status) VALUES
(UUID(), 'CBL-BLK-3C-1M', 'Black Braided Pendant Cable Set 3-Core 1m E27', 'hero', @clu_cbl_pe27, '3-core braided cable set. Rated to 60W. LED/CFL compatible. BS 7671 compliant.', 'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings.', 12.99, 4.94, 62.0, 847, 0.45, 1.2, 93, 72, 'VALID'),
(UUID(), 'CBL-GLD-3C-1M', 'Gold Braided Pendant Cable Set 3-Core 1m E27', 'hero', @clu_cbl_pe27, 'Gold braided cable with E27 holder. Period properties. 3-core. BS 7671 compliant.', 'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style bulbs. Rated to 60W for LED use.', 14.99, 5.25, 65.0, 621, 0.55, 0.8, 94, 68, 'VALID'),
(UUID(), 'CBL-WHT-2C-3M', 'White Round Flex Cable 2-Core 3m', 'support', @clu_cbl_ext, '2-core white flex cable. 3m length. Rewiring lamps. CE marked. Bare ends.', 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Custom termination with your plug and lamp holder.', 6.99, 3.91, 44.0, 312, 0.25, 2.5, 80, 35, 'VALID'),
(UUID(), 'CBL-RED-3C-2M', 'Red Twisted Pendant Cable 3-Core 2m E27', 'harvest', @clu_cbl_pe27, 'Red twisted cable. 3-core. 2m. E27 holder.', 'Red twisted pendant cable for stylish ceiling installations.', 16.99, 13.93, 18.0, 14, 0.85, 5.5, 40, 8, 'VALID'),
(UUID(), 'SHD-TPE-DRM-35', 'Fabric Drum Shade Taupe 35cm E27/B22', 'hero', @clu_shd_fab, 'Taupe drum shade. 35cm. Warm glare-free light. E27/B22. Fire-retardant.', 'A fabric drum lampshade in taupe diffuses light for warm illumination. 35cm diameter suits ceiling pendants and floor lamps with E27/B22 fittings.', 18.99, 5.51, 71.0, 1240, 0.35, 1.1, 96, 81, 'VALID'),
(UUID(), 'SHD-GLS-CNE-20', 'Opal Glass Cone Shade 20cm E27', 'hero', @clu_shd_gls, 'Opal glass cone shade. 20cm. Bright focused light. E27. BS EN compliant.', 'An opal glass cone shade delivers focused light for kitchens and reading nooks. Compact 20cm diameter. Softens harshness while maintaining brightness.', 22.99, 9.66, 58.0, 534, 1.20, 3.2, 84, 55, 'VALID'),
(UUID(), 'BLB-LED-E27-4W', 'LED Filament Bulb E27 4W 2700K', 'support', @clu_blb_led, 'E27 LED filament. 4W. 2700K warm white. 470 lumens. Dimmable.', 'A 4W LED filament with E27 cap produces warm white light. Dimmable. Standard E27 pendants and lamps.', 4.99, 3.09, 38.0, 2100, 0.15, 0.5, 74, 42, 'VALID'),
(UUID(), 'BLB-LED-B22-8W', 'LED GLS Bulb B22 8W 4000K', 'support', @clu_blb_led, 'B22 LED bulb. 8W. 4000K cool white. 806 lumens. Kitchens and workspaces.', 'An 8W LED with B22 bayonet cap. Cool white light equivalent to 60W. For kitchen fittings and workspaces.', 3.99, 2.59, 35.0, 1850, 0.10, 0.4, 70, 28, 'VALID'),
(UUID(), 'PND-SET-BRS-3L', 'Brass 3-Light Pendant Cluster Set', 'hero', @clu_pnd_clu, 'Brass 3-light cluster. Kitchen islands. Statement lighting. BS 7671. Adjustable.', 'A brass pendant cluster creates balanced illumination for kitchen islands. Three adjustable E27 drops. Antique brass finish.', 89.99, 40.50, 55.0, 289, 2.50, 4.5, 94, 63, 'VALID'),
(UUID(), 'FLR-ARC-BLK-175', 'Black Arc Floor Lamp 175cm E27', 'kill', @clu_flr_arc, 'Arc floor lamp. 175cm. E27. Black.', 'Contemporary arc floor lamp with E27 socket.', 49.99, 52.09, -4.2, 3, 0.05, 12.0, 0, 0, 'VALID')
ON DUPLICATE KEY UPDATE
  primary_cluster_id = VALUES(primary_cluster_id),
  readiness_score = VALUES(readiness_score),
  score_citation = VALUES(score_citation),
  validation_status = VALUES(validation_status);
