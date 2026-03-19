# Sync jobs (cron)

Schedule these from the **backend/python** directory (or set `PYTHONPATH` accordingly). No other code or business logic is changed by this doc.

## Monthly

- **ERP monthly** (spec §8.1): 1st of every month, 02:00 UTC  
  `python -m src.jobs.nightly_erp_sync`  
  Env: `ERP_SYNC_ENABLED`, `ERP_CONNECTOR`, `ERP_CSV_DIRECTORY`, `CIE_CMS_URL`, `CIE_INTERNAL_API_KEY`.  
  Business rule: `sync.erp_cron_schedule` = `0 2 1 * *`.  
  Reads commercial data (margin, CPPC, velocity, return rate) from the configured connector (CSV/REST/ODBC) and POSTs to the PHP `/api/v1/erp/sync` endpoint for tier recalculation.  
  Admin can also trigger manually from the admin panel.

## Weekly

- **GSC weekly** (spec §9.1): Monday 02:00 UTC  
  `python -m src.jobs.weekly_gsc_sync`  
  Env: `GSC_PROPERTY`, DB vars, `GOOGLE_SERVICE_ACCOUNT_JSON`.

- **GA4 weekly** (spec §10.2): Monday 03:00 UTC  
  `python -m src.jobs.weekly_ga4_sync`  
  Env: `GA4_PROPERTY_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON`, DB vars.

## Example crontab (adjust path and env):

```cron
# ERP — 1st of month 02:00 UTC (sync.erp_cron_schedule)
0 2 1 * *  cd /path/to/backend/python && python -m src.jobs.nightly_erp_sync

# GSC — Monday 02:00 UTC
0 2 * * 1  cd /path/to/backend/python && python -m src.jobs.weekly_gsc_sync

# GA4 — Monday 03:00 UTC
0 3 * * 1  cd /path/to/backend/python && python -m src.jobs.weekly_ga4_sync
```
