-- Seed tiers after SKUs
SET @sku_id = UUID();
INSERT INTO skus (id, sku_code, title, tier, readiness_score) 
VALUES (@sku_id, 'CBL-BLK-3C-3M', 'Black 3-Core Cable 3m', 'HERO', 87);

INSERT INTO tier_history (id, sku_id, old_tier, new_tier, reason) 
VALUES (UUID(), @sku_id, 'SUPPORT', 'HERO', 'Initial high performance');
