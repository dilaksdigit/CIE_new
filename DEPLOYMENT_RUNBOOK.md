# CIE v2.3.2 Deployment Runbook

## Pre-Deploy Checklist

```bash
bash scripts/pre_deploy_check.sh /path/to/production/.env
# Windows:
# powershell -File scripts/pre_deploy_check.ps1 -EnvFile C:\path\to\.env
```

**Code enforcement (P0):** Even if `.env` sets `APP_DEBUG=true`, `config/app.php` forces `debug=false` when `APP_ENV=production|staging`. API errors return generic JSON (no stack traces). CI runs `scripts/scan_tracked_secrets.py`.

- [ ] `APP_DEBUG=false`, `APP_ENV=production` on server `.env` (never copy `docker-compose.yml` defaults)
- [ ] `DB_PASSWORD` is not compose default `cie_password` (P0 — rotate DB creds on staging/prod)
- [ ] Shopify token rotated; old `shpat_*` revoked (P0.1)
- [ ] `ALLOW_DEMO_TOKEN=false` (or unset) on staging/prod
- [ ] All API keys configured (FINAL_Developer_Instruction §6.2 pre-flight)
- [ ] `GOOGLE_SERVICE_ACCOUNT_JSON` points to file **outside** repository
- [ ] `INTERNAL_SERVICE_TOKEN` = 64-char hex (`openssl rand -hex 32`), same in PHP and Python env
- [ ] Shopify token rotated; old token revoked
- [ ] PostgreSQL accessible (`DB_CONNECTION=pgsql`, port 5432); schema applied (see `database/postgres/README.md`)
- [ ] Redis accessible
- [ ] N8N at `N8N_BASE_URL`; workflows imported via `scripts/import_n8n_workflows.sh`

## Deployment Steps (in order)

### 1. Database

```bash
cd backend/php
php artisan migrate
php artisan db:seed
```

On greenfield Docker: ensure `database/migrations` has no duplicate numeric prefixes before first boot.

### 2. PHP Laravel

```bash
cd backend/php
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan optimize
```

### 3. Python FastAPI

```bash
cd backend/python
pip install -r requirements.txt
uvicorn api.main:app --host 0.0.0.0 --port 8000 --workers 4
```

### 4. React Frontend

```bash
cd frontend
npm install
npm run build
# Serve dist/ via nginx or Laravel public
```

### 5. N8N Workflows

```bash
export N8N_BASE_URL=https://n8n.example.com
export N8N_API_KEY=your_key
bash scripts/import_n8n_workflows.sh
# Activate shopify_deploy + gmc_deploy (required for PHP publish webhooks)
# Optional: W1–W8 orchestration workflows
# Add HMAC verify Code node per docs/N8N_HMAC_VERIFY.md
# Env paths must match: N8N_SHOPIFY_WEBHOOK_PATH=shopify-deploy, N8N_GMC_WEBHOOK_PATH=gmc-deploy
```

### 6. `skus` → `sku_master` bridge (GAP-ERP-03)

Run once after migrate and before measurement/AI jobs (no tier or gate logic changes):

```bash
cd backend/python
python -m src.jobs.skus_to_sku_master_bridge
```

Schedule daily until architect approves schema consolidation:

```cron
15 2 * * * cd /path/to/cie/backend/python && python -m src.jobs.skus_to_sku_master_bridge >> logs/bridge.log 2>&1
```

### 7. Cron Jobs (UTC)

```cron
0 3 * * 0  cd /path/to/cie/backend/python && python -m src.jobs.weekly_gsc_sync >> logs/gsc.log 2>&1
0 4 * * 0  cd /path/to/cie/backend/python && python -m src.jobs.weekly_ga4_sync >> logs/ga4.log 2>&1
0 6 * * 1  cd /path/to/cie/backend/python && python run_weekly_ai_audit.py >> logs/audit.log 2>&1
0 7 * * *  cd /path/to/cie/backend/python && python weekly_decay_check.py >> logs/decay.log 2>&1
*/30 * * * * cd /path/to/cie/backend/python && python run_vector_retry_queue.py >> logs/vector.log 2>&1
```

Adjust paths to match your install layout.

## Pre-Launch Verification

| # | Check |
|---|--------|
| 1 | All DB tables + FKs |
| 2 | OpenAPI endpoints respond |
| 3 | 72/72 acceptance tests (`pytest`, `php artisan test`) |
| 4 | RBAC: writer 403 on `/api/admin/users` |
| 5 | Kill tier edit blocked |
| 6 | `audit_log` immutable |
| 7 | Validate p95 < 500ms |
| 8 | Tier banners (4 screenshots) |
| 9 | Google Rich Results — Hero SKU |
| 10 | N8N shopify_deploy + gmc_deploy execution log; payload keys via `scripts/verify_n8n_deploy_payload.py` |
| 11 | Decay auto-brief in writer queue |
| 12 | ERP sync → `tier_history` |
| 13 | Readiness dashboard scores |

## Rollback

```bash
php artisan migrate:rollback --step=N
git checkout <previous_tag>
# Restart php, python, n8n, frontend
```

## Production env assert (deploy script snippet)

```bash
grep -q '^APP_DEBUG=false' .env || { echo "APP_DEBUG must be false"; exit 1; }
```

## Staging E2E and client sign-off

Full checklist: `docs/STAGING_E2E_AND_SIGNOFF.md`  
Architect gaps: `docs/ARCHITECT_REVIEW_GAP_ROUTES_API.md`  
AI quorum (SGE stubbed): `docs/AI_AUDIT_QUORUM_EFFECTIVE.md`

```bash
bash scripts/verify_staging_readiness.sh
# Live: APP_URL=... TEST_WRITER_TOKEN=... python scripts/e2e_publish_flow_check.py
```
