# CIE v2.3.2 — Project Audit Report
**Date:** 2026-03-30 | **Branch:** main | **Migrations:** 001–144

---

## 1. DATABASE LAYER

### 1.1 Migration Count
- **144 numbered migrations** (some duplicated numbers indicate corrections/replacements)
- 2 unnumbered hardening patches: `hardening_schema_patch.sql`, `safe_hardening_patch.sql`

### 1.2 Core Tables (by migration group)

| # Range | Tables Created / Altered | Notes |
|---|---|---|
| 001–018 | `users`, `roles`, `clusters`, `skus`, `intents`, `sku_intents`, `audit_results`, `content_briefs`, `validation_logs`, `tier_history`, `audit_log`, `cluster_vectors`, `sku_vectors`, `channel_mappings`, `erp_sync_log`, `staff_effort_logs`, `approval_requests`, `executive_kpis` | Foundation schema |
| 019–026 | Add `lock_version` to skus, `ai_validation_pending`, `tier_access` to intents, `category` to clusters, `ai_audit_runs`, `ai_golden_queries`, `ai_audit_results`; audit_log canonical form | AI audit tables |
| 027–042 | v2.3.2 patch tables (`faq_templates`, `sku_faq_responses`), intent type fixes, `validation_retry_queue`, missing FK constraints, immutability triggers, `weekly_scores`, `business_rules` | Business rules + immutability |
| 043–061 | Harvest tier intents, user seeding, `semrush_imports` (created + dropped + re-created), `gsc_baselines`, `ga4_landing_performance`, `url_performance`, validation log enhancements, UTC enforcement | Semrush + analytics baseline |
| 062–080 | Remove Amazon from channel enum, rename `sku_faqs→sku_faq_responses`, Semrush column additions, `tier_change_requests`, AI audit cron, vector retry queue, gate type business rules, `ai_audit_runs` degraded mode, FAQ tables | Tier changes + channels |
| 082–105 | Intent taxonomy enforcement + fixes, FK enforcement, readiness scoring, `sku_commercial`, `sku_readiness`, tier history fixes, brief status fixes | Schema hardening |
| 106–144 | Missing SKU columns, intent taxonomy labels, URL performance constraints, `ai_agent_logs`, golden queries seed (cables), ERP fields, `sync_status_table`, `ai_agent_logs` prompt/response columns, `decay_notifications`, `revenue_risk` fields on audit runs, readiness component weights | Final hardening + AI agent |

### 1.3 All Tables (canonical set)

| Table | Description |
|---|---|
| `users` | System users — writer, reviewer, admin |
| `roles` | 8-role RBAC hierarchy |
| `clusters` | Semantic intent clusters (Cluster_ID → intent grouping) |
| `skus` / `sku_master` | Main product catalog — titles, descriptions, tier, cluster assignment (note: schema split `sku_master` + `sku_commercial` + `sku_readiness` exists in later migrations; PHP uses legacy `skus` — see GAP-ERP-03) |
| `sku_commercial` | ERP-sourced commercial data: margin, velocity, CPPC, tier score |
| `sku_readiness` | Per-channel readiness scores (0–100) |
| `intent_taxonomy` | Locked 9-intent taxonomy with `is_active` flag |
| `sku_intents` | Junction: SKU → primary + secondary intents |
| `audit_log` | **Immutable** event log (UPDATE/DELETE blocked by DB trigger) |
| `validation_logs` | Per-gate validation results with gate codes, pass/fail, user_id |
| `validation_retry_queue` | Queue for failed gate validations to retry |
| `audit_results` / `ai_audit_results` | AI engine citation scores (0–3 scale, 4 engines) |
| `ai_audit_runs` | Weekly audit run metadata — category, date, degraded_mode, run_status, revenue_risk |
| `ai_golden_queries` | 20 golden questions per product category |
| `ai_agent_logs` | AI suggestion request/response log with prompt + response columns |
| `content_briefs` | Generated content briefs per SKU |
| `tier_history` / `sku_tier_history` | Tier change history (name discrepancy — see GAP-P3R2-1) |
| `tier_change_requests` | Workflow table for admin-approved tier overrides |
| `channel_mappings` | Channel-specific configuration per SKU |
| `erp_sync_log` | ERP sync job history |
| `staff_effort_logs` | Writer effort tracking |
| `approval_requests` | Legacy approval workflow (pre-DECISION-002; still in schema) |
| `executive_kpis` | KPI snapshots for reviewer dashboard |
| `weekly_scores` | Week-over-week audit scores per category |
| `business_rules` | Dynamic configurable rules (key/value + audit trail) |
| `semrush_imports` | Semrush CSV upload rows — keyword, volume, difficulty, position, cluster_id, import_batch_id |
| `semrush_content_snapshots` | Baseline keyword positions captured at publish time |
| `gsc_baselines` | Google Search Console baseline metrics — impressions, clicks, CTR, position, cis_status |
| `ga4_landing_performance` | GA4 sessions, bounce_rate, conversions per SKU/week |
| `url_performance` | SEO performance (impressions/clicks/CTR/position) per URL/week |
| `gsc_weekly_performance` | Weekly GSC aggregates |
| `gsc_unmatched_urls` | GSC URLs not matched to a SKU |
| `cluster_vectors` | Cluster-level embedding vectors (Redis cache source) |
| `sku_vectors` | Per-SKU embedding vectors |
| `faq_templates` | FAQ question templates per product class |
| `sku_faq_responses` | Writer-authored FAQ answers per SKU |
| `sync_status_table` | Sync job status tracker |

### 1.4 Seed Files

| File | Content |
|---|---|
| `001_seed_intents.sql` | Intent taxonomy (9 intents) |
| `002_seed_roles.sql` | RBAC roles |
| `002_seed_tiers.sql` | Tier definitions |
| `004_seed_test_users.sql` | Test user accounts |
| `005_seed_golden_test_data.sql` | Golden SKU test fixtures |
| `006_seed_dummy_skus.sql` | Sample SKU data |
| `007_seed_canonical_cie.sql` | Canonical CIE base data |
| `007_seed_golden_sku_intents.sql` | Golden SKU → intent mappings |
| `008_seed_golden_sku_content.sql` | Golden SKU content fields |
| `009_seed_golden_sku_readiness.sql` | Golden SKU readiness scores |
| `golden_queries/cables_v1.0.json` | 20 golden audit questions for "cables" category |

---

## 2. BACKEND — PHP (Laravel)

### 2.1 Controllers (23)

| Controller | Routes Served | Status |
|---|---|---|
| `AuthController` | POST /auth/login, /auth/register | Done |
| `SkuController` | Full SKU CRUD + suggest, validate, publish, titles, faq-suggestions, rollback | Done (modified) |
| `ValidationController` | POST /sku/{id}/validate | Done |
| `TierController` | POST /tiers/recalculate, /erp/sync | Done |
| `ClusterController` | GET/POST/PUT clusters | Done |
| `AuditController` | POST /audit/run, GET /audit/results/{category} | Done (modified) |
| `AuditLogController` | GET /audit-logs, /audit-logs/filters | Done |
| `BriefController` | GET /briefs, POST /brief/generate, POST /briefs/{id}/suggest-revision | Done (modified) |
| `IntentsController` | GET /taxonomy/intents | Done |
| `DashboardController` | GET /dashboard/summary, /decay-alerts, /channel-stats | Done (modified) |
| `SemrushImportController` | POST /admin/semrush-import, GET latest, DELETE batch | Done (modified) |
| `AdminBusinessRulesController` | GET/PUT /admin/business-rules, approve | Done |
| `ConfigController` | GET/PUT /config | Done |
| `BaselineController` | POST /gsc/baseline/{id}, /ga4/baseline/{id} | Done |
| `ShopifyProductPullController` | GET /shopify/status, products; POST /sync | Done |
| `TierChangeController` | POST /sku/{id}/tier-change-request, approve, status; POST /tier-change-requests/{id}/approve-portfolio | Done |
| `FAQController` | GET /faq/templates, POST /sku/{id}/faq | Done (modified) |
| `GscController` | GET /gsc/status | Done |
| `Ga4Controller` | GET /ga4/status | Done |
| `BulkOpsController` | GET summary, tier-change-requests; POST cluster-assignment, status-change, faq-apply; GET export | Done |
| `ClusterChangeRequestController` | Cluster change request workflow | Done |
| `TitleController` | POST /sku/{id}/titles (via SkuController delegation) | Done |
| `UserController` | User management (admin) | Done |

### 2.2 Services (33)

| Service | Purpose | Modified |
|---|---|---|
| `ValidationService` | Gate validation orchestration | — |
| `TierCalculationService` | Tier computation logic | — |
| `ReadinessScoreService` | 0–100 per-channel readiness scoring | Yes |
| `ChannelDeployService` | Shopify + GMC publish pipeline | Yes |
| `ChannelGovernorService` | Channel score computation (hardcoded deltas — see GAP-P3-1) | Yes |
| `ChannelTierRulesService` | Tier rules per channel | — |
| `ContentHealthScoreService` | 5-component CHS scoring | — |
| `DecayService` | Decay detection + notification generation | Yes |
| `ERPSyncService` | ERP integration — appears unreferenced (see GAP-ERP-01) | — |
| `ExecutiveReportService` | Summary reporting | — |
| `FAQService` | FAQ CRUD and suggestions | Yes |
| `FaqSuggestionService` | AI FAQ suggestions | — |
| `IntentAssignmentService` | Auto-assign intents | — |
| `MaturityScoreService` | Content maturity scoring | — |
| `NotificationService` | Event notifications | — |
| `PermissionService` | Permission checks | — |
| `PublishTraceService` | Publish history tracking | — |
| `BriefGenerationService` | Content brief generation | — |
| `AuditLogService` | Audit log operations (immutable) | — |
| `BaselineService` | GSC/GA4 baseline tracking | — |
| `AIAgentService` | AI suggestion workflows | — |
| `ApprovalService` | Approval request workflows | — |
| `BusinessRulesService` | Business rule management | — |
| `GmcFeedService` | Google Merchant Center feed | — |
| `SemrushParserService` | Semrush CSV parsing + batch import | Yes |
| `ShopifyProductPullService` | Shopify sync logic | — |
| `ShopifyRateLimiter` | Rate limit handling (2 calls/sec) | — |
| `TitleEngineService` | AI-powered title generation | Yes |
| `PythonWorkerClient` | HTTP RPC to FastAPI | Yes |
| `CacheManager` | Cache operations | — |
| `Logger` | Logging utilities | — |
| `ResponseFormatter` | API response formatting | — |

### 2.3 Console Commands (11)

| Command | Purpose | Status |
|---|---|---|
| `AuditRunWeeklyCommand` | Trigger weekly AI audit | Modified |
| `CieDecayCheckCommand` | Check SKU decay | Done |
| `CieVectorRetryListCommand` | List failed embeddings | New (untracked in git) |
| `CieVectorRetryProcessCommand` | Retry failed embeddings | New (untracked in git) |
| `DecayRunCommand` | Decay alert trigger | Done |
| `DeleteSampleSkusCommand` | Cleanup sample data | Done |
| `RefreshGoldenGateStatusCommand` | Recalculate gate status | Done |
| `ShopifySyncProductsCommand` | Shopify product sync | Done |
| `TestGscGa4LiveCommand` | Live GSC/GA4 test | Done |
| `TestSampleWorkflowCommand` | N8N workflow test | Done |
| `ValidatePortfolioGatesCommand` | Portfolio gate validation | Done |

### 2.4 Gate Validators (8 + support classes)

| Gate | File | Rule Enforced |
|---|---|---|
| G1 | `G1_BasicInfoGate` | Title, URL, description presence |
| G2 | `G2_IntentGate` | Exactly 1 primary intent from locked taxonomy |
| G3 | `G3_SecondaryIntentGate` | 1–3 secondary intents, must differ from primary |
| G4 | `G4_AnswerBlockGate` | 250–300 chars, contains primary intent keyword |
| G4-VEC | `G4_VectorGate` | Cosine similarity ≥ 0.72 (fail-soft warning, not block) — modified |
| G5 | `G5_TechnicalGate` | Schema markup, structured data |
| G6 | `G6_TierTagGate` | Tier-appropriate tagging; Kill = all fields disabled |
| G7 | `G7_ExpertAuthorityGate` | Hero/Support: min 1 credentialed authority statement |

Support classes: `GateValidator` (orchestrator), `GateInterface` (contract), `GateResult` (result object)

### 2.5 Middleware (5)

| Middleware | Purpose |
|---|---|
| `AuthMiddleware` | JWT/session authentication |
| `RBACMiddleware` | Role-based access control |
| `RateLimitMiddleware` | Rate limiting |
| `LoggingMiddleware` | Request logging |
| `TierLockMiddleware` | Tier change locking |

---

## 3. BACKEND — Python (FastAPI)

### 3.1 API Endpoints (18 routes)

| Method | Path | Purpose | Status |
|---|---|---|---|
| GET | `/`, `/api`, `/api/` | Health check | Done |
| POST | `/api/v1/sku/embed` | Generate embedding | Done |
| POST | `/api/v1/sku/similarity` | Cosine similarity vs cluster | Done |
| POST | `/api/v1/sku/validate` | Full gate validation pipeline | Done |
| POST | `/api/v1/sku/suggest` | AI content suggestions | Done |
| POST | `/api/v1/ai-agent/titles` | Title generation | Done |
| POST | `/api/v1/ai-agent/cluster-suggest` | Cluster suggestion | Done |
| POST | `/api/v1/ai-agent/suggest-revision` | Revision suggestions | Done |
| POST | `/api/v1/sku/{id}/suggestions/{sid}/status` | Update suggestion status | Done |
| POST | `/api/v1/audit/run` | Trigger audit (background task) | Done (modified) |
| GET | `/api/v1/audit/results/{category}` | Fetch audit results | Done |
| GET | `/api/v1/taxonomy/intents` | List intents | Done |
| GET | `/api/v1/clusters` | List clusters | Done |
| POST | `/api/v1/brief/generate` | Generate content brief | Done |
| POST | `/api/v1/baseline/gsc-metrics` | GSC baseline capture | Done |
| POST | `/api/v1/baseline/ga4-metrics` | GA4 baseline capture | Done |
| GET | `/api/v1/ga4/health` | GA4 connection health | Done |
| GET | `/api/v1/gsc/verify` | GSC connection verify | Done |

### 3.2 AI Audit Engines (4)

| Engine | File | Model | Status |
|---|---|---|---|
| OpenAI (GPT-4o) | `engines/openai_engine.py` | gpt-4o | Done (modified) |
| Google Gemini | `engines/gemini_engine.py` | gemini-pro | Done (modified) |
| Perplexity | `engines/perplexity_engine.py` | sonar-pro | Done (modified) |
| Anthropic (Claude) | `engines/anthropic_engine.py` | claude-sonnet-4-20250514 | Done |
| Google SGE | (manual / scraping) | — | No dedicated engine file confirmed |

Scoring scale: 0 = not mentioned, 1 = cited, 2 = summarised, 3 = recommended as best.
Degradation quorum: 3 of 4 engines must agree before action triggers.

### 3.3 Jobs / Cron Workers

| Job File | Schedule | Purpose |
|---|---|---|
| `nightly_erp_sync.py` | Nightly | ERP data pull → tier recalc |
| `weekly_ga4_sync.py` | Weekly (Sun 03:00 UTC) | GA4 metrics pull |
| `weekly_gsc_sync.py` | Weekly (Mon 03:00 UTC) | GSC metrics pull |
| `weekly_decay_check.py` | Weekly | Decay detection |
| `cis_d15_job.py` | D+15 from publish | Content improvement score |
| `cis_d30_job.py` | D+30 from publish | 30-day conclusion |
| `shopify_product_sync.py` | On-demand | Shopify catalog pull |
| `vector_retry_queue.py` | On-demand | Retry failed embeddings |
| `run_vector_retry_queue.py` | CLI entry point | Vector retry runner (new, untracked) |

### 3.4 Key Python Modules

| Module | Path | Purpose |
|---|---|---|
| `gates_validate.py` | `api/` | Full G1–G7 + VEC validation pipeline (32+ KB) |
| `schemas_validate.py` | `api/` | Pydantic request/response schemas |
| `embedding.py` | `src/vector/` | OpenAI embedding generation |
| `cluster_cache.py` | `src/vector/` | Redis cluster vector cache — modified |
| `weekly_service.py` | `src/ai_audit/` | Weekly audit orchestration — modified |
| `ai_agent.py` | `src/utils/` | AI agent wrapper — modified |
| `prompts.py` | `src/utils/` | AI prompt templates — modified |
| `business_rules.py` | `src/utils/` | Business rules loader |
| `gsc_client.py` | `src/integrations/` | Google Search Console client |
| `ga4_client.py` | `src/integrations/` | Google Analytics 4 client |

---

## 4. FRONTEND — React

### 4.1 Pages (20 routes)

| Page | Route | Role | Status |
|---|---|---|---|
| `Dashboard.jsx` | /dashboard | Reviewer | Done (modified) |
| `WriterQueue.jsx` | /queue | Writer | Done |
| `WriterEdit.jsx` | /sku/:id/edit | Writer | Done (modified) |
| `SkuEdit.jsx` | /sku/:id | Admin | Done |
| `AiAudit.jsx` | /audit | Reviewer | Done |
| `AuditTrail.jsx` | /audit-trail | Admin | Done |
| `Briefs.jsx` | /briefs | Writer | Done |
| `BulkOps.jsx` | /bulk-ops | Admin | Done |
| `BusinessRules.jsx` | /admin/business-rules | Admin | Done |
| `Channels.jsx` | /channels | Admin | Done |
| `ClustersPage.jsx` | /clusters | Admin | Done |
| `Config.jsx` | /config | Admin | Done |
| `ErpSync.jsx` | /erp-sync | Admin | Done |
| `Help.jsx` | /help | All | Done |
| `Maturity.jsx` | /maturity | Reviewer | Done |
| `SemrushImport.jsx` | /admin/semrush-import | Admin | Done |
| `ShopifyPull.jsx` | /shopify-pull | Admin | Done |
| `StaffKpis.jsx` | /staff-kpis | Reviewer | Done |
| `TierMgmt.jsx` | /tier-mgmt | Admin | Done |
| `ReviewSemrush.jsx` | /review/semrush | Reviewer | Done |

### 4.2 Key Components (32 total)

| Component | Purpose |
|---|---|
| `ValidationPanel.jsx` | Displays G1–G7 gate results in plain English (no gate codes per R3) |
| `TierLockBanner.jsx` | Tier lock notification (Kill = all fields blocked) |
| `TierBadge.jsx` | Tier display with spec palette colours |
| `FAQTab.jsx` | FAQ response authoring per SKU |
| `DecayMonitor.jsx` | Decay alert display |
| `AuditScoreCard.jsx` | AI audit citation scores (0–3 per engine) |
| `AuthGuard.jsx` | Protected route wrapper |
| `HiddenFieldSlot.jsx` | Hidden field management (Kill/Harvest tier) |
| `ImageUploader.jsx` | Image upload handler |
| `Header.jsx` / `Sidebar.jsx` | Navigation |
| `Toast.jsx` | Toast notifications |

### 4.3 Frontend Services & Utilities

| File | Purpose |
|---|---|
| `src/services/api.js` | Axios HTTP client for all API calls |
| `src/lib/authRouting.js` | Authentication/routing logic |
| `src/lib/rbac.js` | Client-side RBAC checks |
| `src/lib/tierFieldMap.js` | Tier → visible fields mapping |
| `src/theme.js` | Light palette (bg #FAFAFA, accent #5B7A3A, etc.) |

---

## 5. N8N WORKFLOWS

| ID | File | Purpose | Status |
|---|---|---|---|
| W1 | `W1_erp_sync_tier_calc.json` | ERP sync → tier recalculation | Done |
| W2 | `W2_vision_ai_extract.json` | Vision AI content extraction | New/untracked — see GAP-P7-1 |
| W3 | `W3_semantic_embed_cluster.json` | Semantic embedding + clustering | New/untracked — see GAP-P7-2 |
| W4 | `W4_content_draft_generation.json` | AI content draft generation | New/untracked — see GAP-P7-2 |
| W5 | (no JSON) | Gate validation | PHP→Python direct chain only — see GAP-P7-2 |
| W6 | `shopify_deploy.json` (backend/n8n) | Shopify channel deploy | Done — payload verification needed (GAP-P7-3) |
| W7 | `W7_decay_check.json` | Performance decay detection | Done |
| W8 | `W8_ai_audit_scheduler.json` | Weekly audit scheduler (Mon 06:00 UTC) | Done |

---

## 6. TESTS

### 6.1 PHP Tests

| File | Purpose |
|---|---|
| `Phase0/AuditLogImmutabilityTest.php` | Verify audit_log UPDATE/DELETE is blocked |
| `Phase0/BusinessRulesTest.php` | Business rules loading/caching — modified |
| `Phase0/RBACTest.php` | Role-based access control |
| `Phase1/GateValidationTest.php` | G1–G7 gate validation |
| `Phase1/TierEngineTest.php` | Tier calculation logic |
| `Phase1/VectorFailSoftTest.php` | Embedding failure handling |
| `ReadinessGoldenDoc4bTest.php` | Readiness score golden test |
| `Phase3Round2ScoringTest.php` | Scoring algorithm tests |

### 6.2 Python Tests

| File | Purpose |
|---|---|
| `tests/test_golden_skus.py` | Golden SKU gate validation — modified |
| `tests/python/test_golden_g4_g5.py` | G4/G5 gate specific tests |
| `tests/python/phase2/gsc_integration_test.py` | GSC integration testing |

### 6.3 Test Fixtures

| File | Content |
|---|---|
| `tests/fixtures/golden_test_data.json` | Golden SKU test data (aligned to Doc4b §1) |
| `database/seeds/golden_queries/cables_v1.0.json` | Cables category golden audit questions |

---

## 7. KNOWN GAPS (from GAP_LOG.md)

| Gap ID | Date | Severity | Summary |
|---|---|---|---|
| **GAP-ROUTES-01** | 2026-03-25 | **BLOCKING** | ~7 routes in api.php not in OpenAPI contract. Architect must add to spec or retire. |
| **GAP-ERP-05** | 2026-03-25 | **BLOCKING** | Intent taxonomy key conflict: `safety_compliance` vs `troubleshooting/regulatory`. Architect must confirm canonical key set. |
| GAP-ERP-ROUTE-01 | 2026-03-26 | Non-blocking | Alias route `POST /api/admin/erp-sync` not in OpenAPI spec. |
| GAP-INTENT-01 | 2026-03-25 | Non-blocking | CLAUDE.md display labels differ from code enum keys. No code change needed. |
| GAP-SEMRUSH-DDL-01 | 2026-03-25 | Non-blocking | `semrush_imports` has extra columns beyond canonical DDL. Orphaned going forward. |
| GAP-ERP-04 | 2026-03-25 | Non-blocking | ERP tier calc weights: Cloud Briefing uses 40/35/15/10 vs spec 40/25/20/15. Code follows spec (correct). |
| GAP-ERP-03 | 2026-03-25 | Non-blocking | `skus` vs `sku_master`+`sku_commercial` schema split. PHP uses legacy single table. |
| GAP-ERP-02 | 2026-03-25 | Non-blocking | Python `tier_recalculator.py` formula + BusinessRules key mismatch vs PHP path. May be dead code. |
| GAP-ERP-01 | 2026-03-25 | Non-blocking | `ERPSyncService.php` exists but appears unreferenced. Active path is TierController. |
| GAP-P7-1 | 2026-03-24 | Non-blocking | W2 Vision AI: no N8N JSON in repo. Architect to confirm if in v2.3.2 scope. |
| GAP-P7-2 | 2026-03-24 | Non-blocking | W3/W4/W5: direct PHP→Python chains only, no N8N JSON exports. |
| GAP-P7-3 | 2026-03-24 | Non-blocking | W6 Shopify payload: `metafields: []` in JSON — needs live staging verification. |
| GAP-P6-1 | 2026-03-24 | Non-blocking | Python cron reads env vars; `business_rules` has same values. Acceptable if env is set correctly. |
| GAP-P6-2 | 2026-03-24 | Non-blocking | `cis_status` vs spec `measurement_status` — confirm canonical name. |
| GAP-P6-3 | 2026-03-24 | Non-blocking | Semrush column names (`competitor_url`, `keyword_difficulty`) differ from some spec labels. |
| GAP-P6-4 | 2026-03-24 | Non-blocking | `GET /gsc/status` returns config-only, not live API verification. |
| GAP-P3-1 | 2026-03-24 | Non-blocking | Channel readiness deltas (10/7/0/-7) hardcoded in `ChannelGovernorService`. Should use `BusinessRules`. |
| GAP-P3R2-1 | 2026-03-24 | Non-blocking | Table named `tier_history` in code vs `sku_tier_history` in spec ERD. |
| GAP-API-10 | 2026-03-20 | Non-blocking | OpenAPI `ReadinessResponse.channels` enum conflict: `shopify/gmc` vs `google_sge/amazon/ai_assistants/own_website`. |
| GAP-API-13 | 2026-03-20 | Non-blocking | N8N callback endpoints (deploy success/fail) not in OpenAPI contract. |
| DB-01 | 2026-03-12 | Non-blocking | FAQ column names differ from some spec references (`question` vs `template_text`, `answer` vs `response_text`). |

---

## 8. IMPLEMENTATION COMPLETENESS SUMMARY

| Domain | Spec Coverage | Notes |
|---|---|---|
| Database Schema | ~95% | All core tables exist. Split schema (sku_master/commercial) partially done. |
| Gate Validation (G1–G7 + VEC) | 100% | All gates implemented. VEC is fail-soft per DECISION-005. |
| Tier System | 100% | All 4 tiers, tier history, tier change requests, Kill/Harvest field locking. |
| AI Audit (4 engines) | ~90% | 3 engines confirmed modified. Google SGE manual only. Weekly service done. |
| Semrush Integration | ~90% | Import, parse, batch ID, snapshots all done. Column naming gap (non-blocking). |
| Content Health Score | ~85% | Readiness scoring done. 5-component CHS partially verified. |
| Channel Deploy (Shopify + GMC) | ~85% | Deploy service done. Metafield payload needs staging verification (GAP-P7-3). |
| Analytics (GSC + GA4) | ~85% | Baselines, weekly sync jobs, landing performance all done. Live verify gap (GAP-P6-4). |
| Decay Detection | ~95% | Decay service, cron, notifications, revenue_risk columns all done. |
| N8N Workflows | ~70% | W1, W6, W7, W8 done. W2/W3/W4 new/untracked. W5 is code-side only. |
| Frontend (20 pages) | ~95% | All pages exist. WriterEdit + Dashboard modified. |
| Business Rules Engine | 100% | Table, seeding, audit trail, approval workflow all done. |
| FAQ System | ~90% | Tables, templates, responses, AI suggestions done. Column naming discrepancy (DB-01). |
| Vector Embeddings | ~95% | Redis cluster cache, retry queue, retry commands all done. |
| OpenAPI Contract | ~80% | ~7 routes undocumented. Channel enum conflict. Blocking for external consumers. |

---

**Overall: ~88–90% spec complete.**

The two blocking gaps to resolve before generating the final developer guide:
1. **GAP-ROUTES-01** — Undocumented routes must be added to OpenAPI or retired.
2. **GAP-ERP-05** — Intent taxonomy canonical key set must be confirmed by architect.

All other gaps are non-blocking or deferred architectural decisions.

---

*CIE v2.3.2 | current_audit_new.md | Generated 2026-03-30*
