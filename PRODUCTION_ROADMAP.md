# CIE v2.3.2 — Production Completion Roadmap (Tracker)

**Target:** 100% — 72 acceptance tests, zero credential exposures, staging + client sign-off  
**Methodology:** Drift-Safe v3 · Master Loop on every task

Update status: ⬜ Not Started → 🔄 In Progress → ✅ Done → ❌ Blocked

| Phase | Name | Status | Exit Gate |
|-------|------|--------|-----------|
| P0 | Stop-Ship Security | 🔄 In Progress | Code enforcement done; server secrets rotation manual |
| P1 | Governance Documents | 🔄 In Progress | 5 governance files exist |
| P2 | Critical Code Fixes | ✅ Done | W5, admin users, migrations |
| P3 | Integration E2E Testing | 🔄 In Progress | Static + mocked-wire E2E green; live staging needs APP_URL + tokens |
| P4 | Test Suite Completion | 🔄 In Progress | 72/72 acceptance tests pass |
| P5 | Remaining Features | 🔄 In Progress | Hardening features confirmed |
| P6 | Performance & Hardening | 🔄 In Progress | p95 < 500ms, tier screenshots |
| P7 | Production Deployment | ⬜ Not Started | Staging + client sign-off |

---

## P0 — Stop-Ship Security

| Task | Status | Notes |
|------|--------|-------|
| P0.1 Rotate Shopify token | ⬜ | **Manual** — Shopify Admin; revoke old token |
| P0.2 `.gitignore` + `.env.example` | ✅ | `_env`, `service_account.json`, `debug-*.log`, `docker-compose.override.yml` ignored |
| P0.3 Move service account out of repo | 🔄 | **Manual** path in prod `.env`; `pre_deploy_check` blocks repo file |
| P0.4 `APP_DEBUG=false` production | ✅ | Forced in `config/app.php` + `Handler.php`; `pre_deploy_check.sh` / `.ps1` |
| P0.5 Migration duplicate fix | ✅ | Renumbered 145–154; see DECISION-012 |
| P0.6 `INTERNAL_SERVICE_TOKEN` | 🔄 | **Manual** set on server; deploy script requires non-empty |
| P0.7 Remove debug logs from VCS | ✅ | CI/pre-deploy fails if `debug-*.log` tracked; `scan_tracked_secrets.py` in CI |

---

## P1 — Governance

| Task | Status |
|------|--------|
| P1.1 `GAP_LOG.md` | ✅ (exists; maintain) |
| P1.2 `DECISIONS.md` | ✅ |
| P1.3 `ARCHITECTURE.md` | ✅ |
| P1.4 `REQUIREMENTS.md` | ✅ |
| P1.5 SOURCE spot-check 10% | ⬜ |

---

## P2 — Critical Fixes

| Task | Status |
|------|--------|
| P2.1 `W5_gate_validation.json` | ✅ |
| P2.2 `/admin/users` | ✅ |
| P2.3 Gate key naming | ✅ | DECISION-011; Python long keys authoritative |
| P2.4 HMAC on W6/W5 | 🔄 | PHP `X-N8N-Signature`; verify N8N in staging |
| P2.5 Deprecated SQL moved | ✅ |
| P2.6 Intent taxonomy verify | ⬜ | Run `scripts/verify_intent_taxonomy.sql` on target DB |

---

## P3 — Integration E2E

| Task | Status |
|------|--------|
| P3.1 7-step publish flow | 🔄 | Static: `e2e_n8n_shopify_deploy_static.py` + `ChannelDeployE2eTest`; live: `e2e_publish_flow_check.py` |
| P3 N8N payload verify | ✅ | `verify_n8n_deploy_payload.py` + fixture `tests/fixtures/n8n/hero_deploy_payload_sample.json` |
| P3.2–P3.8 | ⬜ | ERP, Shopify, GMC, GSC/GA4, CIS, AI audit, decay |

---

## P4 — Test Suite

| Task | Status |
|------|--------|
| P4.1 Coverage matrix | ✅ | `tests/ACCEPTANCE_COVERAGE.md` |
| P4.2–P4.4 Golden + edge | ✅ | **75** Python tests passing |
| P4.5 PHPUnit expansion | 🔄 | `ChannelGovernorTest`, `RbacEndpointMatrixTest` |
| P4.6 acceptance_tests.yaml | ✅ | Catalog + AT-001..AT-EDGE-10 mapping |

**Run:** `bash scripts/run_all_tests.sh` or `cd backend/python && python -m pytest tests/ -q`

---

## P5 — Remaining Features

| P5.5 Audit RBAC scope | ✅ | `AuditLogController::applyAuditScope` |
| P5.1 Cluster assistant | ✅ | `WriterEdit.jsx` + `GET /v1/sku/{id}/cluster-suggest` |
| P5.2 Field indicators | ✅ | Title / description / answer-block counters (green/amber/red) |
| P5.3 AI readiness UI | ✅ | `Channels.jsx` + `buildChannelStats` `ai_readiness` |
| P5.7 Decay brief badge | ✅ | `WriterQueue.jsx` |
| P5.4, P5.6 | ⬜ | Minor KPI polish if spec gaps remain |

---

## P6 — Performance & Hardening

| Task | Status |
|------|--------|
| P6.2 RBAC matrix | ✅ | `RbacEndpointMatrixTest.php` (live tokens) |
| P6.1 Load test p95 | ⬜ | k6/ab on validate endpoint |
| P6.3 Tier screenshots | ⬜ | `docs/screenshots/tier-banners/` |
| P6.4 Rich Results | ⬜ | Google tool on staging Hero URL |
| P6.5 Edge sweep | 🔄 | Python edge tests in `test_golden_matrix.py` |

---

## P7 — Deployment

| Task | Status |
|------|--------|
| P7.1 Runbook | ✅ | `DEPLOYMENT_RUNBOOK.md` |
| P7 pre-deploy | ✅ | `scripts/pre_deploy_check.sh` |
| P2.6 Intent SQL | ✅ | `scripts/verify_intent_taxonomy.sql` |
| P2.4 N8N HMAC doc | ✅ | `docs/N8N_HMAC_VERIFY.md` |
| CI pipeline | ✅ | `.github/workflows/ci.yml` — pytest, PHPUnit batch tests, frontend build |
| Staging + sign-off | ⬜ | `docs/STAGING_E2E_AND_SIGNOFF.md` §6 after live E2E green |
| Architect GAP review | 🔄 | `docs/ARCHITECT_REVIEW_GAP_ROUTES_API.md` pending sign-off |
| sku_master bridge | ✅ doc | `DEPLOYMENT_RUNBOOK.md` §6 — mandatory until GAP-ERP-03 closed |
| AI quorum (SGE) | ✅ doc | `docs/AI_AUDIT_QUORUM_EFFECTIVE.md` |

---

## P0 reminder

Run `bash scripts/pre_deploy_check.sh /path/to/production.env` before every deploy.

---

*Last updated: 2026-06-03*
