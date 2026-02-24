# Unified API Specification — 7.1 Core API Endpoints

Reference: CIE v2.3.1 ENFORCEMENT + DEV SPEC. OpenAPI: `docs/openapi/cie-v2.3.1-unified.yaml`.

## 7.1 Core API Endpoints — Implementation Status

| Method | Endpoint | Purpose | Service | Status | Implementation |
|--------|----------|---------|---------|--------|----------------|
| POST | /api/v1/sku/validate | Pre-publish validation (G1–G7 + G6.1) | PHP → Python | ✅ Exists | FastAPI `backend/python/api/main.py` |
| POST | /api/v1/sku/embed | Generate embedding for description text | PHP → Python | ✅ Exists | FastAPI `backend/python/api/main.py` |
| POST | /api/v1/sku/similarity | Cosine similarity description vs cluster intent | PHP → Python | ✅ Exists | FastAPI `backend/python/api/main.py` |
| GET | /api/v1/taxonomy/intents | Locked intent enum list filtered by tier | Python → PHP | ✅ Added | PHP `GET /api/v1/taxonomy/intents` (IntentsController) |
| GET | /api/v1/clusters | Master cluster list with IDs and intent vectors | Python → PHP | ✅ Added | PHP `GET /api/v1/clusters` (ClusterController) |
| POST | /api/v1/audit/run | Trigger AI citation audit (category 20 questions) | Python (scheduled) | ✅ Exists | PHP `POST /api/v1/audit/run` → FastAPI `/queue/audit` |
| GET | /api/v1/audit/results/{category} | Latest audit scores + decay per SKU | Python → PHP | ✅ Added | PHP `GET /api/v1/audit/results/{category}` (AuditController) |
| POST | /api/v1/brief/generate | Auto-generate content brief (Week 3 decay) | Python (auto) | ✅ Exists | PHP briefs + FastAPI `/queue/brief-generation` |
| GET | /api/v1/sku/{id}/readiness | Per-channel readiness scores (0–100) | PHP | ✅ Added | PHP `GET /api/v1/sku/{id}/readiness` (SkuController) |
| POST | /api/v1/erp/sync | Receive ERP data push; recompute tiers | ERP → Python → PHP | ✅ Added | PHP `POST /api/v1/erp/sync` (TierController/ErpController) |

## Base URLs (one main port for Python)

- **Main — FastAPI (Python):** `http://localhost:8000` — single app: `/`, `/health`, `/api/v1/sku/validate`, `/api/v1/sku/embed`, `/api/v1/sku/similarity`, `/validate-vector`, `/queue/audit`, `/queue/brief-generation`, `/audits/{id}`, `/briefs/{id}`, `/docs`.
- **PHP (CMS):** `http://localhost:8080` — all `/api/v1/*` routes (SKUs, taxonomy, clusters, audit, brief, ERP, readiness).

## 7.2 Pre-Publish Validation

- **Request:** `POST /api/v1/sku/validate` (FastAPI on 8000) with JSON body (sku_id, cluster_id, tier, primary_intent, secondary_intents, title, description, answer_block, best_for, not_for, expert_authority, action).
- **Response 200:** `{ "status": "pass", "gates": { ... }, "vector_check": { ... }, "publish_allowed": true }`.
- **Response 400:** `{ "status": "fail", "gates": { ... }, "publish_allowed": false }` with error_code, detail, user_message per gate.
- **7.3 Error codes:** CIE_G1_INVALID_CLUSTER, CIE_G2_INVALID_INTENT, CIE_G3_*, CIE_G4_*, CIE_G5_*, CIE_G6_*, CIE_G6_1_*, CIE_G7_AUTHORITY_MISSING, CIE_VEC_SIMILARITY_LOW.
