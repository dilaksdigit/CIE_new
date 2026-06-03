-- CIE v2.3.2: Database-level protection — prevent updates to Kill-tier SKUs
-- MySQL: trigger blocks UPDATE when tier remains 'KILL' (application must check first; this is defence in depth)

-- TODO(pg): review trigger drop

-- TODO(pg): manual trigger conversion required
