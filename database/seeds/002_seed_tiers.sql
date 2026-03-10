-- Seed tiers after SKUs (lowercase tier values per migration 054)
SET @sku_id = UUID();
INSERT INTO skus (id, sku_code, title, tier, readiness_score) 
VALUES (@sku_id, 'CBL-BLK-3C-3M', 'Black 3-Core Cable 3m', 'hero', 87);

INSERT INTO tier_history (id, sku_id, old_tier, new_tier, reason) 
VALUES (UUID(), @sku_id, 'support', 'hero', 'Initial high performance');
