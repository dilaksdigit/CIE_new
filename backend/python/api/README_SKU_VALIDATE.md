# SKU Validate API (FastAPI)

**Endpoint:** `POST /api/v1/sku/validate`  
**Spec:** CIE v2.3.1 Section 7.2 / 7.3 — 8 gates, Harvest/Kill rules, 500ms target.

## Request body (JSON)

| Field | Type | Description |
|-------|------|-------------|
| sku_id | string | Optional |
| cluster_id | string | Required for G1 (must be in master list if `CIE_MASTER_CLUSTER_IDS` set) |
| tier | string | One of `hero`, `support`, `harvest`, `kill` |
| primary_intent | string | One of 9 valid intents (e.g. Specification, Compatibility) |
| secondary_intents | string[] | 1–3 for hero, 1–2 for support, 0–1 for harvest, 0 for kill |
| title | string | Optional |
| description | string | Optional |
| answer_block | string | 250–300 chars, must contain primary intent keyword (G4; suspended for harvest) |
| best_for | string[] | Min 2 (G5; suspended for harvest) |
| not_for | string[] | Min 1 (G5; suspended for harvest) |
| expert_authority | string | Required for hero/support (G7; suspended for harvest) |
| action | string | `save` or `publish` |

## Gates (in order)

- **G1:** cluster_id exists in master cluster list (or non-empty if master list not set).
- **G2:** primary_intent is one of 9 valid enums.
- **G3:** 1–3 secondary_intents, all different from primary, all valid.
- **G4:** answer_block 250–300 chars and contains primary intent keyword. **Harvest: suspended.**
- **G5:** ≥2 best_for, ≥1 not_for. **Harvest: suspended.**
- **G6:** tier is hero | support | harvest | kill.
- **G6.1:** Harvest = Specification primary + max 1 secondary; Kill = no intents (only G1+G6 run).
- **G7:** expert_authority non-empty for hero/support. **Harvest: suspended.**

**Kill tier:** Only G1 and G6 are run.

## Response

- **200:** `{ "status": "pass", "message": "All gates passed." }`
- **400:** `{ "status": "fail", "failures": [ { "error_code", "detail", "user_message" }, ... ], "message": "..." }`

All failures are returned (not just the first).

## Run

The validate endpoint is part of the **unified FastAPI app** (`api/main.py`). From `backend/python`:

```bash
pip install -r requirements.txt
python run_validate_api.py
# or: uvicorn api.main:app --host 0.0.0.0 --port 8000
```

Runs on **port 8000** (main). Open **http://localhost:8000/** and **http://localhost:8000/docs**. All endpoints (embed, similarity, validate, queue) are on this single FastAPI app.

Optional env: `CIE_MASTER_CLUSTER_IDS=id1,id2,id3` (comma-separated cluster IDs for G1).
