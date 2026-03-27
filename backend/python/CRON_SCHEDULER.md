# Sync jobs (cron)

## Manual (before first GSC/GA4 weekly sync)

- **Shopify → `sku_master` URLs** (§6.1 `shopify_url` / `shopify_product_id`): apply `database/migrations/107_add_missing_columns_to_sku_master.sql` to `cie_v232` (e.g. `mysql ... < ...` or `python scripts/run_migration_107.py` from `backend/python`), then run  
  `python -m src.jobs.shopify_product_sync`  
  Env: `SHOPIFY_STORE_DOMAIN`, `SHOPIFY_ADMIN_ACCESS_TOKEN`, optional `GSC_PROPERTY` (public `shopify_url` uses same scheme+host as GSC when set). Backfills NULL `sku_content` meta/product/alt fields only.

---

Schedule these from the **backend/python** directory. `src.jobs.*` modules import `_bootstrap` first, which adjusts `sys.path` and loads every `.env` found walking up from `src/jobs/`; the **outermost** (repo root) overrides inner files (e.g. `backend/python/.env` for Docker), so you do not need `PYTHONPATH` for weekly GSC/GA4 or CIS jobs.

## Monthly

- **ERP monthly** (spec §8.1): 1st of every month, 02:00 UTC  
  `python -m src.jobs.nightly_erp_sync`  
  Env: `ERP_SYNC_ENABLED`, `ERP_CONNECTOR`, `ERP_CSV_DIRECTORY`, `CIE_CMS_URL`, `CIE_INTERNAL_API_KEY`.  
  Business rule: `sync.erp_cron_schedule` = `0 2 1 * *`.  
  Reads commercial data (margin, CPPC, velocity, return rate) from the configured connector (CSV/REST/ODBC) and POSTs to the PHP `/api/v1/erp/sync` endpoint for tier recalculation.  
  Admin can also trigger manually from the admin panel.

## Weekly

- **GSC weekly** (spec §9.1): Sunday 03:00 UTC  
  `python -m src.jobs.weekly_gsc_sync`  
  Env: `GSC_PROPERTY`, DB vars, `GOOGLE_SERVICE_ACCOUNT_JSON`.

- **GA4 weekly** (spec §10.2): Monday 03:00 UTC  
  `python -m src.jobs.weekly_ga4_sync`  
  Env: `GA4_PROPERTY_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON`, DB vars.  
  Date window: 7 days ending previous calendar Sunday (`today_utc - 1 day` as window end).  
  SKU matching: `sku_master.shopify_url` (+ `normalise_url` §9.3); optional operational table `sync_status` via migration `137_create_sync_status_table.sql`.

## Example crontab (adjust path and env):

```cron
# ERP — 1st of month 02:00 UTC (sync.erp_cron_schedule)
0 2 1 * *  cd /path/to/backend/python && python -m src.jobs.nightly_erp_sync

# GSC — Sunday 03:00 UTC
0 3 * * 0  cd /path/to/backend/python && python -m src.jobs.weekly_gsc_sync

# GA4 — Monday 03:00 UTC
0 3 * * 1  cd /path/to/backend/python && python -m src.jobs.weekly_ga4_sync
```
