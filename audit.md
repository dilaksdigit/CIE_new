# CIE Project Audit Report
**Date:** 2026-03-20
**Auditor:** Claude Code (automated static analysis)
**Scope:** Full codebase — backend PHP, backend Python, database, frontend, tests, infrastructure

---

## 1. Executive Summary

The Content Intelligence Engine (CIE) is a multi-tier content governance platform for e-commerce SKU management. The system enforces content quality through a gate-based validation pipeline (G1–G7 plus a vector/semantic gate) before allowing SKUs to be published. The project is at version 2.3.2 and shows evidence of substantial specification-driven development, with code comments referencing multiple spec documents (CIE_Master_Developer_Build_Spec.docx, CIE_v2.3.1_Enforcement_Dev_Spec.pdf, CIE_v232_Hardening_Addendum.pdf, and amendment packs).

**Overall Health:** Functional but fragile. The validation pipeline is well-specified and mostly implemented correctly. The dual PHP+Python validation architecture introduces significant synchronisation risk. There are 85 migration files, indicating rapid iterative schema evolution. Test coverage is thin. Several schema design issues, security concerns, and architectural inconsistencies are documented below.

**Critical findings summary:**
- G5_TechnicalGate.php is misnamed relative to its actual function (it validates best_for/not_for, not technical specs)
- The `quorum_pause` value in ValidationService.php line 297 is hard-coded to `2` despite being marked as "not in 52 rules" — a spec violation
- audit_log.entity_id was originally CHAR(36) (UUID) then altered to VARCHAR(50) in migration 026, creating a column-type inconsistency that affects JOIN queries
- Token storage uses `sessionStorage` (not `localStorage`) which means logout on tab close but is visible to same-origin JavaScript
- The `vector_retry_queue.php` processor writes to `sku_content.vector_similarity` (line 163) but no `sku_content` table is defined in any migration
- Migration numbering has two files named `011_*` and two named `031_*` and two named `034_*` and two named `070_*` — unclear execution order
- No HTTPS enforcement in nginx config visible in docker-compose.yml
- The `publishSku()` function in `frontend/src/services/api.js` (lines 63–71) calls validate then PUT content but ignores the validation response — a client-side bypass of gate checks

---

## 2. Tech Stack & Architecture

### Languages & Runtimes
| Layer | Technology | Version |
|---|---|---|
| Backend API (primary) | PHP + Laravel Framework | PHP ^8.1, Laravel ^10.0 |
| Backend Worker (AI/ML) | Python + FastAPI | Python 3.x, FastAPI 0.115.6 |
| Frontend | React + Vite | React 18.2.0, Vite 7.3.1 |
| Database | MySQL | 8.0 (docker-compose) |
| Cache / Queue | Redis | 7-alpine |
| Reverse Proxy | nginx | alpine |

### Key Dependencies
**PHP:** `guzzlehttp/guzzle ^7.5`, `league/fractal ^0.20`, `predis/predis ^2.1`, `monolog/monolog ^3.3`, `respect/validation ^2.2`, `vlucas/phpdotenv ^5.5`

**Python:** `fastapi==0.115.6`, `uvicorn==0.34.0`, `openai==1.12.0`, `anthropic==0.18.0`, `google-generativeai==0.3.2`, `pydantic==2.12.5`, `psycopg2-binary==2.9.9` (unused — MySQL is the DB), `pymysql` (used in code), `redis==5.0.1`, `numpy==1.26.4`

**Frontend:** `axios ^1.6.7`, `react-router-dom ^6.22.0`, `recharts ^2.12.0`, `clsx ^2.1.0`

### Architecture Overview
```
Browser → nginx → PHP API (Laravel, port 8080)
                         ↓ HTTP (Guzzle)
               Python Worker (FastAPI, port 8000)
                         ↓
              MySQL 8.0 + Redis 7
```

The PHP API handles all HTTP routing, RBAC, SKU CRUD, and orchestration. The Python worker provides:
1. OpenAI text-embedding-3-small vector embeddings
2. Cosine similarity vs cluster centroids (Redis-cached)
3. GSC and GA4 baseline metric capture
4. Full G1–G7 gate validation (duplicate of PHP implementation — see Section 3)

**Architecture concern:** Gates G1–G7 are implemented independently in both PHP (`backend/php/src/Validators/Gates/`) and Python (`backend/python/api/gates_validate.py`). These are not the same code path — the PHP layer calls `GateValidator::validateAll()` for the `/sku/{id}/validate` endpoint, and the Python layer runs `run_all_gates()` for the `/api/v1/sku/validate` endpoint. Divergence between the two implementations is an ongoing risk (see Section 11).

---

## 3. Backend PHP Audit

### Routes (`backend/php/routes/api.php`)

The route file is well-organised. All spec-compliant routes live under `/api/v1` prefix with `auth` middleware applied to the group. Key observations:

**Route registration:**
- `POST /admin/semrush-import` is registered outside the `/v1` prefix (line 25) — this is documented in a comment as intentional per spec
- `POST /api/admin/erp-sync` (line 28) duplicates `POST /api/v1/erp/sync` (line 90) — two separate ERP sync routes exist, both pointing to `TierController::erpSync`
- The suggestion status proxy (lines 121–129) is implemented as an inline closure in the route file, bypassing the controller pattern. The closure calls `env('CIE_ENGINE_TOKEN')` directly

**RBAC coverage on routes:**
- `GET /sku/{id}` has no explicit `rbac:` middleware — any authenticated user can view any SKU detail
- `POST /sku` (create) has no `rbac:` filter, meaning any authenticated user can create SKUs
- `POST /sku/{id}/publish` has no `rbac:` filter beyond `auth`
- `GET /queue/today` has no `rbac:` filter

**Missing routes (compared to controllers that exist):**
- `UserController` is listed in `backend/php/src/Controllers/` but no `/users` route exists in api.php
- `TitleController` exists but no route references it
- `ClusterChangeRequestController` exists but no route references it

### Controllers

**SkuController.php** (`backend/php/src/Controllers/SkuController.php`) — 1019 lines

Key findings:
- `update()` method (line 174) performs a double-validation: it runs `validate()` before the save (line 250) to check for blocking failures, then runs it again after the save (line 306). The pre-save validation runs against `$sku->fresh()` which has not yet received the `$updateData`, meaning gates are checked against the pre-update state, not the draft being saved. This means a user can save data that would fail gates if the current state passes gates.
- `store()` method (line 320) casts `auth()->user()->role->name` directly without null-safety — line 337: `auth()->user()->role->name ?? 'system'` is present but line 338 calls `auth()->id()` directly. If user is null (e.g. system call), this will throw.
- `batchLoadGateStatuses()` (line 636) queries `sku_gate_status` where `sku_id` column stores the business SKU code (not the UUID PK). This is intentional by design but inconsistent with the audit_log table which uses the UUID.
- `canonicalStatusesHaveRequiredFullCodes()` at line 701 requires "at least 7 of 9" codes — this heuristic threshold means partial gate runs (e.g. if Python is degraded and VEC gate is pending) will fall back to the inline estimation, which may disagree with canonical results.
- `loadHistory()` at line 960 queries `audit_log` (snake_case table name) but the `AuditLog` model (line 11 import) maps to the same table. The raw DB query at line 962 checks `Schema::hasTable('audit_log')` separately from the Eloquent model — no DRY issue but confirms the table name is `audit_log`.
- `queueToday()` (line 532) hard-codes `defaultThreshold = 85` at line 883 inside `fallbackChannelReadiness()`. Although BusinessRules is consulted first, the literal fallback `85` is used when BusinessRules returns null — this violates the no-numeric-fallback rule referenced throughout spec comments.

**AuditLogController.php** — well-structured with filter enrichment. No critical issues. `limit` is capped at 500 per request (line 50) but there is no pagination cursor — large audit logs will have incomplete views.

**ValidationController** is referenced in routes but not read in detail; its existence is confirmed.

### Services

27 services found in `backend/php/src/Services/`:
`AIAgentService`, `ApprovalService`, `AuditLogService`, `BaselineService`, `BusinessRulesService`, `ChannelDeployService`, `ChannelGovernorService`, `ChannelTierRulesService`, `ContentHealthScoreService`, `DecayService`, `ERPSyncService`, `ExecutiveReportService`, `FAQService`, `FaqSuggestionService`, `GmcFeedService`, `IntentAssignmentService`, `MaturityScoreService`, `NotificationService`, `PermissionService`, `PublishTraceService`, `PythonWorkerClient`, `ReadinessScoreService`, `ShopifyProductPullService`, `ShopifyRateLimiter`, `TierCalculationService`, `TitleEngineService`, `ValidationService`

**ValidationService.php** (`backend/php/src/Services/ValidationService.php`):
- `evaluateAuditQuorum()` at line 292: `$quorumPause = 2` is hard-coded with comment "§5.3: not in 52 rules; hard-coded". This is a direct spec violation — all thresholds are supposed to come from the `business_rules` table.
- `intentDraftToTaxonomyLabel()` at line 269 maps API keys to display labels. The mapping includes `'inspiration' => 'Inspiration / Style'` with a space/slash. If the taxonomy seed stores `'Inspiration / Style'` (with spaces around slash), the G2 lookup using `LOWER(label) = ?` will work, but G2IntentGate uses `str_replace(' ', '_', $intentName)` for the `intent_key` lookup. The intent_taxonomy seed uses `intent_key = 'inspiration'` and `label = 'Inspiration / Style'` which will match via label check but not via key check for normalized forms like `inspiration_style`.

**PythonWorkerClient.php** (`backend/php/src/Services/PythonWorkerClient.php`):
- Uses `env('PYTHON_API_URL', 'http://localhost:5000')` at line 15 — default port 5000 (Flask era). The Python service actually runs on port 8000 (uvicorn). The docker-compose sets `PYTHON_API_URL=http://python-worker:8000` correctly, but a bare deployment without the env var will try port 5000.
- `queueAudit()` posts to `/queue/audit` (line 72) and `queueBriefGeneration()` to `/queue/brief-generation` (line 98). Neither endpoint is defined in `backend/python/api/main.py`. These calls will 404 silently (the method returns `['queued' => false, 'error' => 'Queue failed']` on non-200 status).
- `getAuditResult()` calls `GET /audits/{auditId}` (line 127). This endpoint does not exist in `main.py`. Same silent failure pattern.

### Validators / Gates (G1–G7)

**GateValidator.php** (`backend/php/src/Validators/GateValidator.php`):
- The gate pipeline is run via instantiation `new $gateClass()` (line 41). No dependency injection — gates cannot be mocked in unit tests without extension.
- Each gate execution creates a `ValidationLog` entry (line 48–56) inside the loop. For a SKU with 8 gates this is 8 database inserts per validation call. Combined with the `SkuGateStatus::updateOrCreate()` (line 71–82) and `AuditLog::create()` (line 86–100) calls, a single validation creates up to 24 database writes in the hot path.
- The `hasVectorWarn` path at lines 133–136: when vector warns but no other failures exist, status is set to `ValidationStatus::VALID` but `canPublish = false`. This means a SKU can be `VALID` but not publishable. This is intentional per Hardening Addendum §1.1 but is unintuitive and could confuse consumers of the API.

**G1_BasicInfoGate.php** — validates cluster_id exists in `cluster_master` table with `is_active = true`. The gate resolves `sku->primaryCluster->name` as the business cluster_id and then queries `cluster_master.cluster_id`. This two-step lookup (UUID → name → cluster_master) is fragile: if `clusters.name` does not match `cluster_master.cluster_id` exactly, validation fails incorrectly.

**G2_IntentGate.php** — validates exactly one primary intent from the 9-intent taxonomy. The private `intentTitleKeyword()` method at line 96 is defined but never called (dead code). The comment at line 85 says "No title keyword check per spec" — this method was apparently removed from gate logic but not deleted.

**G3_SecondaryIntentGate.php** — when a Harvest SKU has a secondary intent not in the allowed set `[problem_solving, compatibility, specification]`, the gate returns a `GateType::G6_1_TIER_LOCK` result (line 66) instead of a G3 result. This is by design per spec comment, but means G3 gate code produces G6.1 gate results, mixing gate responsibilities.

**G4_AnswerBlockGate.php** — validates `ai_answer_block` character count (250–300) from `BusinessRules`. Also checks for intent keyword via `answerContainsIntentKeyword()`. The keyword stem for `'installation'` maps to `'install'` (line 141) which is correct, but `'troubleshooting'` maps to `'shoot'` (line 142) — this is an extremely short stem that will match unrelated words containing "shoot" (e.g. "photo shoot", "overshoot").

**G4_VectorGate.php** — calls Python similarity endpoint. Uses `new \GuzzleHttp\Client(['timeout' => 3.0])` (line 208) — 3-second hard timeout on the vector call. On failure, queues into `vector_retry_queue` table. Also logs to `AuditLog`. The gate can return either a single `GateResult` or an `array` of `GateResult` objects (union return type at line 41). The caller `GateValidator::validateAll()` handles this at line 42 with `$gateResults = is_array($rawResult) ? $rawResult : [$rawResult]` — correct but unusual interface.

**G5_TechnicalGate.php** — the filename says "Technical" but the gate validates `best_for` / `not_for` counts (best_for_min_entries, not_for_min_entries from BusinessRules). The gate code `G5_BEST_NOT_FOR` (line 28) is correct, the filename is misleading. Contains a `validateUnits()` private method (lines 58–71) that is never called (dead code). This was apparently from a previous version of G5 that checked measurement units in specifications.

**G6_CommercialPolicyGate.php** — returns an `array` of `GateResult` objects (two gates: G6_TIER_TAG and G6_1_TIER_LOCK). The `killDraftAttemptsContentMutation()` method at line 19 reads `app('cie.validation_draft_keys')` which is set in `ValidationService::validateSku()` (line 191). If called outside that flow (e.g. direct `validate()` call without draft keys being registered), the method receives an empty array and content mutation detection is bypassed.

**G7_ExpertGate.php** — validates `expert_authority` is non-empty and passes a regex specificity check. The regex at line 20 checks for standards like `BS`, `ISO`, `EN`, `IEC`, `CE`, etc. However line 76 calls `expertAuthorityMatchesPythonSpecificity()` which applies the same regex AFTER the generic phrase check (lines 58–73). This means if expert_authority passes the generic-phrase check but does not match the regex, it fails. A value like "Tested by an independent UKAS-accredited laboratory" would fail the regex check even though it is legitimate expert authority — `UKAS` is not in the pattern.

### Enums

**GateType.php** — 11 cases. Includes `G5_TECHNICAL` (line 12) which maps to `'G5_TECHNICAL'` but no gate class uses this enum value — `G5_TechnicalGate` emits `G5_BEST_NOT_FOR`. The `G6_COMMERCIAL_POLICY` case (line 13) appears in the `GateValidator` gateIdMap (line 201) but no gate class produces this code directly (G6CommercialPolicyGate produces G6_TIER_TAG and G6_1_TIER_LOCK).

**TierType, ValidationStatus, RoleType** — standard PHP 8.1 backed enums, no issues noted.

### Commands

**RefreshGoldenGateStatusCommand.php** — an artisan command `cie:refresh-gate-status` that re-seeds and re-validates the 10 golden SKUs. This command directly writes to the database from a PHP class (hardcoded title strings at lines 104–114, content updates at lines 150–226). This means "golden data" is duplicated between:
1. `database/seeds/008_seed_golden_sku_content.sql`
2. The `goldenContentUpdates()` array inside this command
3. `database/seeds/006_seed_dummy_skus.sql`

Any update to golden content must be applied in all three places. The command for `CBL-RED-3C-2M` (Harvest) explicitly NULLs out `ai_answer_block`, `best_for`, `not_for`, `long_description`, and `expert_authority` (lines 135–143) to ensure G6 passes — this is correct per spec but means running this command on a production database would destroy any real Harvest SKU content matching those fields if the command were run without `--codes=` filter.

---

## 4. Backend Python Audit

### API Endpoints (`backend/python/api/main.py`)

FastAPI app with the following routes:
| Method | Path | Description |
|---|---|---|
| GET | `/` `/api/` `/api` | Health check |
| POST | `/api/v1/sku/embed` | Generate text embedding |
| POST | `/api/v1/sku/similarity` | Cosine similarity vs cluster centroid |
| POST | `/api/v1/sku/validate` | Full G1–G7 gate validation |
| POST | `/api/v1/baseline/gsc-metrics` | GSC metrics for a URL |
| POST | `/api/v1/baseline/ga4-metrics` | GA4 metrics for a URL |

**Missing endpoints referenced by PHP PythonWorkerClient:**
- `POST /queue/audit` — not implemented
- `POST /queue/brief-generation` — not implemented
- `GET /audits/{id}` — not implemented
- `GET /health` — not implemented (the health check is at `/`, not `/health`)

**`sku_validate` endpoint** (line 333):
- Uses `async def sku_validate(request: Request)` but manually calls `await request.json()` and validates with Pydantic. This bypasses FastAPI's automatic body injection. Acceptable but non-idiomatic.
- Truncates audit detail to 255 characters at line 404: `detail_str = json.dumps(audit_detail)[:255]`. The audit_log `action` column is `VARCHAR(50)` — the combined `event|detail` string written at line 93 in gates_validate.py could exceed 50 chars silently; MySQL will truncate silently or error depending on strict mode.

**`sku_similarity` endpoint** (line 128):
- Returns `{"status": "pending", "message": "..."}` when cluster is not in Redis cache (line 165). This is fail-soft correct, but there is no mechanism documented for how clusters get loaded into Redis in the first place. If Redis is empty (fresh deployment), all vector checks return `pending`.

### Validation Schemas (`backend/python/api/schemas_validate.py`)

`SkuValidateRequest` at line 32: `primary_intent` is typed as `str | list[str] | None` — the list form is handled in `run_g2()` in gates_validate.py. This is a compatibility shim for callers that send arrays. `psycopg2-binary` is in requirements.txt (line 45 of requirements.txt) but the Python code uses `pymysql` everywhere — psycopg2 is unused dead dependency.

### Vector/AI Logic (`backend/python/src/vector/validation.py`)

`validate_cluster_match()` — correctly implements fail-soft: if `request_vector is None` (embedding API failed), returns `status: pending`. If similarity below threshold, returns `status: warn` not `fail` (per Hardening Addendum §1.1 DECISION-005).

`cluster_cache.get_cluster_vector(cluster_id)` — the cache module is imported but not audited in detail. Redis availability is critical — if Redis is down, all cluster centroid lookups fail and all vector checks return pending.

### `gates_validate.py`

`BusinessRules._load()` at line 38: performs a database query on first access and caches in a class-level dict. There is no TTL or cache invalidation. In a long-running uvicorn process, business rule changes made through the admin UI will not be reflected until the Python worker is restarted. `BusinessRules.invalidate()` is defined but nothing in the Python code calls it on rule updates.

`run_g3()` lines 251–330: when `tier == "harvest"` and secondary intent is not in allowed set, returns a `G6_1_tier_lock` failure item (line 274). This mirrors the PHP behavior but means G3 produces G6.1 failures — same dual-responsibility issue.

`run_g7()` calls `check_specificity()` from `ai_agent_service` (line 596) — an external import. The `ai_agent_service` module was not available for review but is in the dependency chain for every Hero/Support validation. If this module fails to import, G7 will throw on every validation.

`_queue_vector_retry()` (line 509) catches exceptions and logs a warning — if the `vector_retry_queue` table does not exist, the insert silently fails and the SKU remains in degraded mode with no retry queued.

`run_all_gates()` in `gates_validate.py` — the audit `|` operator on `audit_degraded` at line 666 means if any single audit log write fails, the entire response sets `audit_degraded = True`. This is an informational flag; it does not affect gate pass/fail outcomes.

**GAP noted in code comments** (line 682): "GAP_LOG: Harvest primary='specification' enforced in G6.1, not G2. Functionally blocked." — G2 is always run for Harvest SKUs in the Python pipeline but the enforcement of Specification-only primary intent is in G6.1. This means a Harvest SKU with a wrong primary intent will fail G6.1 not G2, which is correct but creates a confusing UX.

### Vector Retry Queue (`backend/python/src/jobs/vector_retry_queue.py`)

At line 163: `cursor.execute("UPDATE sku_content SET vector_similarity = %s WHERE sku_id = %s", (similarity, sku_id))`. There is no `sku_content` table in any migration file found. This UPDATE will silently do nothing (0 rows affected) on every retry resolution. Vector similarity scores are never persisted to any table via this path.

The backoff strategy at line 211: `min(5 * (2 ** new_count), 20)` caps at 20 minutes. With 5 max retries, total retry window is 5 + 10 + 20 + 20 + 20 = 75 minutes. The KPI comment says ">95% pending resolved ≤30 min" — the backoff strategy makes this target unachievable on first failure because retry 3 already hits the 20-minute cap.

---

## 5. Database Audit

### Migrations (Schema Evolution)

Total migration files: 85 (confirmed by `ls | wc -l`). Sequencing issues found:

**Duplicate numbering:**
- `011_create_audit_log_table.sql` AND `011_create_cluster_vectors_table.sql` — both prefixed `011`
- `031_add_erp_velocity_fields_to_skus.sql` AND `031_scrub_user_login_data.sql`
- `034_add_missing_fk_constraints.sql` AND `034_prevent_kill_tier_update_trigger.sql`
- `070_add_shopify_pull_columns_to_skus.sql` AND `070_add_vector_retry_queue.sql`

When mounted into `docker-entrypoint-initdb.d` in alphabetical order, duplicate-numbered files will execute in filesystem-sorted order (OS-dependent on ties). The dependency between them is unspecified.

**Key schema evolution events:**
- Migration 037: Added `ai_answer_block VARCHAR(300)` and `expert_authority TEXT` to skus (these are core gate fields added late)
- Migration 040: Seeded `business_rules` table with 52 rules (actually 53 are inserted — 9 tier + 7 chs + 9 cis + 8 decay + 3 effort + 6 readiness + 11 gates + 5 sync = 58 rows, not 52. The header comment claims "exactly 52 rules" but the actual INSERT contains more)
- Migration 043: Updated `intent_taxonomy.tier_access` for `specification` to include `harvest` — a data migration run after initial seed
- Migration 080: Altered `skus.ai_answer_block` to `VARCHAR(300)` — this was already VARCHAR(300) in migration 037. Migration 080 is redundant but safe.

**`prevent_kill_tier_update` trigger** (migration 034):
```sql
IF (OLD.tier = 'KILL' OR OLD.tier = 'kill') THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Kill-tier SKUs are locked from updates';
END IF;
```
This blocks ALL updates to kill-tier rows, including updates to non-content fields like `last_validated_at`, `can_publish`, `ai_validation_pending`. GateValidator.php at line 167 explicitly skips `$sku->update()` for kill-tier SKUs to avoid this trigger. However, if any other code path tries to update a kill-tier SKU (e.g. ERP sync, cron job, tier recalculation), it will receive a SQLSTATE 45000 exception.

### Seeds

**`007_seed_canonical_cie.sql`**: Seeds 9 intent taxonomy rows with `INSERT IGNORE`. Notably the GAP_LOG comment at line 14 documents a conflict between `ENF§8.3` taxonomy keys and `openapi.yaml SkuValidateRequest` enum values — specifically the "regulatory" vs "safety_compliance" naming. The seed follows the openapi/CLAUDE.md alignment but the spec conflict is unresolved.

**`006_seed_dummy_skus.sql`**: Creates 7 clusters in `cluster_master` and `clusters` tables, then inserts 9 Hero/Support/Harvest SKUs plus 1 Kill SKU. The INSERT uses user-defined variables (`@clu_cbl_pe27` etc.) for cluster ID resolution — this pattern works only in a single session and may fail in certain MySQL connection modes.

**`008_seed_golden_sku_content.sql`**: Not read in full but referenced throughout the codebase as the canonical source of golden test content.

### Schema Design

**`skus` table** (`004_create_skus_table.sql`):
- `best_for TEXT` and `not_for TEXT` — stored as TEXT but parsed as JSON arrays by application code. No JSON column type or constraint enforces this. Invalid non-JSON values would pass database constraints but fail gate validation.
- `faq_data JSON` — correctly uses MySQL JSON type.
- `tier ENUM('hero','support','harvest','kill')` — lowercase values. The PHP enum TierType uses lowercase values, but the prevent_kill_tier_update trigger checks both `'KILL'` and `'kill'`. If the ENUM allows only lowercase, the uppercase check is dead code in the trigger.
- `ai_answer_block VARCHAR(300)` — added in migration 037 then confirmed in 080. The G4 gate requires 250–300 chars. VARCHAR(300) exactly meets the maximum, with zero headroom for gate configuration changes.
- No `ai_answer_block` column existed in `004_create_skus_table.sql` — it was added later. This means SKUs created before migration 037 had no answer block column until the migration ran. The SkuController.php `store()` method at line 327 intersects payload keys with actual DB columns using `Schema::getColumnListing()` to handle this gracefully.

**`audit_log` table** (`011_create_audit_log_table.sql`):
- Original `entity_id CHAR(36)` (UUID format)
- Migration 026 alters it to `VARCHAR(50)` — this was done to accommodate sku_code values (e.g. `CBL-BLK-3C-1M` = 13 chars) as entity identifiers
- `actor_id` and `actor_role` added in migration 026 — not present in original table definition
- `timestamp` column does not appear in migration 011, yet PHP code references it throughout. Migration 026 adds `changed_at` but not `timestamp`. The AuditLogController at line 22 checks `Schema::hasColumn('audit_log', 'timestamp')` defensively. The actual column addition for `timestamp` must be in another migration not reviewed.

**`vector_retry_queue` table**: Created in migration 070 (`070_add_vector_retry_queue.sql`). Table structure not reviewed in full but referenced in `G4_VectorGate.php`, `gates_validate.py`, and `vector_retry_queue.py`.

**`sku_gate_status` table**: Referenced extensively but not in the initial migrations list. Created in a migration between 020 and 027. The `sku_id` column stores the business SKU code (VARCHAR), not the UUID PK — confirmed in GateValidator.php line 73.

---

## 6. Frontend Audit

### Pages (`frontend/src/pages/`)

20 pages found:
`AiAudit`, `AuditTrail`, `Briefs`, `BulkOps`, `BusinessRules`, `Channels`, `ClustersPage`, `Config`, `Dashboard`, `ErpSync`, `Help`, `Maturity`, `SemrushImport`, `ShopifyPull`, `SkuEdit`, `StaffKpis`, `TierMgmt`, `WriterEdit`, `WriterQueue`, `review/`

**WriterQueue.jsx** — defensive normalization of API responses (`normalizeQueueItem()` at line 41). Handles multiple possible field names for the same concept (e.g. `item?.fields_total ?? item?.total_fields ?? item?.required_fields`). This suggests the API response has changed shape over time without clean versioning.

**WriterEdit.jsx** — loads config from `configApi.get()` on mount to get field limits. Uses `TIER_FIELD_MAP` from `tierFieldMap.js` to control field visibility. Contains `SUGGESTION_CARD_TYPE_META` for rendering Semrush keyword suggestions, AI visibility issues, trends, and competitor gaps. The `resolveSuggestionType()` function at line 69 has a fuzzy type-resolution fallback using string matching on `source_label`, `title`, and `body` fields — this is fragile.

**SkuEdit.jsx** — uses `canEditContentFieldsForTier`, `canEditExpertAuthority`, `canAssignCluster`, `canPublishSku` from `rbac.js`. Loads config for answer_block min/max and title_max_length. Fetches these via `configApi.get()` on mount — if config API fails, `answerBlockMin` and `answerBlockMax` remain null, meaning the character counter in the UI cannot validate.

**AuditTrail.jsx** — correctly uses `auditLogApi.getFilters()` and `auditLogApi.getLogs()`. Filters by `sku`, `user`, `action` params. No date range filter despite the potential for very large audit logs.

### Components

**UIComponents.jsx** (`frontend/src/components/common/UIComponents.jsx`):
- `GateChip` renders `id` prop in a `data-field-label` attribute but the `id` prop is not displayed in the rendered output — only `label` is shown. The `id` prop is accepted but unused.
- `ReadinessBar` requires `greenThreshold` and `amberThreshold` props for colour-coded rendering. If these are null (before config loads), the bar renders in `var(--text-muted)` grey — acceptable fallback.
- No `RoleBadge` prop-types or TypeScript types — role validation relies entirely on runtime string matching.

### Services / API Calls (`frontend/src/services/api.js`)

**`publishSku()` function (lines 63–71):**
```javascript
export async function publishSku(skuId, contentPayload) {
    await api.post(`/v1/sku/${skuId}/validate`, {...});
    await api.put(`/v1/sku/${skuId}/content`, contentPayload);
    return { ok: true };
}
```
The validation response from line 64 is `await`-ed but its result is discarded. Even if validation returns a `400 fail` response, axios will throw (because axios throws on 4xx by default), so the PUT on line 69 is only reached if validate returns 200. However, a `pending` status returns 200, meaning a SKU in degraded mode can proceed to content update. This may be intentional (save allowed but publish blocked) but is not obviously correct — the flow should check `publish_allowed` before calling PUT content.

**Token storage**: All API calls use `sessionStorage.getItem('cie_token')` (line 14). Session storage is cleared when the tab is closed but persists across page refreshes within the tab. JWT tokens stored in sessionStorage are accessible to any JavaScript on the page (XSS risk if third-party scripts are injected), though this is a standard trade-off for SPAs.

**`writerEditApi.publish` (line 55)**: Maps to `PUT /v1/sku/{skuId}/content` — this is a content update, not a publish action. The actual publish endpoint `POST /v1/sku/{id}/publish` is separately defined in `skuApi.publish` at line 83. The naming `writerEditApi.publish` is confusing.

**`skuApi.update` (line 78)**: Calls `PUT /v1/sku/{id}/content` which routes to `SkuController::updateContent` which delegates to `SkuController::update`. The `update` endpoint accepts `PUT /{id}/content` but the PHP controller's `update()` method also allows `PUT /{id}` (not in the routes). Only the content variant is exposed externally.

### `tierFieldMap.js`

The `TIER_BANNER_COPY` for Harvest (line 13) reads:
```
'HARVEST — Basic info only. One field to fill. Guide: ~10 min'
```
This is inconsistent with the PHP backend `getTierBanner()` which returns a much longer Harvest banner. The frontend and backend banner copies for Harvest diverge, meaning the displayed banner text depends on which layer is rendering it.

---

## 7. Tests Audit

### PHP Tests (`tests/php/`)

Found in `tests/php/Phase0/`:
- `AuditLogImmutabilityTest.php`
- `BusinessRulesTest.php`
- `RBACTest.php`
- `GateValidationTest.php`
- `TierEngineTest.php`
- `VectorFailSoftTest.php`

Found in `tests/php/Phase1/` (contents not read).

The tests use PHPUnit (^10.0) and Mockery (^1.5) per composer.json. Test files exist for the core validation pipeline and RBAC. The `composer.json` test script (line 31) runs both PHPUnit and the golden SKU validation script: `"test": "phpunit && php ../validate_golden_skus.php"`.

The golden validation PHP script (`validate_golden_skus.php`) at the project root is a standalone PHP script separate from the PHPUnit suite.

### Python Tests (`tests/python/`)

Only one test file found: `test_golden_g4_g5.py`.

**`test_golden_g4_g5.py`:**
- Two tests: `test_golden_shd_gls_cne_20_g4_fail` and `test_golden_blb_led_b22_8w_g5_fail`
- Both mock `BusinessRules` via `monkeypatch` — correct approach for unit isolation
- Coverage is extremely narrow: 2 tests covering 2 edge cases (G4 char limit, G5 not_for count). No tests for G1, G2, G3, G6, G7, vector gate, or any positive (pass) path
- No integration tests for the FastAPI endpoints
- No tests for `vector_retry_queue.py`
- No tests for `gates_validate.run_all_gates()`

### Test Gaps
- No end-to-end tests exist for the full publish flow (validate → update → publish → baseline → channel deploy)
- No tests for the tier change request/approval workflow
- No tests for the audit log immutability (SQL trigger)
- No tests for ERP sync
- No tests for Semrush import
- No tests for decay/escalation logic
- The `integration-test.sh` at the project root was not audited but exists

---

## 8. Infrastructure & Config

### Docker Compose (`docker-compose.yml`)

- **MySQL root password**: `root_password` (line 6) — plaintext in compose file. Acceptable for local dev but must not be used in production.
- **MySQL user password**: `cie_password` (line 8) — plaintext in compose file.
- **PHP API port**: External `8080` maps to internal `8000`
- **Python Worker port**: External `8000` maps to internal `8000` — conflict risk if both services run on the same host without Docker networking
- **nginx**: Serves the Vite `frontend/dist` static build. No SSL termination configured in this compose file.
- **No health checks defined** for any service — Docker will not wait for MySQL to be ready before starting PHP/Python services
- **Volume mounts**: `./backend/php:/app` (PHP) and `./backend/python:/app` (Python) — full directory mounts in development, which means any file created in the container appears on the host

### Environment Variables
- `APP_DEBUG=true` is set in docker-compose (line 40). Must be `false` in production.
- `OPENAI_API_KEY` and `ANTHROPIC_API_KEY` are passed via `${OPENAI_API_KEY}` from the shell (line 59–60) — correct approach for secrets.
- `CIE_ENGINE_TOKEN` (referenced in api.php route closure) is not set in docker-compose.
- Python worker uses both `DB_USERNAME` (set in compose line 57) but the Python code reads `DB_USER` (gates_validate.py line 155). The compose sets `DB_USERNAME` not `DB_USER` — Python database connections will fall back to `root` in the default.

### Missing Configuration
- No `.env.example` file was found in the project root or backend directories (searched via ls)
- No nginx.conf content reviewed (referenced in docker-compose but at `./infrastructure/docker/nginx.conf`)
- No Dockerfile content reviewed (referenced but not available at searched paths)

---

## 9. Security Findings

### HIGH

**S-H1: Plaintext credentials in docker-compose.yml**
`MYSQL_ROOT_PASSWORD: root_password`, `MYSQL_PASSWORD: cie_password` (lines 6, 8). These should use Docker secrets or environment variable substitution like the OPENAI_API_KEY approach already used.

**S-H2: `APP_DEBUG=true` in docker-compose**
Line 40: `APP_DEBUG=true`. If this compose file is used as the basis for a staging/production deployment, detailed stack traces will be exposed to API clients on any exception. Laravel debug mode also enables the Telescope debug bar and exposes environment variables.

**S-H3: Python DB env var mismatch**
`docker-compose.yml` sets `DB_USERNAME=cie_user` (line 57) but `gates_validate.py` reads `DB_USER` (line 155). Python connects as `root` in any deployment using this compose file, with full database privileges.

**S-H4: No rate limiting on validation endpoints**
`POST /sku/{id}/validate` and `POST /api/v1/sku/validate` have no rate limiting. Each validation creates up to 24 DB writes (8 gates × 3 writes each). A malicious authenticated user could trigger a large number of validation requests to degrade the database.

### MEDIUM

**S-M1: JWT in sessionStorage**
`sessionStorage.getItem('cie_token')` (api.js line 14). JWTs in storage are vulnerable to XSS attacks. `HttpOnly` cookies would be preferable.

**S-M2: Missing RBAC on create and view SKU routes**
`POST /v1/sku` (create) and `GET /v1/sku/{id}` (view) have only `auth` middleware, not `rbac:` middleware. Any authenticated user (including low-privilege roles) can create SKUs or view full SKU details.

**S-M3: `publishSku()` client-side validation bypass**
`frontend/src/services/api.js` lines 63–71: the function awaits validation but does not inspect the response body. A `200 pending` response (degraded mode) proceeds to the content update. The server-side `SkuController::update()` independently re-validates, so the server is not compromised, but the client-side flow can misrepresent the publish outcome.

**S-M4: Inline closure in route file exposes token logic**
`api.php` lines 121–129: the suggestion status proxy is an inline closure calling `env('CIE_ENGINE_TOKEN')`. If `APP_DEBUG=true` and an exception occurs in this closure, the token value may appear in stack traces.

**S-M5: `audit_log` has no DELETE/UPDATE revoke enforced at application layer**
The AuditTrail page comment says "REVOKE UPDATE/DELETE enforced at database level" but no `REVOKE` SQL statement exists in any migration. The audit log relies on the absence of any application code that updates/deletes it, not an enforced database privilege restriction. A developer with DB access can still modify audit log records.

### LOW

**S-L1: Python worker opens DB connections per request**
`_get_db()` in gates_validate.py and vector_retry_queue.py open a new pymysql connection on each call. There is no connection pooling. Under concurrent load, this will exhaust the MySQL connection limit.

**S-L2: Regex anchor in G7 specificity check**
The `expertAuthorityMatchesPythonSpecificity()` regex in G7ExpertGate.php (line 20) has no anchors and matches substrings. A value containing `CE` (e.g. "Accepted by all retailers") technically contains the letters C and E but the pattern `\bCE\b` uses word boundaries — this is acceptable. However `\bATEX\b`, `\bRoHS\b`, `\bREACH\b`, `\bUKCA\b` are legitimate marks but very product-category specific. Products outside the lighting/electrical domain may find it impossible to satisfy G7 without category-specific updates to this regex.

---

## 10. Code Quality Findings

### CQ-1: Dead code in multiple gate files
- `G2_IntentGate.php`: `intentTitleKeyword()` method (lines 96–111) is defined but never called
- `G5_TechnicalGate.php`: `validateUnits()` method (lines 58–71) is defined but never called. Both should be removed to reduce maintenance surface.

### CQ-2: Gate name/code inconsistency
`G5_TechnicalGate.php` uses `GateType::G5_BEST_NOT_FOR` (not `G5_TECHNICAL`). The `GateType::G5_TECHNICAL` enum case exists (GateType.php line 12) but is produced by no gate class. The filename misleads developers about the gate's purpose.

### CQ-3: Double validation in SkuController::update()
Lines 250 and 263 both call `$this->validationService->validate()`. The first call validates before any data changes (against current DB state, not draft), and the second is conditional. Then line 306 validates again after the save. This is up to 3 validation passes per save, each creating N database writes.

### CQ-4: Golden content in three locations
The same SKU content data appears in `database/seeds/008_seed_golden_sku_content.sql`, `database/seeds/006_seed_dummy_skus.sql`, and `RefreshGoldenGateStatusCommand.php::goldenContentUpdates()`. These must be kept in sync manually.

### CQ-5: `$quorumPause` hard-coded constant
`ValidationService.php` line 297: `$quorumPause = 2` with comment "§5.3: not in 52 rules; hard-coded". This is a business logic value that should be in `business_rules`.

### CQ-6: Python BusinessRules cache has no TTL
`BusinessRules._cache` in gates_validate.py is populated once and never refreshed. Admin business rule changes require a Python worker restart to take effect.

### CQ-7: `psycopg2-binary` unused in requirements.txt
`psycopg2-binary==2.9.9` (requirements.txt line 45) is a PostgreSQL driver. The project uses MySQL. This should be removed to reduce the dependency footprint.

### CQ-8: Tier banner copy divergence
PHP `SkuController::getTierBanner()` (lines 157–172) and frontend `tierFieldMap.js` `TIER_BANNER_COPY` (lines 10–15) have different copy for all four tiers. The Harvest banner differs most significantly — PHP gives a full instructional message while the frontend gives "HARVEST — Basic info only. One field to fill. Guide: ~10 min". Content writers will see different messages depending on which surface renders the banner.

### CQ-9: `publishSku()` in api.js ignores validate response
As noted in Section 9, the client-side `publishSku()` ignores the validation JSON body. The `publish_allowed` field in the response is never checked client-side.

### CQ-10: No pagination in audit log API
`AuditLogController::index()` returns up to 500 results (line 50) with no pagination cursor. A table with millions of audit entries will produce large responses and slow queries.

### CQ-11: Duplicate ERP sync routes
`POST /api/admin/erp-sync` (api.php line 28) and `POST /api/v1/erp/sync` (line 90) both call `TierController::erpSync`. Two different URLs with different RBAC applied (line 28: `rbac:ADMIN`, line 90: `rbac:ADMIN`). Both are admin-only but the duplication is unnecessary.

### CQ-12: PHP `SkuController` line count
At 1019 lines, `SkuController.php` is a very large controller. Many private helper methods (`buildGateStatuses`, `buildGateStatusesFallback`, `computeFieldProgress`, `tierFieldsComplete`, `fallbackChannelReadiness`) belong in a separate service or view model.

### CQ-13: Missing `content.title_max_length` in business_rules seed
`SkuEdit.jsx` at line 61 reads `content.title_max_length` from the config API. The `business_rules` seed in migration 040 does not contain a `content.title_max_length` key. The `ConfigController` must be sourcing this from elsewhere or returning null. If null, `titleMaxLength` in the frontend stays null and no max-length validation is applied.

---

## 11. Bugs & Issues Found

### B-1: `sku_content` table does not exist (HIGH)
`vector_retry_queue.py` line 163:
```python
cursor.execute("UPDATE sku_content SET vector_similarity = %s WHERE sku_id = %s", (similarity, sku_id))
```
No migration creates a `sku_content` table. This UPDATE silently updates 0 rows on every retry resolution. Vector similarity scores from retries are never persisted anywhere.

### B-2: Python DB connection env var mismatch (HIGH)
`docker-compose.yml` sets `DB_USERNAME=cie_user` but `gates_validate.py` reads `os.environ.get("DB_USER", "root")`. Python always connects as root when using the compose file.

### B-3: PythonWorkerClient calls non-existent endpoints (MEDIUM)
`/queue/audit`, `/queue/brief-generation`, `/audits/{id}`, `/health` are called by PHP but not implemented in Python. Brief generation and audit queue calls will silently fail with `queued: false` responses.

### B-4: Pre-save validation uses pre-update state (MEDIUM)
`SkuController::update()` calls `$this->validationService->validate($sku->fresh(), false)` at line 250 before applying `$updateData`. Gates are validated against the current DB state, not the incoming payload. A content update that would fix a gate failure is not tested before saving.

### B-5: G2 intent gate dead code (LOW)
`G2_IntentGate.php::intentTitleKeyword()` is never called. Its presence could confuse developers into thinking title keyword checks are active in G2 when they are not (they were explicitly removed per spec comment on line 85).

### B-6: G5 filename vs gate code mismatch (LOW)
`G5_TechnicalGate.php` emits `GateType::G5_BEST_NOT_FOR`. The `GateType::G5_TECHNICAL` case in GateType.php is orphaned.

### B-7: Harvest banner copy divergence frontend vs backend (LOW)
PHP `getTierBanner('HARVEST')` returns a 3-sentence instructional message. Frontend `TIER_BANNER_COPY.harvest` returns a single short line. Content writers see inconsistent guidance.

### B-8: Migration numbering collisions (LOW)
Four pairs of duplicate migration numbers (011, 031, 034, 070) create ambiguous execution order in `docker-entrypoint-initdb.d`.

### B-9: `G6_CommercialPolicyGate` content mutation detection bypassed without draft keys (MEDIUM)
When `validate()` is called directly without going through `validateSku()`, `app('cie.validation_draft_keys')` is not set. The `killDraftAttemptsContentMutation()` check at line 19 receives an empty `$draftKeys` array and always returns `false`. Kill-tier edit blocking in G6.1 only works when called through the `validateSku()` path.

### B-10: `troubleshooting` intent keyword stem too short (LOW)
`G4_AnswerBlockGate::getStemmedKeywordOrList()` maps `troubleshooting` to stem `'shoot'` (line 142). Words like "shoot", "photoshoot", "overshoot" all match. Should be `'troubleshoot'`.

### B-11: `canPublish` true even with vector warn status (MEDIUM)
In `GateValidator::validateAll()`, lines 125–136: when `$hasVectorWarn && !$blockingFailure`, status is set to `VALID` and `canPublish = false`. But then line 157 sets `updateData['can_publish'] = $canPublish` which persists `false` to the database for a VALID SKU. A VALID SKU with `can_publish = false` will fail the `SkuController::publish()` check at line 372 (`$canPublish = $validation['can_publish'] ?? false`). The SKU appears VALID in the portfolio but cannot be published. This may be intentional but the user sees "VALID" status with no publish button — confusing UX.

---

## 12. Missing Features / Gaps

### MF-1: No pagination in SKU list endpoint
`GET /v1/sku` returns all SKUs via `Sku::with(...)->get()` (SkuController line 78). There is no `LIMIT`/`OFFSET` or cursor pagination. With large catalogues this will OOM the PHP process.

### MF-2: No audit log date range filter
`AuditLogController::index()` accepts `entity_type`, `entity_id`, `user`, `sku`, `action` filters but no `from_date`/`to_date` filters. Compliance reviews typically require date-bounded audit exports.

### MF-3: No webhook retry mechanism for channel deploys
`ChannelDeployService::deployToShopify()` and `deployToGMC()` are N8N webhook calls. If N8N is unavailable or returns an error, the publish fails but there is no retry queue equivalent to the vector retry queue.

### MF-4: Cluster centroid initialization not documented
The similarity endpoint returns `pending` when the cluster centroid is not in Redis. No endpoint or cron job for loading cluster centroids into Redis is visible in the audited Python API. The `cluster_cache` module is imported but its initialization mechanism is unclear.

### MF-5: `quorum_pause` threshold not in business_rules
`ValidationService.php` line 297 hard-codes `quorum_pause = 2`. This should be in `business_rules` as `decay.quorum_pause` or equivalent.

### MF-6: No integration test for end-to-end publish flow
The test suite has no integration test covering: create SKU → assign cluster → assign intent → validate → update content → publish → check baseline.

### MF-7: `UserController` and `TitleController` are unreachable
Both controllers exist in `src/Controllers/` but no routes in `api.php` map to them. Their functionality is inaccessible via the API.

### MF-8: No `content.title_max_length` business rule
`SkuEdit.jsx` reads this key from config, but it is not seeded in migration 040. If `ConfigController` reads from `business_rules`, the value will always be null.

### MF-9: `notify_content_owner()` in vector_retry_queue.py is a log-only stub
Line 72 in `vector_retry_queue.py`: `notify_content_owner()` only calls `logger.warning()`. No email, Slack, or notification is sent. Content owners are never actually notified of vector retry failures.

### MF-10: PHP `PythonWorkerClient` audit/brief queue endpoints unimplemented
As noted in B-3. The brief generation queue (`queueBriefGeneration()`) and audit queue (`queueAudit()`) from PHP have no Python handler. `BriefController` can queue but the queue never processes.

---

## 13. Recommendations

### Priority: HIGH

**R-H1: Fix Python DB connection environment variable**
Change `docker-compose.yml` to use `DB_USER=cie_user` (matching what `gates_validate.py` reads) OR update all Python code to read `DB_USERNAME`. Currently Python always connects as root. Target files: `docker-compose.yml` line 57, `gates_validate.py` line 155, `vector_retry_queue.py` line 22.

**R-H2: Investigate and create the `sku_content` table or fix the UPDATE statement**
`vector_retry_queue.py` line 163 writes to `sku_content` which does not exist. Either create the table (with at least `sku_id`, `vector_similarity` columns) and a migration, or remove the UPDATE and write the similarity score to `sku_gate_status.error_message` or a dedicated column. Until this is fixed, retry-resolved vector similarity scores are silently lost.

**R-H3: Implement the missing Python queue endpoints**
`POST /queue/audit`, `POST /queue/brief-generation`, `GET /audits/{id}`, `GET /health` are called by PHP but not implemented. Implement stub endpoints that accept the payloads and either process or queue them. The `GET /health` endpoint is the most critical — PHP health checks always return false.

**R-H4: Add SKU list pagination**
`SkuController::index()` loads all SKUs without pagination. Add `?page=` and `?per_page=` parameters with a reasonable default (e.g. 50) and maximum (e.g. 200). Use Eloquent's `paginate()` method.

**R-H5: Move `quorum_pause` to business_rules**
Remove the hard-coded `$quorumPause = 2` in `ValidationService.php` line 297 and add `decay.quorum_pause` to the business_rules seed in migration 040. This is a minor change but resolves a documented spec violation.

**R-H6: Add Python BusinessRules cache invalidation**
When admin updates a business rule via `PUT /v1/admin/business-rules/{key}`, the PHP `BusinessRulesService` should also call the Python worker health/reload endpoint (or the Python worker should periodically re-read rules). Without this, business rule changes don't take effect in the Python gate pipeline until the worker restarts.

**R-H7: Fix docker-compose to use non-root DB credentials for Python**
Either change `DB_USERNAME` to `DB_USER` in the compose file or add both `DB_USER` and `DB_USERNAME` so both PHP and Python pick up their respective env vars correctly.

### Priority: MEDIUM

**R-M1: Fix pre-save validation in SkuController::update()**
The first validation at line 250 validates against pre-update state. Apply `$updateData` to an in-memory copy of the SKU model before validating, so gates see the intended new values. This prevents saving data that fixes a gate failure without triggering the correct gate pass.

**R-M2: Resolve G5_TechnicalGate naming**
Rename `G5_TechnicalGate.php` to `G5_BestNotForGate.php` to match its actual function and the gate code `G5_BEST_NOT_FOR`. Remove the dead `validateUnits()` method. Remove the orphaned `G5_TECHNICAL` case from `GateType.php` or use it.

**R-M3: Remove dead code from G2 and G5 gates**
Delete `intentTitleKeyword()` from `G2_IntentGate.php` (lines 96–111). Delete `validateUnits()` from `G5_TechnicalGate.php` (lines 58–71).

**R-M4: Fix `troubleshooting` intent keyword stem**
Change `'troubleshooting' => 'shoot'` to `'troubleshooting' => 'troubleshoot'` in `G4_AnswerBlockGate::getStemmedKeywordOrList()` (line 142) and in `INTENT_KEYWORDS` dict in `gates_validate.py` (currently missing troubleshooting entirely — `'troubleshooting'` is not in the Python INTENT_KEYWORDS dict, only `'problem_solving'`).

**R-M5: Align tier banner copy between PHP and frontend**
Audit all four tier banners in `SkuController::getTierBanner()` and `tierFieldMap.js::TIER_BANNER_COPY`. Establish a single canonical source (recommend the PHP backend) and have the frontend fetch it from `GET /v1/config` rather than maintaining its own copy.

**R-M6: Rename duplicate migration files**
Renumber the duplicate-prefix migrations to ensure unambiguous execution order:
- `011_create_cluster_vectors_table.sql` → `011b_create_cluster_vectors_table.sql`
- `031_scrub_user_login_data.sql` → `031b_scrub_user_login_data.sql`
- `034_prevent_kill_tier_update_trigger.sql` → `034b_prevent_kill_tier_update_trigger.sql`
- `070_add_vector_retry_queue.sql` → `070b_add_vector_retry_queue.sql`

**R-M7: Add RBAC to SKU create and view routes**
`POST /v1/sku` should require `rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,ADMIN` at minimum. `GET /v1/sku/{id}` can remain open to all authenticated users if read-only access is intended, but this should be an explicit design decision documented in the route file.

**R-M8: Add audit log date range filter**
Add `from_date` and `to_date` query parameters to `AuditLogController::index()` to support time-bounded compliance queries.

**R-M9: Implement actual content owner notification**
Replace the `notify_content_owner()` stub in `vector_retry_queue.py` with a real notification. This could POST to the PHP notification endpoint, send an email via a configured mail service, or write to a `notifications` table. Until this is implemented, content owners are never informed when their SKU's description fails vector validation after all retries are exhausted.

**R-M10: Add `content.title_max_length` to business_rules seed**
Add a row to migration 040 or a new migration:
```sql
(UUID(), 'content.title_max_length', '255', 'integer', 'Maximum SKU title length')
```
Update `ConfigController` to include this key in its response.

### Priority: LOW

**R-L1: Remove unused `psycopg2-binary` from requirements.txt**
PostgreSQL driver has no role in this MySQL-based project. Removing it reduces Docker image size and dependency surface.

**R-L2: Add Docker healthchecks to compose file**
Add MySQL and Redis healthchecks so PHP and Python services wait for their dependencies before starting:
```yaml
healthcheck:
  test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
  interval: 5s
  timeout: 3s
  retries: 10
```

**R-L3: Set `APP_DEBUG=false` in docker-compose or document environment-specific overrides**
Create a `docker-compose.override.yml` for development (debug on) and ensure the base `docker-compose.yml` has `APP_DEBUG=false`.

**R-L4: Replace inline closure in api.php suggestion proxy**
Extract the suggestion status proxy (lines 121–129 of api.php) to a proper controller method (e.g. `SuggestionController::status()`). This improves testability and removes `env()` calls from route definitions.

**R-L5: Add database connection pooling for Python**
Replace per-request `_get_db()` connections with a connection pool (e.g. using `SQLAlchemy` with `pool_size=5`) or a singleton connection with reconnection logic. Under concurrent load the current approach will exhaust MySQL's `max_connections`.

**R-L6: Increase `vector_retry_queue` backoff cap to achieve 30-minute SLA**
Current cap of 20 minutes with 5 retries gives max ~75-minute window. To meet the documented KPI of ">95% resolved ≤30 min", either reduce the backoff intervals or increase max_retries to allow faster resolution windows. Alternatively, add an immediate first retry (0-minute delay) so the first attempt happens promptly.

**R-L7: Consolidate golden SKU content to a single source**
Remove the `goldenContentUpdates()` array from `RefreshGoldenGateStatusCommand.php` and have the command execute the SQL seed files directly (`008_seed_golden_sku_content.sql`). This eliminates the three-way synchronisation requirement.

**R-L8: Add SKU list pagination to frontend**
`WriterQueue` and `Portfolio` pages will load all SKUs into memory once the catalogue grows. Add pagination controls that pass `page` and `per_page` to `GET /v1/sku`.

**R-L9: Document cluster centroid initialization process**
The README or QUICK_START_GUIDE.md should document how to populate Redis with cluster centroids (likely via an artisan command or Python script). Without this, any fresh deployment will have all vector checks returning `pending`.

**R-L10: Extend G7 specificity regex for non-electrical product categories**
The current regex in `G7_ExpertGate.php` line 20 is heavily biased toward electrical standards (BS, EN, IEC, CE, UKCA). If the CIE platform is extended to non-electrical products, G7 will reject all expert authority statements. Make the pattern configurable via `business_rules` or extend it with chemical, structural, or biological standards as needed.

---

*End of Audit Report — CIE v2.3.2 — 2026-03-20*
