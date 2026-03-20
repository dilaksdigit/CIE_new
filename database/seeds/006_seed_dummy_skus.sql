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

-- SKUs population (lowercase tier values per ENUM; data from golden_test_data.json)
-- Step 1: Insert/update the 9 non–Kill-tier SKUs. ON DUPLICATE KEY UPDATE (on sku_code UNIQUE) refreshes
-- cluster link, readiness, and citation on re-runs.
INSERT INTO skus (id, sku_code, title, tier, primary_cluster_id, meta_description, long_description, current_price, cost, margin_percent, annual_volume, erp_cppc, erp_return_rate_pct, readiness_score, score_citation, validation_status) VALUES
(UUID(), 'CBL-BLK-3C-1M', 'Black Braided Pendant Cable Set 3-Core 1m E27', 'hero', @clu_cbl_pe27, '3-core braided pendant cable set with E27 holder. Rated to 60W. Compatible with LED and CFL. Ideal for standard 2.4m ceilings. BS 7671 compliant. Free UK delivery.', 'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings.', 12.99, 4.94, 62.0, 847, 0.18, 2.1, 93, 72, 'VALID'),
(UUID(), 'CBL-GLD-3C-1M', 'Gold Braided Pendant Cable Set 3-Core 1m E27', 'hero', @clu_cbl_pe27, 'Gold braided pendant cable set with E27 holder. Perfect for period properties and art deco schemes. 3-core, BS 7671 compliant. Pairs with brass ceiling roses.', 'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style bulbs. Rated to 60W for LED use, with 1m length for standard ceiling heights.', 14.99, 5.25, 65.0, 621, 0.16, 1.8, 94, 68, 'VALID'),
(UUID(), 'CBL-WHT-2C-3M', 'White Round Flex Cable 2-Core 3m', 'support', @clu_cbl_ext, 'White 2-core round flex cable, 3m length. Ideal for rewiring table lamps and floor lamps. CE marked. Bare ends for custom wiring.', 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use.', 6.99, 3.91, 44.0, 312, 0.42, 3.5, 80, 35, 'VALID'),
(UUID(), 'CBL-RED-3C-2M', 'Red Twisted Pendant Cable 3-Core 2m E27', 'harvest', @clu_cbl_pe27, 'Red twisted 3-core pendant cable 2m with E27 holder. Specification grade.', 'Red twisted pendant cable for stylish ceiling installations.', 16.99, 13.93, 18.0, 14, 1.85, 8.2, 40, 8, 'VALID'),
(UUID(), 'SHD-TPE-DRM-35', 'Fabric Drum Shade Taupe 35cm E27/B22', 'hero', @clu_shd_fab, 'Fabric drum shade in taupe, 35cm diameter. Creates warm, glare-free light for living rooms and bedrooms. Fits E27 and B22 pendants. Fire-retardant.', 'A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Ideal for rooms where softened ambient lighting matters most.', 18.99, 5.51, 71.0, 1240, 0.22, 4.8, 96, 81, 'VALID'),
(UUID(), 'SHD-GLS-CNE-20', 'Opal Glass Cone Shade 20cm E27', 'hero', @clu_shd_gls, 'Opal glass cone shade, 20cm. Bright, focused-yet-diffused light for kitchens and modern spaces. E27 ring fitting. BS EN 60598-1 compliant.', 'An opal glass cone shade delivers brighter, more focused light than fabric alternatives, making it ideal for kitchen pendants and reading nooks. The 20cm diameter suits compact pendants. Opal finish softens harshness while maintaining brightness for tasks.', 22.99, 9.66, 58.0, 534, 0.28, 6.2, 84, 55, 'VALID'),
(UUID(), 'BLB-LED-E27-4W', 'LED Filament Bulb E27 4W 2700K', 'support', @clu_blb_led, 'E27 LED filament bulb, 4W warm white 2700K. 470 lumens. Dimmable. Squirrel cage style. Fits pendant cable sets and table lamps.', 'A 4W LED filament bulb with E27 screw cap produces 470 lumens of warm white light at 2700K, equivalent to a 40W incandescent. Fits standard E27 pendants, table lamps, and floor lamps. Dimmable with compatible trailing-edge dimmer switches.', 4.99, 3.09, 38.0, 2100, 0.31, 1.2, 74, 42, 'VALID'),
(UUID(), 'BLB-LED-B22-8W', 'LED GLS Bulb B22 8W 4000K', 'support', @clu_blb_led, 'B22 bayonet LED bulb, 8W cool white 4000K. 806 lumens, equivalent to 60W. Ideal for kitchens and workspaces. CE and RoHS compliant.', 'An 8W LED GLS bulb with B22 bayonet cap produces 806 lumens of cool white light at 4000K, equivalent to a traditional 60W bulb. Designed for kitchen ceiling fittings and workspaces where bright, clear illumination is needed. Non-dimmable.', 3.99, 2.59, 35.0, 1850, 0.38, 1.5, 70, 28, 'VALID'),
(UUID(), 'PND-SET-BRS-3L', 'Brass 3-Light Pendant Cluster Set', 'hero', @clu_pnd_clu, 'Brass 3-light pendant cluster set with E27 holders. Statement lighting for kitchen islands and dining tables. BS 7671 compliant. Adjustable drop length.', 'A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. Antique brass finish suits both period and contemporary interiors.', 89.99, 40.50, 55.0, 289, 0.35, 3.8, 94, 63, 'VALID')
ON DUPLICATE KEY UPDATE
  primary_cluster_id = VALUES(primary_cluster_id),
  title = VALUES(title),
  meta_description = VALUES(meta_description),
  long_description = VALUES(long_description),
  current_price = VALUES(current_price),
  cost = VALUES(cost),
  margin_percent = VALUES(margin_percent),
  annual_volume = VALUES(annual_volume),
  erp_cppc = VALUES(erp_cppc),
  erp_return_rate_pct = VALUES(erp_return_rate_pct),
  readiness_score = VALUES(readiness_score),
  score_citation = VALUES(score_citation),
  validation_status = VALUES(validation_status);

-- Step 2: Insert the Kill-tier SKU only if it does not exist (INSERT IGNORE). Never UPDATE a kill-tier row,
-- so the prevent_kill_tier_update trigger (034) is never fired when re-running this seed.
INSERT IGNORE INTO skus (id, sku_code, title, tier, primary_cluster_id, meta_description, long_description, current_price, cost, margin_percent, annual_volume, erp_cppc, erp_return_rate_pct, readiness_score, score_citation, validation_status) VALUES
(UUID(), 'FLR-ARC-BLK-175', 'Black Arc Floor Lamp 175cm E27', 'kill', @clu_flr_arc, 'Arc floor lamp. 175cm. E27. Black. Discontinued.', 'Contemporary arc floor lamp with E27 socket.', 49.99, 52.09, -4.2, 3, 2.85, 22.0, 0, 0, 'VALID');
