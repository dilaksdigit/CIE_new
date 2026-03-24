-- SOURCE: CLAUDE.md §6 locked taxonomy; OpenAPI enum alignment.
-- ADDITIVE/SAFE: normalize known legacy keys to canonical keys.

SET NAMES utf8mb4;

-- Rename legacy keys only when canonical target does not already exist.
UPDATE intent_taxonomy it
SET it.intent_key = 'safety_compliance',
    it.label = 'Safety/Compliance'
WHERE it.intent_key IN ('regulatory', 'safety')
  AND NOT EXISTS (
    SELECT 1 FROM (
      SELECT intent_key FROM intent_taxonomy WHERE intent_key = 'safety_compliance'
    ) x
  );

UPDATE intent_taxonomy it
SET it.intent_key = 'bulk_trade',
    it.label = 'Bulk/Trade'
WHERE it.intent_key IN ('troubleshooting', 'bulk')
  AND NOT EXISTS (
    SELECT 1 FROM (
      SELECT intent_key FROM intent_taxonomy WHERE intent_key = 'bulk_trade'
    ) x
  );
