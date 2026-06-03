-- SOURCE: Production Roadmap P2.6 — locked 9-intent taxonomy verification
-- Run (Postgres): psql -U ... -d cie_v232 -f scripts/verify_intent_taxonomy.sql
-- Evidence: screenshot of result (expect 9 rows, intent_count = 9)

SELECT intent_id, intent_key, label, tier_access
FROM intent_taxonomy
WHERE is_active = TRUE OR is_active IS NULL
ORDER BY intent_id;

SELECT COUNT(*) AS intent_count FROM intent_taxonomy
WHERE intent_key IN (
  'problem_solving',
  'comparison',
  'compatibility',
  'specification',
  'installation',
  'troubleshooting',
  'inspiration',
  'regulatory',
  'replacement'
);
