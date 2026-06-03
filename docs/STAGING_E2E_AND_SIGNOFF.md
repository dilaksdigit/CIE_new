# Staging E2E, P0 Security, and Client Sign-Off

**Date:** 2026-06-03  
**Purpose:** Execute recommended production-readiness actions without changing application business rules.

---

## 1. Staging E2E (N8N + Shopify + GMC)

**CI / local (no Shopify token):**

```bash
python scripts/e2e_n8n_shopify_deploy_static.py
cd backend/php && vendor/bin/phpunit --bootstrap ../../tests/php/bootstrap-app.php ../../tests/php/Feature/ChannelDeployE2eTest.php
```

Proves workflow paths, deploy payload schema, and HMAC POST to `shopify-deploy` (Http fake).

### Automated (local CI parity)

```powershell
# Windows — run equivalent steps from verify_staging_readiness.sh
cd backend\php
$env:APP_KEY="base64:dGVzdGtleQ=="
vendor\bin\phpunit --bootstrap ..\..\tests\php\bootstrap-app.php `
  ..\..\tests\php\Feature\PublishAuthorizationTest.php `
  ..\..\tests\php\Feature\DemoTokenGateTest.php `
  ..\..\tests\php\Feature\SemrushMigrationTest.php `
  ..\..\tests\php\Feature\PythonWorkerConfigTest.php `
  ..\..\tests\php\Feature\BusinessRulesRouteTest.php

cd ..\..\frontend
npm run build

cd ..\python
python -m pytest tests/ -q
```

**Last run (dev workstation):** PHPUnit 18/18 OK; Python **75 passed**, 1 skipped.

### Live staging (requires secrets)

```bash
export APP_URL=https://staging.cie.example.com
export TEST_WRITER_TOKEN=<writer JWT>
export TEST_HERO_SKU_CODE=CBL-BLK-3C-1M   # or staging Hero UUID/code
python scripts/e2e_publish_flow_check.py
```

| Step | Script check | Manual proof (operator) |
|------|--------------|-------------------------|
| 0–2 | Publish not 403; validate passes | Writer can open SKU editor |
| 3–4 | GSC/GA4 baseline HTTP | Rows in `gsc_baselines` / GA4 tables |
| 5 | Publish 200/201/202 | N8N **shopify_deploy** + **gmc_deploy** executions green |
| 6 | Readiness 200 | Shopify Admin metafields + GMC feed row updated |

### N8N + channel proof (one Hero SKU)

1. Publish Hero SKU after all gates pass.
2. In N8N execution log, open inbound webhook body from PHP.
3. Run payload checklist:

```bash
python scripts/verify_n8n_deploy_payload.py --sku-code CBL-BLK-3C-1M
```

Required outbound keys (from `ChannelDeployService::buildDeployPayload`):  
`sku_id`, `shopify_product_id`, `title`, `meta_title`, `meta_description`, `answer_block`, `best_for`, `not_for`, `faq`, `json_ld`, `alt_text`.

4. In Shopify Admin → Product → Metafields: confirm **title**, **answer block**, and CIE metafields match payload.
5. GMC: confirm feed/API update for same SKU (secondary channel).

---

## 2. P0 security — manual tasks (production/staging server)

**Do not commit rotated secrets.** Use server `.env` only.

| ID | Task | How to verify |
|----|------|----------------|
| P0.1 | Rotate Shopify Admin token | Revoke old `shpat_*` in Shopify; update `SHOPIFY_ADMIN_ACCESS_TOKEN` |
| P0.3 | Service account outside repo | `GOOGLE_SERVICE_ACCOUNT_JSON=/secure/path/...json` |
| P0.4 | `APP_DEBUG=false` | `grep '^APP_DEBUG=false' /path/to/.env` |
| P0.6 | `INTERNAL_SERVICE_TOKEN` | 64-char hex; same in PHP + Python |
| DB | Remove default password | Not `cie_password` / compose defaults on staging/prod |

Pre-deploy gate:

```bash
bash scripts/pre_deploy_check.sh /path/to/staging.env
```

`docker-compose.yml` retains `cie_password` and `APP_DEBUG=true` for **local dev only** — never copy compose env to production.

---

## 3. Architect review

See `docs/ARCHITECT_REVIEW_GAP_ROUTES_API.md` for GAP-ROUTES-01, GAP-API-10, GAP-API-13 recommendations and sign-off checkboxes.

---

## 4. `skus` / `sku_master` — deploy runbook (GAP-ERP-03)

Until architect approves schema consolidation:

**Mandatory after migrate and before GSC/GA4/AI jobs:**

```bash
cd backend/python
python -m src.jobs.skus_to_sku_master_bridge
```

Schedule (UTC), after ERP sync:

```cron
15 2 * * * cd /path/to/cie/backend/python && python -m src.jobs.skus_to_sku_master_bridge >> logs/bridge.log 2>&1
```

Bridge copies `shopify_url`, content fields, and aligns `sku_content` from legacy `skus` — no tier or gate logic changes.

---

## 5. AI audit quorum (SGE stubbed)

See `docs/AI_AUDIT_QUORUM_EFFECTIVE.md`.

---

## 6. Client sign-off (after E2E green)

| # | Evidence artifact | Owner |
|---|-------------------|-------|
| 1 | `e2e_publish_flow_check.py` output (all PASS) | Dev |
| 2 | N8N execution screenshots (Shopify + GMC) | Dev |
| 3 | Shopify metafield screenshot (Hero SKU) | Dev |
| 4 | `pre_deploy_check.sh` OK on staging `.env` | Ops |
| 5 | Architect sign-off on `ARCHITECT_REVIEW_GAP_ROUTES_API.md` | Architect |
| 6 | KPI reviewer acknowledges SGE-effective quorum doc | Client |

**Sign-off line (client):**  
*“Staging publish flow verified for Shopify + GMC on [date]; P0 checklist complete; open gaps accepted per GAP_LOG.”*

Record approval in `REQUIREMENTS.md` § Tests & delivery when complete.
