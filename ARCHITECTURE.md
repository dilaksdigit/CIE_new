# ARCHITECTURE.md — CIE v2.3.2 System Architecture

## Service Map

| Service | Technology | Port | Command to Start |
|---------|-----------|------|------------------|
| PHP Laravel (CMS/API) | PHP 8.2 + Laravel | 8080 | `cd backend/php && php artisan serve --port=8080` |
| Python FastAPI (Validation/AI) | Python 3.11 + FastAPI | 8000 | `cd backend/python && uvicorn api.main:app --host 0.0.0.0 --port 8000` |
| React Frontend | Vite + React 18 | 5173 | `cd frontend && npm run dev` |
| PostgreSQL Database | PostgreSQL 16 | 5432 | Docker (`postgres:16-alpine`) or local PG |
| Redis (vector cache) | Redis 7 | 6379 | `redis-server` or Docker |
| N8N (workflow automation) | N8N | 5678 | `n8n start` |

## Inter-Service Communication

```
Browser → React (5173)
         ↓ API calls (/api)
         PHP Laravel (8080)
              ↓ PythonWorkerClient HTTP POST
              Python FastAPI (8000)  ← vector, gates, audit, CIS
              ↓ N8N Webhook (W1, W5, W6, W7, W8)
              N8N (5678)
                ↓ HTTP
                Shopify Admin API (PRIMARY)
                Google Merchant Center (SECONDARY)
                GSC / GA4 (Python cron jobs)
```

## Repository Layout

| Path | Purpose |
|------|---------|
| `backend/php/` | Laravel API, gate orchestration, RBAC |
| `backend/python/` | FastAPI gate engine, embeddings, CIS/audit jobs |
| `frontend/` | React UI (15 routes, desktop 1280px+) |
| `database/migrations/` | Legacy MySQL-dialect SQL (source for conversion) |
| `database/postgres/migrations/` | PostgreSQL migrations applied via Docker init |
| `database/migrations/deprecated/` | Non-executable historical patches |
| `n8n/workflows/` | W1–W8 workflow JSON exports |
| `database/seeds/` | Business rules, golden test data |

## Environment Setup (Fresh Install)

1. Clone repository
2. Copy `.env.example` to `.env` (and `backend/php/.env` if split); fill values per `CLAUDE.md` §19
3. Place Google service account JSON **outside** the repo; set `GOOGLE_SERVICE_ACCOUNT_JSON` to absolute path
4. `composer install` in `backend/php`
5. `pip install -r requirements.txt` in `backend/python`
6. `npm install` in `frontend`
7. Greenfield: `docker-compose up` applies `database/postgres/init/` (PostgreSQL migrations + spec indexes — DECISION-014). Legacy `database/migrations/` is MySQL reference only.
8. Run seeds (`database/seeds/`)
9. `bash scripts/import_n8n_workflows.sh` (with `N8N_API_KEY` set)
10. Pre-flight: FINAL_Developer_Instruction §6.2

## External API Dependencies

| Service | Required For | Auth |
|---------|-------------|------|
| OpenAI | Vector similarity, audit | API key |
| Anthropic / Gemini / Perplexity | AI citation audit | API keys |
| Google Search Console / GA4 / GMC | Measurement + feed | Service account JSON |
| Shopify | Primary channel deploy | Admin access token |
| ERP | Tier recalculation | API key or CSV |

## Database

- Engine: **PostgreSQL 16** (system of record per `DECISION-013`)
- 160+ numbered migrations; MySQL files in `database/migrations/` mirror `database/postgres/migrations/`
- Greenfield: `database/postgres/init/` bootstrap + immutable triggers on `audit_log`
- `audit_log`: immutable (triggers block UPDATE/DELETE)
- Intent taxonomy: locked 9 intents (see `CLAUDE.md` §6 and migration `082_intent_taxonomy_enf_spec_83.sql`)

## Security

- Never commit `_env`, `.env`, or `service_account.json` (see `.gitignore`)
- Production: `APP_ENV=production`, `APP_DEBUG=false`
- `INTERNAL_SERVICE_TOKEN`: shared PHP ↔ Python (`x-service-token`)
- N8N webhooks: HMAC via `N8N_WEBHOOK_SECRET` (`ChannelDeployService`)

## Backup

Configure daily PostgreSQL dumps to secure off-site storage (see `DEPLOYMENT_RUNBOOK.md`).
