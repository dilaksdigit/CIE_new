-- SOURCE: database/seeds/008_seed_golden_sku_content.sql §7 + 007 title for BLB-LED-B22-8W
-- Postgres one-shot: align BLB row with golden_test_data.json (Support, intentional G4/G5 fails)

UPDATE skus SET
  title = 'Bright LED Bulb for B22 Kitchen and Ceiling Lights | 8W 4000K Cool White 806lm',
  meta_title = 'LED GLS Bulb B22 8W Cool White 4000K 806 Lumens for Kitchen Ceiling Fittings',
  short_description = 'B22 bayonet LED bulb, 8W cool white 4000K. 806 lumens, equivalent to 60W. Ideal for kitchens and workspaces. CE and RoHS compliant.',
  ai_answer_block = 'An 8W LED GLS bulb with B22 bayonet cap produces 806 lumens of cool white light at 4000K, equivalent to a traditional 60W bulb. Designed for kitchen ceiling fittings and workspaces where bright, clear illumination is needed. Non-dimmable.',
  best_for = '["B22 ceiling fittings", "Kitchen and workspace lighting", "High-brightness task areas"]',
  not_for = '[]',
  long_description = 'An 8W LED bulb with B22 bayonet cap produces cool white light at 4000K, delivering 806 lumens equivalent to a traditional 60W incandescent bulb. It fits all standard B22 bayonet cap fittings commonly found in UK ceiling lights. Non-dimmable. Ideal for kitchens and workspaces.',
  expert_authority = 'CE and RoHS compliant. Energy rating A+. 15,000 hour rated lifespan. Non-dimmable.',
  validation_status = 'INVALID'
WHERE sku_code = 'BLB-LED-B22-8W';
