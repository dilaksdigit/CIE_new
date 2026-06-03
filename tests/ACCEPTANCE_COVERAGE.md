# Acceptance Test Coverage Matrix (P4.1)

**Target:** 72 tests per CIE_Doc4b (6 stages × 10 SKUs + 12 edge cases)  
**Last run:** `cd backend/python && python -m pytest tests/ -q`

| Stage | Description | Automated | Count | Notes |
|-------|-------------|-----------|-------|-------|
| 1 | Gate matrix per SKU | Partial | 10+ | `test_golden_matrix.py` definitive + hero smoke |
| 2 | Tier / G6.1 | Partial | 8 | `test_golden_skus.py`, kill/harvest |
| 3 | Channel decisions | Partial | 14 | `test_golden_stages.py`, `ChannelGovernorTest.php` |
| 4 | Maturity scores | Partial | 10 | `test_golden_maturity_total` parametrized |
| 5 | Title validation | Partial | 1 | Hero title flags |
| 6 | ERP → tier | Manual | 0 | P3.2 staging + `e2e_publish_flow_check.py` |
| Edge | Boundary cases | Done | 12 | `test_golden_matrix.py` edge section |
| RBAC | Role × route | Partial | 15+ | `RbacEndpointMatrixTest.php` (live tokens) |
| PHP | Governor / rules | Partial | 8 | Phase0 + Phase1 PHPUnit |

**Python total:** 75 passed, 1 skipped (`python -m pytest tests/ -q`)  
**PHPUnit (no DB):** 4 `ChannelGovernorTest` + Feature tests (live RBAC skipped without tokens)

## Gap to 72

- Stage 6 ERP tier_history (needs live ERP payload) — **manual staging**
- Live Shopify/GMC round-trip (P3.3–P3.4) — **manual staging**
- GAP-P4-GOLDEN-G4 — **resolved** (golden prose aligned; ALL_PASS publish test added)

See `acceptance_tests.yaml` for Given/When/Then catalog.
