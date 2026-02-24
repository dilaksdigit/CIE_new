-- 006_seed_dummy_skus.sql

-- Clusters setup (INSERT IGNORE ensures we don't duplicate if they exist)
INSERT IGNORE INTO clusters (id, name, intent_statement) VALUES 
('CLU-CBL-P-E27', 'Pendant Cable Set E27', 'Connect and power a pendant light fitting safely and stylishly'),
('CLU-CBL-EXT', 'Extension Flex', 'Extend or replace existing lamp cable safely'),
('CLU-SHD-FAB', 'Fabric Drum Shade', 'Create warm, even, glare-free lighting in living spaces'),
('CLU-SHD-GLS', 'Glass Cone Shade', 'Provide bright, focused-yet-diffused lighting with a premium material finish'),
('CLU-BLB-LED', 'LED Bulb', 'Find the right bulb for existing lamp fittings'),
('CLU-PND-CLU', 'Cluster Pendant Set', 'Create a statement multi-light pendant display for dining or kitchen islands'),
('CLU-FLR-ARC', 'Arc Floor Lamp', 'Floor lamps with an arc design');

-- SKUs population
INSERT INTO skus (id, sku_code, title, tier, primary_cluster_id, meta_description, long_description, current_price, cost, margin_percent, annual_volume, erp_cppc, erp_return_rate_pct, readiness_score, validation_status) VALUES
(UUID(), 'CBL-BLK-3C-1M', 'Black Braided Pendant Cable Set 3-Core 1m E27', 'HERO', 'CLU-CBL-P-E27', '3-core braided cable set. Rated to 60W. LED/CFL compatible. BS 7671 compliant.', 'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings.', 12.99, 4.94, 62.0, 847, 0.45, 1.2, 93, 'VALID'),
(UUID(), 'CBL-GLD-3C-1M', 'Gold Braided Pendant Cable Set 3-Core 1m E27', 'HERO', 'CLU-CBL-P-E27', 'Gold braided cable with E27 holder. Period properties. 3-core. BS 7671 compliant.', 'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style bulbs. Rated to 60W for LED use.', 14.99, 5.25, 65.0, 621, 0.55, 0.8, 94, 'VALID'),
(UUID(), 'CBL-WHT-2C-3M', 'White Round Flex Cable 2-Core 3m', 'SUPPORT', 'CLU-CBL-EXT', '2-core white flex cable. 3m length. Rewiring lamps. CE marked. Bare ends.', 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Custom termination with your plug and lamp holder.', 6.99, 3.91, 44.0, 312, 0.25, 2.5, 80, 'VALID'),
(UUID(), 'CBL-RED-3C-2M', 'Red Twisted Pendant Cable 3-Core 2m E27', 'HARVEST', 'CLU-CBL-P-E27', 'Red twisted cable. 3-core. 2m. E27 holder.', 'Red twisted pendant cable for stylish ceiling installations.', 16.99, 13.93, 18.0, 14, 0.85, 5.5, 40, 'VALID'),
(UUID(), 'SHD-TPE-DRM-35', 'Fabric Drum Shade Taupe 35cm E27/B22', 'HERO', 'CLU-SHD-FAB', 'Taupe drum shade. 35cm. Warm glare-free light. E27/B22. Fire-retardant.', 'A fabric drum lampshade in taupe diffuses light for warm illumination. 35cm diameter suits ceiling pendants and floor lamps with E27/B22 fittings.', 18.99, 5.51, 71.0, 1240, 0.35, 1.1, 96, 'VALID'),
(UUID(), 'SHD-GLS-CNE-20', 'Opal Glass Cone Shade 20cm E27', 'HERO', 'CLU-SHD-GLS', 'Opal glass cone shade. 20cm. Bright focused light. E27. BS EN compliant.', 'An opal glass cone shade delivers focused light for kitchens and reading nooks. Compact 20cm diameter. Softens harshness while maintaining brightness.', 22.99, 9.66, 58.0, 534, 1.20, 3.2, 84, 'VALID'),
(UUID(), 'BLB-LED-E27-4W', 'LED Filament Bulb E27 4W 2700K', 'SUPPORT', 'CLU-BLB-LED', 'E27 LED filament. 4W. 2700K warm white. 470 lumens. Dimmable.', 'A 4W LED filament with E27 cap produces warm white light. Dimmable. Standard E27 pendants and lamps.', 4.99, 3.09, 38.0, 2100, 0.15, 0.5, 74, 'VALID'),
(UUID(), 'BLB-LED-B22-8W', 'LED GLS Bulb B22 8W 4000K', 'SUPPORT', 'CLU-BLB-LED', 'B22 LED bulb. 8W. 4000K cool white. 806 lumens. Kitchens and workspaces.', 'An 8W LED with B22 bayonet cap. Cool white light equivalent to 60W. For kitchen fittings and workspaces.', 3.99, 2.59, 35.0, 1850, 0.10, 0.4, 70, 'VALID'),
(UUID(), 'PND-SET-BRS-3L', 'Brass 3-Light Pendant Cluster Set', 'HERO', 'CLU-PND-CLU', 'Brass 3-light cluster. Kitchen islands. Statement lighting. BS 7671. Adjustable.', 'A brass pendant cluster creates balanced illumination for kitchen islands. Three adjustable E27 drops. Antique brass finish.', 89.99, 40.50, 55.0, 289, 2.50, 4.5, 94, 'VALID'),
(UUID(), 'FLR-ARC-BLK-175', 'Black Arc Floor Lamp 175cm E27', 'KILL', 'CLU-FLR-ARC', 'Arc floor lamp. 175cm. E27. Black.', 'Contemporary arc floor lamp with E27 socket.', 49.99, 52.09, -4.2, 3, 0.05, 12.0, 0, 'VALID');
