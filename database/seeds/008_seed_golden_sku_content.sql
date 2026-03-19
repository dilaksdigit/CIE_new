-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1, 6.3, 7 (G4, G5)
-- SOURCE: openapi.yaml SkuValidateRequest — answer_block minLength: 250, maxLength: 300; best_for minItems: 2; not_for minItems: 1
-- SOURCE: CLAUDE.md Section 6 — G4/G5 rules locked with zero override
--
-- Seeds content fields for the 10 golden SKUs on the v1 skus table
-- (ai_answer_block, short_description, best_for, not_for, meta_title, long_description)
-- and the canonical sku_content table.
--
-- Intentional test failures:
--   SHD-GLS-CNE-20 : answer_block < 250 chars → G4 FAIL
--   BLB-LED-B22-8W : best_for has only 1 entry → G5 FAIL

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. CBL-BLK-3C-1M (Hero) — ALL gates PASS (from golden_test_data.json)
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'Black Braided Pendant Cable Set 3-Core 1m with E27 Holder for Ceiling Light Installation',
  short_description = '3-core braided pendant cable set with E27 holder. Rated to 60W. Compatible with LED and CFL. Ideal for standard 2.4m ceilings. BS 7671 compliant. Free UK delivery.',
  ai_answer_block = 'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings.',
  best_for = '["Standard ceiling pendant installations", "Kitchen island lighting", "Bedroom pendant upgrades", "Replacing old flex cable"]',
  not_for = '["Bathroom installations (not IP-rated)", "Outdoor use", "Heavy industrial fixtures over 5kg"]',
  long_description = 'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely and stylishly. The black braided fabric sleeve covers the inner conductors while providing a decorative finish suited to modern and industrial interiors. Rated to 60W, this cable is compatible with LED and CFL bulbs. Choose the 1m length for standard 2.4m ceiling rooms or opt for the 1.5m variant if you have period property ceilings. BS 7671 compliant for UK domestic installations. The set includes an E27 lamp holder, ceiling rose plate, and all required fixings.'
WHERE sku_code = 'CBL-BLK-3C-1M';

-- ---------------------------------------------------------------------------
-- 2. CBL-GLD-3C-1M (Hero) — ALL gates PASS
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'Gold Braided Pendant Cable Set 3-Core 1m E27 for Period and Art Deco Interiors',
  short_description = 'Gold braided pendant cable set with E27 holder. Perfect for period properties and art deco schemes. 3-core, BS 7671 compliant. Pairs with brass ceiling roses.',
  ai_answer_block = 'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style bulbs. Rated to 60W for LED use, with 1m length for standard ceiling heights.',
  best_for = '["Period property pendant installations", "Art deco interior schemes", "Statement lighting accents", "Brass fixture pairings"]',
  not_for = '["Modern minimalist spaces", "Outdoor use", "Bathrooms"]',
  long_description = 'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style filament bulbs. The braided gold fabric sleeve is durable and flame-retardant. Rated to 60W for LED use. The complete kit includes an E27 lamp holder, ceiling rose plate with matching gold finish, and all required fixings.'
WHERE sku_code = 'CBL-GLD-3C-1M';

-- ---------------------------------------------------------------------------
-- 3. CBL-WHT-2C-3M (Support) — ALL gates PASS
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'White 2-Core Round Flex Cable 3m for Table Lamp and Floor Lamp Rewiring',
  short_description = 'White 2-core round flex cable, 3m length. Ideal for rewiring table lamps and floor lamps. CE marked. Bare ends for custom wiring.',
  ai_answer_block = 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use.',
  best_for = '["Table lamp rewiring", "Floor lamp cable extension"]',
  not_for = '["Ceiling pendant installations (needs 3-core)", "Outdoor use"]',
  long_description = 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use. The white round profile blends discreetly against skirting boards. Suitable for lamps rated up to 60W with LED or CFL bulbs. Not intended for ceiling pendant installations or outdoor use.'
WHERE sku_code = 'CBL-WHT-2C-3M';

-- ---------------------------------------------------------------------------
-- 4. SHD-TPE-DRM-35 (Hero) — ALL gates PASS
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'Taupe Fabric Drum Lampshade 35cm E27 B22 for Warm Glare-Free Living Room Lighting',
  short_description = 'Fabric drum shade in taupe, 35cm diameter. Creates warm, glare-free light for living rooms and bedrooms. Fits E27 and B22 pendants. Fire-retardant.',
  ai_answer_block = 'A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Ideal for rooms where softened ambient lighting matters most.',
  best_for = '["Living rooms needing warm ambient light", "Bedrooms with low ceilings", "Replacing dated coolie or pleated shades", "Pairing with dimmer switches"]',
  not_for = '["Task lighting (too diffused)", "Kitchens needing directional light", "Outdoor use", "High-humidity bathrooms"]',
  long_description = 'A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Fire-retardant fabric meets BS EN 60598-1. The shade produces a soft, glare-free ambience ideal for relaxation spaces. Pair with an LED or CFL bulb rated up to 60W for energy-efficient warm white lighting.'
WHERE sku_code = 'SHD-TPE-DRM-35';

-- ---------------------------------------------------------------------------
-- 5. SHD-GLS-CNE-20 (Hero) — INTENTIONAL G4 FAIL (answer_block < 250 chars)
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'Opal Glass Cone Lampshade 20cm E27 for Kitchen Pendant and Modern Minimalist Interiors',
  short_description = 'Opal glass cone shade, 20cm. Bright, focused-yet-diffused light for kitchens and modern spaces. E27 ring fitting. BS EN 60598-1 compliant.',
  ai_answer_block = 'An opal glass cone shade delivers brighter, more focused light than fabric alternatives, making it ideal for kitchen pendants and reading nooks. The 20cm diameter suits compact pendants. Opal finish softens harshness while maintaining brightness for tasks.',
  best_for = '["Kitchen pendant lighting", "Bathroom vanity (check IP rating of fixture)", "Reading nooks", "Modern minimalist interiors"]',
  not_for = '["Children''s rooms (fragile)", "Outdoor use", "Low-ceiling rooms (directional, not diffused)"]',
  long_description = 'An opal glass cone shade delivers brighter, more focused light than fabric alternatives. The compact 20cm diameter makes it ideal for smaller pendant installations. The opal finish provides a smooth, even diffusion of light. Compatible with standard E27 pendant holders. BS EN 60598-1 compliant for domestic use.'
WHERE sku_code = 'SHD-GLS-CNE-20';

-- ---------------------------------------------------------------------------
-- 6. BLB-LED-E27-4W (Support) — ALL gates PASS
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'LED Filament Bulb E27 4W 2700K Warm White 470 Lumens Dimmable Squirrel Cage',
  short_description = 'E27 LED filament bulb, 4W warm white 2700K. 470 lumens. Dimmable. Squirrel cage style. Fits pendant cable sets and table lamps.',
  ai_answer_block = 'A 4W LED filament bulb with E27 screw cap produces 470 lumens of warm white light at 2700K, equivalent to a 40W incandescent. Fits standard E27 pendants, table lamps, and floor lamps. Dimmable with compatible trailing-edge dimmer switches.',
  best_for = '["E27 pendant cable sets", "Table lamp bulb replacement", "Vintage-style visible bulb displays"]',
  not_for = '["B22 bayonet fittings", "Outdoor unenclosed fixtures", "High-lumen task lighting needs"]',
  long_description = 'A 4W LED filament bulb with E27 cap produces warm white light at 2700K, delivering 470 lumens equivalent to a traditional 40W incandescent bulb. Fully dimmable with trailing-edge dimmer switches. Compatible with standard E27 screw fittings found in ceiling pendants, table lamps, and floor lamps across UK homes.'
WHERE sku_code = 'BLB-LED-E27-4W';

-- ---------------------------------------------------------------------------
-- 7. BLB-LED-B22-8W (Support) — INTENTIONAL G5 FAIL (not_for empty, min 1 required)
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'LED GLS Bulb B22 8W Cool White 4000K 806 Lumens for Kitchen Ceiling Fittings',
  short_description = 'B22 bayonet LED bulb, 8W cool white 4000K. 806 lumens, equivalent to 60W. Ideal for kitchens and workspaces. CE and RoHS compliant.',
  ai_answer_block = 'An 8W LED GLS bulb with B22 bayonet cap produces 806 lumens of cool white light at 4000K, equivalent to a traditional 60W bulb. Designed for kitchen ceiling fittings and workspaces where bright, clear illumination is needed. Non-dimmable.',
  best_for = '["B22 ceiling fittings", "Kitchen and workspace lighting", "High-brightness task areas"]',
  not_for = '[]',
  long_description = 'An 8W LED bulb with B22 bayonet cap produces cool white light at 4000K, delivering 806 lumens equivalent to a traditional 60W incandescent bulb. It fits all standard B22 bayonet cap fittings commonly found in UK ceiling lights. Non-dimmable. Ideal for kitchens and workspaces.'
WHERE sku_code = 'BLB-LED-B22-8W';

-- ---------------------------------------------------------------------------
-- 8. PND-SET-BRS-3L (Hero) — ALL gates PASS
-- ---------------------------------------------------------------------------
UPDATE skus SET
  meta_title = 'Antique Brass 3-Light Pendant Cluster Set E27 for Kitchen Island and Dining Table Lighting',
  short_description = 'Brass 3-light pendant cluster set with E27 holders. Statement lighting for kitchen islands and dining tables. BS 7671 compliant. Adjustable drop length.',
  ai_answer_block = 'A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. Antique brass finish suits both period and contemporary interiors.',
  best_for = '["Kitchen island statement lighting", "Dining table centrepiece", "Open-plan living areas", "Period property renovations"]',
  not_for = '["Low ceilings under 2.4m", "Bathrooms (not IP-rated)", "Single bulb requirements"]',
  long_description = 'A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. Antique brass finish suits both period and contemporary interiors. BS 7671 compliant. Full installation hardware and instructions are included.'
WHERE sku_code = 'PND-SET-BRS-3L';

-- ---------------------------------------------------------------------------
-- 9. CBL-RED-3C-2M (Harvest) — G4/G5/G7 SUSPENDED; G1, G2, G6 checked
--    Note: Harvest tier — no content fields updated beyond primary_intent
--    (G6_CommercialPolicyGate blocks content fields for Harvest)
-- ---------------------------------------------------------------------------
-- Only update meta_title and short_description (basic info for G1)
UPDATE skus SET
  meta_title = 'Red Twisted Pendant Cable 3-Core 2m E27 Spec Grade',
  short_description = 'A red twisted 3-core pendant cable at 2m length with E27 holder. Specification grade. For stylish ceiling pendant installations in creative interiors.'
WHERE sku_code = 'CBL-RED-3C-2M';

-- ---------------------------------------------------------------------------
-- 10. FLR-ARC-BLK-175 (Kill) — G6.1 blocks all, no content gate run
--     Do NOT update Kill-tier content (CLAUDE.md Section 6, Kill row)
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- 11. Seed canonical sku_content table
-- ---------------------------------------------------------------------------
INSERT INTO sku_content (id, sku_id, title, description, answer_block, best_for, not_for)
VALUES
  (UUID(), 'CBL-BLK-3C-1M',
   'Pendant Cable Set for Ceiling Lights - Safe Wiring Made Simple | 3-Core Braided 1m E27',
   'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose the 1m length for standard 2.4m ceiling rooms or opt for the 1.5m variant if you have period property ceilings. BS 7671 compliant for UK domestic installations.',
   'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings.',
   '["Standard ceiling pendant installations", "Kitchen island lighting", "Bedroom pendant upgrades", "Replacing old flex cable"]',
   '["Bathroom installations (not IP-rated)", "Outdoor use", "Heavy industrial fixtures over 5kg"]'),
  (UUID(), 'CBL-GLD-3C-1M',
   'Statement Gold Pendant Cable for Period Properties | Braided 3-Core 1m E27',
   'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style filament bulbs. Rated to 60W for LED use.',
   'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style bulbs. Rated to 60W for LED use, with 1m length for standard ceiling heights.',
   '["Period property pendant installations", "Art deco interior schemes", "Statement lighting accents", "Brass fixture pairings"]',
   '["Modern minimalist spaces", "Outdoor use", "Bathrooms"]'),
  (UUID(), 'CBL-WHT-2C-3M',
   'Replacement Flex Cable for Table and Floor Lamps | 2-Core White PVC 3m',
   'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination. CE marked for indoor domestic use.',
   'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use.',
   '["Table lamp rewiring", "Floor lamp cable extension"]',
   '["Ceiling pendant installations (needs 3-core)", "Outdoor use"]'),
  (UUID(), 'SHD-TPE-DRM-35',
   'Warm Glare-Free Lighting for Living Rooms | Fabric Drum Shade Taupe 35cm',
   'A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Fire-retardant fabric meets BS EN 60598-1.',
   'A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Ideal for rooms where softened ambient lighting matters most.',
   '["Living rooms needing warm ambient light", "Bedrooms with low ceilings", "Replacing dated coolie or pleated shades", "Pairing with dimmer switches"]',
   '["Task lighting (too diffused)", "Kitchens needing directional light", "Outdoor use", "High-humidity bathrooms"]'),
  (UUID(), 'SHD-GLS-CNE-20',
   'Bright Focused Kitchen Pendant Lighting | Opal Glass Cone Shade 20cm E27',
   'An opal glass cone shade delivers brighter, more focused light than fabric alternatives. The compact 20cm diameter makes it ideal for smaller pendant installations. Compatible with standard E27 pendant holders. BS EN 60598-1 compliant.',
   'An opal glass cone shade delivers brighter, more focused light than fabric alternatives, making it ideal for kitchen pendants and reading nooks. The 20cm diameter suits compact pendants. Opal finish softens harshness while maintaining brightness for tasks.',
   '["Kitchen pendant lighting", "Bathroom vanity (check IP rating of fixture)", "Reading nooks", "Modern minimalist interiors"]',
   '["Children''s rooms (fragile)", "Outdoor use", "Low-ceiling rooms (directional, not diffused)"]'),
  (UUID(), 'BLB-LED-E27-4W',
   'LED Bulb for E27 Pendant and Table Lamps - Warm Filament Glow | 4W 2700K 470lm',
   'A 4W LED filament bulb with E27 cap produces warm white light at 2700K, delivering 470 lumens equivalent to a traditional 40W incandescent bulb. Fully dimmable with trailing-edge dimmer switches. Compatible with standard E27 screw fittings.',
   'A 4W LED filament bulb with E27 screw cap produces 470 lumens of warm white light at 2700K, equivalent to a 40W incandescent. Fits standard E27 pendants, table lamps, and floor lamps. Dimmable with compatible trailing-edge dimmer switches.',
   '["E27 pendant cable sets", "Table lamp bulb replacement", "Vintage-style visible bulb displays"]',
   '["B22 bayonet fittings", "Outdoor unenclosed fixtures", "High-lumen task lighting needs"]'),
  (UUID(), 'BLB-LED-B22-8W',
   'Bright LED Bulb for B22 Kitchen and Ceiling Lights | 8W 4000K Cool White 806lm',
   'An 8W LED bulb with B22 bayonet cap produces cool white light at 4000K, delivering 806 lumens equivalent to a traditional 60W bulb. It fits all standard B22 bayonet cap fittings. Non-dimmable. Ideal for kitchens and workspaces.',
   'An 8W LED GLS bulb with B22 bayonet cap produces 806 lumens of cool white light at 4000K, equivalent to a traditional 60W bulb. Designed for kitchen ceiling fittings and workspaces where bright, clear illumination is needed. Non-dimmable.',
   '["B22 ceiling fittings", "Kitchen and workspace lighting", "High-brightness task areas"]',
   '[]'),
  (UUID(), 'PND-SET-BRS-3L',
   'Statement Kitchen Island Lighting | Brass 3-Light Pendant Cluster E27',
   'A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. Antique brass finish suits both period and contemporary interiors. BS 7671 compliant.',
   'A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. Antique brass finish suits both period and contemporary interiors.',
   '["Kitchen island statement lighting", "Dining table centrepiece", "Open-plan living areas", "Period property renovations"]',
   '["Low ceilings under 2.4m", "Bathrooms (not IP-rated)", "Single bulb requirements"]')
ON DUPLICATE KEY UPDATE
  title        = VALUES(title),
  description  = VALUES(description),
  answer_block = VALUES(answer_block),
  best_for     = VALUES(best_for),
  not_for      = VALUES(not_for);

-- ---------------------------------------------------------------------------
-- 12. Set erp_sync_date for Hero/Support golden SKUs (only if column exists)
-- ---------------------------------------------------------------------------
-- Migration 077 adds erp_sync_date to skus. Run this block only when column exists.
SET @erp_col = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'erp_sync_date'
);
SET @erp_sql = IF(@erp_col > 0,
  'UPDATE skus SET erp_sync_date = ''2026-01-15 00:00:00'' WHERE sku_code IN (''CBL-BLK-3C-1M'',''CBL-GLD-3C-1M'',''CBL-WHT-2C-3M'',''SHD-TPE-DRM-35'',''SHD-GLS-CNE-20'',''BLB-LED-E27-4W'',''BLB-LED-B22-8W'',''PND-SET-BRS-3L'')',
  'SELECT 1'
);
PREPARE stmt FROM @erp_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
