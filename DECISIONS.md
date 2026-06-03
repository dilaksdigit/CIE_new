# DECISIONS.md — CIE v2.3.2 Decision Log

All significant technical decisions. Future maintainers must understand why every major choice was made.

Format: `DECISION-NNN | Date | Title | Decision | Rationale`

---

| ID | Date | Title | Decision | Rationale |
|----|------|-------|----------|-----------|
| DECISION-001 | 2026-02 | Channel Priority | Shopify = PRIMARY. GMC = SECONDARY. Amazon = DEFERRED. | Focus on revenue channel. SP-API approval delays add complexity. |
| DECISION-002 | 2026-02 | No Human Approval Queue | Gates G1–G7 + VEC are the quality control. Auto-publish on pass. | Bottleneck elimination. Human review reintroduces the problem gates solve. |
| DECISION-003 | 2026-02 | 2 Users, Not 8 Roles | 8 RBAC roles remain in code for future scale. Only 2 active user accounts. Admin creates users via `/admin/users`; public `/auth/register` returns 403. | Avoids UI complexity with no current value. |
| DECISION-004 | 2026-02 | Semrush CSV, Not API | Manual weekly CSV upload, not Semrush API integration. | API requires paid tier. CSV gives admin control. Architecture is API-ready. |
| DECISION-005 | 2026-02 | Fail-Soft Vector Validation | Below 0.72 = warning, not block. Save allowed. Publish blocked when vector fails hard rules. | Hard-block prevents in-progress saves. Warning + audit log = governance. |
| DECISION-006 | 2026-02 | Kill SKU = Total Lockout | All fields disabled at code level. No content effort permitted. | Kill = negative net value. System enforces zero effort at field level. |
| DECISION-007 | 2026-02 | Drift-Safe v3 with 5 LLM Spaces | 5 spaces (vs v3's 4) to keep prompt generation separate. | Solo developer without DTL benefits from explicit two-step: confirm → prompt. |
| DECISION-008 | 2026-02 | Light Theme Only | No dark mode. No toggle. Warm off-white palette throughout. | Office-hours use case. Dark mode adds cost, zero business value. |
| DECISION-009 | 2026-02 | Desktop Only (1280px+) | No responsive design. No mobile styles. | Both users on desktop. Mobile adds ~35% frontend work for a non-existent use case. |
| DECISION-010 | 2026-02 | Auto-Publish on Gate Pass | All 7 gates pass → auto-deploy to Shopify + GMC via W6. | Create Once, Deploy Everywhere principle. |
| DECISION-011 | 2026-06 | Gate Key Naming | Canonical API gate keys: ENF §7.2 long format (`G1_cluster_id`, `G5_best_not_for`, etc.). Python FastAPI `POST /api/v1/sku/validate` is the **sole** gate engine for CMS validate/publish paths. | `ValidationService` proxies to `PythonWorkerClient::validateSkuGates()`; PHP `GateValidator` retained only for legacy/offline tooling, not writer validate. `sku_gate_status` populated from Python response. |
| DECISION-012 | 2026-06 | Migration Renumber (P0.5) | Duplicate migration prefixes renumbered 145–154; deprecated patches moved to `database/migrations/deprecated/`. | Fresh `migrate` and Docker init require unique sequence numbers. |
| DECISION-013 | 2026-06 | PostgreSQL as system of record | All app connections use PostgreSQL (`pgsql` / `psycopg2`). Legacy `database/migrations/*.sql` remain MySQL dialect for reference; apply schema via `database/postgres/`. | Owner moved off MySQL; business rules unchanged — driver and SQL dialect only. |
| DECISION-014 | 2026-06 | Spec-native PG indexes | Greenfield init runs `database/postgres/canonical/03_spec_indexes.sql` after migrations (`init/03_spec_indexes.sql`). Indexes sourced from Master §6 / Build Pack / Hardening / Semrush specs via `scripts/generate_pg_spec_indexes.py`. | `convert_mysql_migrations_to_pg.py` strips inline KEY/INDEX; converter-only deploy is deprecated. Verified with `scripts/verify_pg_indexes.py`. |
| DECISION-015 | 2026-06 | Operator doc PG alignment | Operator guides (`README`, `QUICK_START`, `IMPLEMENTATION_GUIDE`, wiring summaries, `API_REFERENCE`) use PostgreSQL 5432 / `psql` only. Legacy MySQL SQL is reference-only. | Prevents misconfiguration from stale MySQL instructions. Verified with `scripts/verify_docs_postgres_alignment.py`. |

---

## Golden test fixture codes (Task 10)

Golden test fixture codes follow pack codes from CIE_Doc4b (e.g. CBL-BLK-3C-1M, FLR-ARC-BLK-175). Checklist labels (SKU-CABLE-001, etc.) are illustrative only. Channel outputs: `shopify` and `gmc` only (DECISION-001).

---

## Task completion log (Production Roadmap)

| Task ID | Date | Note |
|---------|------|------|
| P0.2 | 2026-06-01 | `.gitignore` hardened for secrets and debug logs |
| P0.5 | 2026-06-01 | Migrations 145–154; see DECISION-012 |
| P0.5 | 2026-06-01 | `safe_hardening_patch.sql` / `hardening_schema_patch.sql` → `deprecated/` |
| P2.1 | 2026-06-01 | `n8n/workflows/W5_gate_validation.json` added |
| P2.2 | 2026-06-01 | `/admin/users` UI + `UserController` + routes; register disabled |
| P5.6 | 2026-06-01 | `scripts/import_n8n_workflows.sh` added |
| P3.1 | 2026-06-01 | `scripts/e2e_publish_flow_check.py` — staging E2E runner |
| P3.1 | 2026-06-03 | `scripts/verify_staging_readiness.sh` — batch-fix regression + optional live E2E |
| CI | 2026-06-03 | Frontend build job + batch-fix PHPUnit in `.github/workflows/ci.yml` |
| P4.2–P4.4 | 2026-06-01 | `test_golden_matrix.py` + `conftest.py` — 42 tests passing |
| P5.5 | 2026-06-01 | Audit log RBAC scope in `AuditLogController` |
| P4.1 | 2026-06-01 | `tests/ACCEPTANCE_COVERAGE.md` — 69 Python + PHPUnit matrix |
| P6.2 | 2026-06-01 | `RbacEndpointMatrixTest.php` + `ChannelGovernorTest.php` |
| P0.4 | 2026-06-01 | `scripts/pre_deploy_check.sh` |
| P2.4 | 2026-06-01 | `docs/N8N_HMAC_VERIFY.md` for W6/W5 signature verify node |
| Day-2 | 2026-06-03 | GMC Support threshold, PermissionService multi-role, IntentsController tier_rules from DB, `api_v1_base` config, docs PG alignment, CI migration smoke |
| GAP-ROUTES-01 | 2026-06-03 | OpenAPI contract parity — all PHP routes in `cie_v231_openapi.yaml`; `scripts/verify_openapi_route_parity.py`; integrator callbacks use `/api` server base |
| GAP-P3-N8N-E2E | 2026-06-03 | N8N/Shopify deploy automated E2E (static workflow parity + PHPUnit Http::fake wire test) |
| GAP-P0-SECURITY | 2026-06-03 | Production debug/credential hardening — config forces debug off; generic API errors in prod; secret scan + pre-deploy gates; docker-compose safe defaults |
| PG-INDEXES | 2026-06-03 | Spec-native index bootstrap — `canonical/03_spec_indexes.sql`, Docker `init/03`, `verify_pg_indexes.py`; DECISION-014 |
| DOC-PG-ALIGN | 2026-06-03 | Operator documentation aligned to PostgreSQL — removed mysql-service/3306/pymysql instructions; DECISION-015 |
| Staging | 2026-06-03 | `docs/STAGING_E2E_AND_SIGNOFF.md`, `verify_n8n_deploy_payload.py`, architect review doc, AI quorum doc, runbook bridge job |
