# Architect Review — GAP-ROUTES-01, GAP-API-10, GAP-API-13

**Date:** 2026-06-03  
**Status:** Implemented in repo (2026-06-03) — `cie_v231_openapi.yaml` + `scripts/verify_openapi_route_parity.py`. Formal architect sign-off still required for Change Protocol closure.  
**Authority:** CLAUDE.md R1 (OpenAPI locked); Change Protocol required before contract edits

---

## GAP-ROUTES-01 — Undocumented API routes

### Current runtime inventory (`backend/php/routes/api.php`)

| Method | Path | Controller | In `docs/api/openapi.yaml`? | Recommendation |
|--------|------|------------|------------------------------|----------------|
| POST | `/api/admin/semrush-import` | SemrushImportController | Yes (v2.3.2 addendum) | **Keep** — specced exception |
| GET | `/api/v1/admin/semrush-import/latest` | SemrushImportController | Yes (`cie_v231_openapi.yaml`) | Done |
| DELETE | `/api/v1/admin/semrush-import/{batch_date}` | SemrushImportController | Yes | Done |
| POST | `/api/v1/sku/{sku_id}/suggest` | SkuController | Yes | Done |
| POST | `/api/admin/sync-failed` | TierController | Yes | Done |
| POST | `/api/skus/{skuCode}/channel-deployed` | SkuController | Yes | Done |
| POST | `/api/skus/{skuCode}/channel-failed` | SkuController | Yes | Done |
| POST | `/api/admin/erp-sync` | TierController | Yes (documented alias) | Done — canonical remains `POST /api/v1/erp/sync` |

### Proxy-only (Python worker, not PHP route table)

| Consumer | Upstream | Notes |
|----------|----------|-------|
| GscController | `GET {PYTHON_API_URL}/api/v1/gsc/verify` | Admin health; optional live API |
| Ga4Controller | `GET {PYTHON_API_URL}/api/v1/ga4/health` | Admin health |

**Recommendation:** Add `GET /api/v1/gsc/verify` and `GET /api/v1/ga4/health` to OpenAPI as **admin-only operational** endpoints (or document as internal via PHP proxy paths if architect prefers single gateway).

### Not in runtime

- `POST /api/admin/sync-complete` — referenced in ERP integration text only; **do not implement** until W1 workflow is updated and OpenAPI amended.

### Blocking resolution (for contract consumers)

1. Architect approves **OpenAPI addendum batch** for: semrush latest/delete, suggest, sync-failed, gsc/verify, ga4/health (if exposed via PHP), channel callbacks in locked `docs/api/openapi.yaml`.
2. **Retire** nothing in the list above unless a consumer is confirmed unused (sync-complete only).

---

## GAP-API-10 — Readiness channel enum conflict

### Facts

| Layer | Channel identifiers |
|-------|---------------------|
| `docs/api/openapi.yaml` `ReadinessResponse` | `shopify`, `gmc` |
| `docs/cie_v231_openapi.yaml` | `shopify`, `gmc` |
| PHP publish + deploy (`SkuController`, `ChannelDeployService`) | `shopify`, `gmc` only |
| DECISION-001 | Shopify PRIMARY, GMC SECONDARY, Amazon DEFERRED |

Some audit/dashboard materials reference `google_sge`, `amazon`, `ai_assistants`, `own_website` as **measurement / AI visibility** channels, not deploy targets.

### Recommendation (no code change)

- **Canonical API contract for readiness deploy scores:** `shopify` + `gmc` (matches DECISION-001).
- **Do not** replace OpenAPI enum with four-channel IDs without a formal spec change.
- If UI needs four measurement channels, expose them under a **separate schema** (e.g. dashboard `channel-stats`) or document as non-contract fields — not `ReadinessResponse.channels[]`.

**Architect sign-off line:** *“OpenAPI readiness channels remain shopify/gmc; four-channel labels are dashboard-only until a spec amendment.”*

---

## GAP-API-13 — N8N callback endpoint contract

### Facts

- Integration spec and `docs/cie_v231_openapi.yaml` define:
  - `POST /skus/{sku_code}/channel-deployed`
  - `POST /skus/{sku_code}/channel-failed`
- Runtime PHP routes (prefix `/api`):
  - `POST /api/skus/{skuCode}/channel-deployed`
  - `POST /api/skus/{skuCode}/channel-failed`
- N8N W6 must call **`PHP_API_URL`** + `/api/skus/...` with auth + HMAC per `docs/N8N_HMAC_VERIFY.md`.

### Recommendation

- **Close GAP-API-13** as *implemented with path prefix `/api`* once `docs/api/openapi.yaml` is synced with `cie_v231_openapi.yaml` callback paths.
- **Do not** add new callback routes; wire existing N8N success/fail nodes to the two routes above.
- `POST /api/admin/sync-failed` remains **ERP-only** (W1), not channel deploy.

---

## Sign-off checklist (architect)

- [ ] Approve OpenAPI addendum list (GAP-ROUTES-01 table)
- [ ] Confirm readiness enum = `shopify`/`gmc` (GAP-API-10)
- [ ] Confirm N8N W6 uses `/api/skus/{code}/channel-deployed|failed` (GAP-API-13)
- [ ] Reject or defer `sync-complete` until W1 + contract updated

*After sign-off, update `GAP_LOG.md` resolution rows and apply doc-only OpenAPI edits via Change Protocol.*
