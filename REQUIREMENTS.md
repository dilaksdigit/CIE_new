# REQUIREMENTS.md — CIE v2.3.2

Single checklist derived from `CLAUDE.md` §1–15. Status: **DONE** | **PARTIAL** | **MISSING**

## Core product

| ID | Requirement | Status |
|----|-------------|--------|
| R-CORE-01 | Closed-loop SEO CMS for lighting e-commerce | PARTIAL |
| R-CORE-02 | Two primary users: Writer, KPI Reviewer + Admin | PARTIAL |
| R-CORE-03 | No human approval queue; gates = approval | DONE |
| R-CORE-04 | Auto-publish Shopify + GMC on gate pass | PARTIAL |

## Gates G1–G7 + VEC

| Gate | Rule summary | Status |
|------|--------------|--------|
| G1 | Valid cluster_id | DONE |
| G2 | Exactly 1 primary intent (9 taxonomy) | DONE |
| G3 | 1–3 secondary intents | DONE |
| G4 | Answer block 250–300 chars | DONE |
| G5 | best_for / not_for (Hero/Support) | DONE |
| G6 | Tier tag hero/support/harvest/kill | DONE |
| G6.1 | Tier field lockout Kill/Harvest | DONE |
| G7 | Expert authority Hero/Support | DONE |
| VEC | Vector ≥0.72 fail-soft warning | DONE |

## Channels (DECISION-001)

| Channel | Status |
|---------|--------|
| Shopify PRIMARY | PARTIAL |
| GMC SECONDARY | PARTIAL |
| Amazon | DEFERRED |

## UI (15 routes)

| Route | Status |
|-------|--------|
| `/writer/queue`, `/writer/edit/:skuId` | DONE |
| `/review/*` (dashboard, maturity, ai-audit, channels, kpis, semrush) | DONE |
| `/admin/clusters`, config, tiers, audit-trail, bulk-ops, semrush-import, shopify-pull, erp-sync | DONE |
| `/admin/users` | DONE |
| `/login` (no public `/register`) | DONE |
| Desktop 1280px+, light theme | DONE |

## Semrush v2.3.2

| Requirement | Status |
|-------------|--------|
| CSV upload `/admin/semrush-import` | DONE |
| Review `/review/semrush` | DONE |
| Snapshots on publish | PARTIAL |

## Measurement loop

| Source | Status |
|--------|--------|
| GSC | PARTIAL |
| GA4 | PARTIAL |
| AI audit (4 engines) | PARTIAL |
| Semrush CSV | DONE |

## Tests & delivery

| Requirement | Status |
|-------------|--------|
| 72 golden acceptance tests (DOC4B) | PARTIAL (69 Python + 4+ PHPUnit; see ACCEPTANCE_COVERAGE.md) |
| `acceptance_tests.yaml` | PARTIAL (starter) |
| Client sign-off on this document | MISSING — use `docs/STAGING_E2E_AND_SIGNOFF.md` §6 after live E2E |

## Security

| Requirement | Status |
|-------------|--------|
| Secrets not in VCS | PARTIAL (gitignore done; rotate tokens manually) |
| `APP_DEBUG=false` production | MISSING (manual) |
| RBAC on all endpoints | PARTIAL |
| `audit_log` immutable | DONE |

---

For open spec ambiguities see `GAP_LOG.md`. For architectural detail see `ARCHITECTURE.md`.
