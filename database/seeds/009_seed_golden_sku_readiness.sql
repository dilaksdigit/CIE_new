-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.5, 7 (G7)
-- SOURCE: CIE_v232_FINAL_Developer_Instruction.docx Phase 3 Task 3.2
-- SOURCE: CLAUDE.md Section 6 — G7: "Channel Readiness — Score >= threshold for target channel"
--
-- Seeds channel_readiness rows for the 10 golden SKUs.
-- The canonical schema (024_create_canonical_cie_schema.sql) uses channel_readiness with
-- channel ENUM('shopify','gmc') and score SMALLINT, keyed by sku_id (sku_code).
--
-- Active channels: Shopify (own_website), GMC (google_sge). Amazon deferred (CLAUDE.md Section 4).
--
-- Hero/Support: readiness scores above threshold (default 85 per BusinessRules).
-- Harvest: G7 SUSPENDED — placeholder rows with 0 values (safe, no impact).
-- Kill: No rows — G7 is suspended for Kill.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Hero SKUs — both shopify and gmc channels, scores above threshold
-- ---------------------------------------------------------------------------

-- Scores from golden_test_data.json expected_outputs.channel_decisions (own_website->shopify, google_sge->gmc)

-- CBL-BLK-3C-1M (Hero)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'CBL-BLK-3C-1M', 'shopify', 95, '{"title":95,"description":90,"images":91,"schema":92}', '2026-01-15 12:00:00'),
  (UUID(), 'CBL-BLK-3C-1M', 'gmc',     92, '{"title":92,"description":88,"images":90,"gtin":90}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- CBL-GLD-3C-1M (Hero)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'CBL-GLD-3C-1M', 'shopify', 94, '{"title":96,"description":92,"images":94,"schema":94}', '2026-01-15 12:00:00'),
  (UUID(), 'CBL-GLD-3C-1M', 'gmc',     89, '{"title":93,"description":89,"images":91,"gtin":91}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- SHD-TPE-DRM-35 (Hero)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'SHD-TPE-DRM-35', 'shopify', 96, '{"title":98,"description":94,"images":96,"schema":96}', '2026-01-15 12:00:00'),
  (UUID(), 'SHD-TPE-DRM-35', 'gmc',     94, '{"title":95,"description":91,"images":93,"gtin":93}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- SHD-GLS-CNE-20 (Hero)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'SHD-GLS-CNE-20', 'shopify', 91, '{"title":90,"description":86,"images":88,"schema":88}', '2026-01-15 12:00:00'),
  (UUID(), 'SHD-GLS-CNE-20', 'gmc',     88, '{"title":88,"description":84,"images":86,"gtin":86}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- PND-SET-BRS-3L (Hero)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'PND-SET-BRS-3L', 'shopify', 93, '{"title":96,"description":92,"images":94,"schema":94}', '2026-01-15 12:00:00'),
  (UUID(), 'PND-SET-BRS-3L', 'gmc',     91, '{"title":93,"description":89,"images":91,"gtin":91}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- ---------------------------------------------------------------------------
-- 2. Support SKUs — target channel(s) from expected_outputs
-- ---------------------------------------------------------------------------

-- CBL-WHT-2C-3M (Support)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'CBL-WHT-2C-3M', 'shopify', 88, '{"title":90,"description":86,"images":88,"schema":88}', '2026-01-15 12:00:00'),
  (UUID(), 'CBL-WHT-2C-3M', 'gmc',     76, '{"title":88,"description":84,"images":86,"gtin":86}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- BLB-LED-E27-4W (Support)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'BLB-LED-E27-4W', 'shopify', 85, '{"title":88,"description":84,"images":86,"schema":86}', '2026-01-15 12:00:00'),
  (UUID(), 'BLB-LED-E27-4W', 'gmc',     72, '{"title":86,"description":82,"images":84,"gtin":84}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- BLB-LED-B22-8W (Support)
INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'BLB-LED-B22-8W', 'shopify', 82, '{"title":88,"description":84,"images":86,"schema":86}', '2026-01-15 12:00:00'),
  (UUID(), 'BLB-LED-B22-8W', 'gmc',     70, '{"title":86,"description":82,"images":84,"gtin":84}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- ---------------------------------------------------------------------------
-- 3. Harvest SKU — placeholder rows from expected_outputs (G7 suspended)
-- ---------------------------------------------------------------------------

INSERT INTO channel_readiness (id, sku_id, channel, score, component_scores, computed_at)
VALUES
  (UUID(), 'CBL-RED-3C-2M', 'shopify', 65, '{"title":65,"description":65,"images":65,"schema":65}', '2026-01-15 12:00:00'),
  (UUID(), 'CBL-RED-3C-2M', 'gmc',     52, '{"title":52,"description":52,"images":52,"gtin":52}', '2026-01-15 12:00:00')
ON DUPLICATE KEY UPDATE
  score            = VALUES(score),
  component_scores = VALUES(component_scores),
  computed_at      = VALUES(computed_at);

-- ---------------------------------------------------------------------------
-- 4. Kill SKU — No rows. G7 is suspended for Kill tier.
--    FLR-ARC-BLK-175: intentionally omitted.
-- ---------------------------------------------------------------------------
