-- SOURCE: CLAUDE.md §14 — GA4 data pull to include bounce_rate
-- Add bounce_rate to ga4_landing_performance (GA4 returns as fraction 0–1).

ALTER TABLE ga4_landing_performance
  ADD COLUMN bounce_rate DECIMAL(5,4) NULL AFTER conversion_rate;
