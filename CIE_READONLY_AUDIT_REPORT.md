# CIE v2.3.2 — Read-Only Codebase Audit Report

**Audit date:** March 3, 2025  
**Scope:** Comparison of codebase against source documents (Master Build Spec, v231/v232 packs, Enforcement Spec, Integration Spec, UI Restructure, Semrush CSV spec, openapi.yaml, golden_test_data).  
**No code was modified.**

---

## SECTION A — FILE & FOLDER STRUCTURE

Files and folders grouped by area (excluding `vendor/` and `node_modules/`).

### Backend (PHP)
- **Root:** `backend/php/` — `composer.json`, `artisan`, `public/`, `routes/`
- **Routes:** `backend/php/routes/api.php`
- **Source:** `backend/php/src/`
  - **Controllers:** AuthController, SkuController, ValidationController, TierController, ClusterController, ClusterChangeRequestController, AuditController, AuditLogController, BriefController, IntentsController, DashboardController, ConfigController, AdminBusinessRulesController, UserController, TitleController
  - **Models:** Sku, Cluster, Intent, IntentTaxonomy, SkuIntent, AuditLog, ContentBrief, TierHistory, SkuGateStatus, BusinessRule, User, Role, AuditResult, ClusterChangeRequest, StaffEffortLog, ValidationLog
  - **Services:** ValidationService, TierCalculationService, BusinessRulesService, ReadinessScoreService, MaturityScoreService, DecayService, PythonWorkerClient, FaqSuggestionService, TitleEngineService, ChannelGovernorService, ApprovalService, ExecutiveReportService, ERPSyncService, NotificationService, IntentAssignmentService, PermissionService
  - **Validators:** GateValidator, GateResult, GateInterface; **Gates:** G1_BasicInfoGate, G2_IntentGate, G2_ImagesGate, G3_SecondaryIntentGate, G3_SEOGate, G4_AnswerBlockGate, G4_VectorGate, G5_TechnicalGate, G6_CommercialGate, G6_CommercialPolicyGate, G7_ExpertGate
  - **Support:** BusinessRules
  - **Enums:** GateType, TierType, ValidationStatus, RoleType, IntentType, AuditEngineType
  - **Middleware:** AuthMiddleware, RBACMiddleware, TierLockMiddleware, LoggingMiddleware, RateLimitMiddleware
  - **Http:** Kernel; **Providers:** RouteServiceProvider; **Database:** Connection; **Console:** Kernel, Commands (TestSampleWorkflowCommand, DeleteSampleSkusCommand, CieDecayCheckCommand, DecayRunCommand)
  - **Utils:** ResponseFormatter, Logger, FileUploader, CacheManager, JsonLdRenderer; **Exceptions:** Handler

### Python jobs / cron
- **Root:** `backend/python/` — `run_validate_api.py`, `run_decay_escalation.py`, `api/`, `src/`
- **API:** `api/main.py`, `api/gates_validate.py`, `api/schemas_validate.py`
- **Modules:** `src/erp_sync/` (sync_job, tier_recalculator, connectors: odbc, rest, csv), `src/ai_audit/` (audit_engine, decay_detector, decay_cron, weekly_service, engines: openai, anthropic, gemini, perplexity), `src/vector/` (validation, embedding, cluster_cache), `src/brief_generator/` (generator, templates), `src/title/validation.py`, `src/jobs/` (nightly_erp_sync, weekly_decay_check, vector_retry_queue), `src/utils/` (config, logger)

### N8N workflow definitions
- **None found.** No `n8n/` directory or workflow JSON files in the repository.

### Frontend (React)
- **Root:** `frontend/` — `package.json`, `vite.config.js`, `index.html`, `src/`, `public/`
- **Entry:** `src/main.jsx`, `src/App.jsx`
- **Styles:** `src/styles/globals.css`, `src/theme.js`
- **Pages:** WriterQueue, WriterEdit, Dashboard, Maturity, AiAudit, Channels, StaffKpis, ClustersPage, Config, TierMgmt, AuditTrail, BulkOps, Briefs, Help, BusinessRules, Login, Register
- **Components:** `auth/` (Login, Register, AuthGuard, DefaultRedirect), `common/` (Sidebar, Header, Footer, Toast, Button, Input, Modal, UIComponents), `sku/` (SkuList, SkuDetailView, SkuEditForm, ValidationPanel, TierBadge, TierLockBanner, ImageUploader), `cluster/` (ClusterList, ClusterEditForm, IntentViewer, ClusterApprovalQueue), `brief/` (BriefQueue, BriefDetailModal, EffortReport), `audit/` (AuditDashboard, AuditScoreCard, DecayMonitor)
- **Lib:** `authRouting.js`, `rbac.js`, `tierFieldMap.js`; **Services:** `api.js`; **Scripts:** `darkToLightMap.js`, `findReplaceDarkColors.js`; **Help:** `Help_ORIGINAL.jsx`

### Database migrations / seeds
- **Migrations:** `database/migrations/` — 48 SQL files (e.g. 001_create_users_table, 002_create_roles_table, 003_create_clusters_table, 004_create_skus_table, 005_create_intents_table, 006_create_sku_intents_table, 007_create_audit_results_table, 008_create_content_briefs_table, 009_create_validation_logs_table, 010_create_tier_history_table, 011_create_audit_log_table, 011_create_cluster_vectors_table, 012_create_sku_vectors_table, 013_create_channel_mappings_table, 015_create_erp_sync_log_table, 016_create_staff_effort_logs_table, 017_create_approval_requests_table, 018_create_executive_kpis_table, 019_add_lock_version_to_skus_table, 020_add_ai_validation_pending_to_skus_table, 021_add_tier_access_to_intents_table, 022_add_category_to_clusters_table, 023_create_ai_audit_tables, 024_create_canonical_cie_schema, 025–045, hardening_schema_patch, safe_hardening_patch)
- **Seeds:** `database/seeds/` — e.g. 002_seed_roles, 007_seed_canonical_cie, golden_test_data.json

### Config / environment files
- **Root:** `.env.example`; **Frontend:** `frontend/.env`, `frontend/.env.local`
- **Config (PHP):** `config/` — app, auth, api, cache, cors, database, filesystems, hashing, logging, redis, session, view, mail, constants

### Other
- **Docs:** `docs/` — `api/openapi.yaml`, `API_INVENTORY.md`, `API_REFERENCE_COMPLETE.md`, `deployment/runbook.md`, etc.
- **Root:** `README.md`, `MASTER_SUMMARY.md`, `current_completion.md`, `BACKEND_REQUIREMENTS_FOR_FRONTEND_FIXES.md`, `FRONTEND_AUDIT_*`, `DOCUMENTATION_INDEX.md`, `IMPLEMENTATION_GUIDE.md`, `QUICK_START_GUIDE.md`, `START_HERE.md`, `WORKFLOW_WIRING_SUMMARY.md`, `SYSTEM_ARCHITECTURE_COMPLETE.md`
- **Scripts:** `seed_golden_data.php`, `validate_golden_skus.php` (project root)
- **Docker:** `docker-compose` (if present in repo)

---

## SECTION B — COMPONENT-BY-COMPONENT STATUS

| # | Component | Status |
|---|-----------|--------|
| 1 | L0 ERP Sync Python cron + POST /api/v1/erp/sync | 🔶 PARTIAL |
| 2 | L1 business_rules table + 52 seed rules | ✅ COMPLETE |
| 3 | L1 business_rules_audit immutable table + triggers | ✅ COMPLETE |
| 4 | L1 BusinessRules.get() helper | ✅ COMPLETE |
| 5 | L1 Admin UI for business rules | 🔶 PARTIAL |
| 6 | L2 GSC weekly pull Python job | ❌ MISSING |
| 7 | L2 GA4 weekly pull Python job | ❌ MISSING |
| 8 | L2 gsc_baselines table with d15/d30 columns | ❌ MISSING |
| 9 | L2 POST /api/gsc/baseline/{sku_id} | ❌ MISSING |
| 10 | L2 POST /api/ga4/baseline/{sku_id} | ❌ MISSING |
| 11 | L3 Tier calculation engine (CPS formula) | ⚠️ DIVERGED |
| 12 | L3 Auto-promotion rule (Harvest → Support on +30% velocity) | ✅ COMPLETE |
| 13 | L3 Readiness scores per channel (0–100) | ✅ COMPLETE |
| 14 | L3 Channel Maturity Score (CHS) computation | ✅ COMPLETE |
| 15 | L4 AI Agent — content suggestions pre-fill | 🔶 PARTIAL |
| 16 | L4 AI Agent — priority queue generation | ✅ COMPLETE |
| 17 | L4 AI Agent — gate failure explainer | 🔶 PARTIAL |
| 18 | L4 AI Agent — auto refresh brief generation | ✅ COMPLETE |
| 19 | L5 POST /api/v1/sku/validate (all 7 gates + VEC simultaneous) | ✅ COMPLETE |
| 20 | L5 G1 Cluster ID validation | ✅ COMPLETE |
| 21 | L5 G2 Primary Intent validation | ✅ COMPLETE |
| 22 | L5 G3 Secondary Intent validation | ✅ COMPLETE |
| 23 | L5 G4 Answer Block validation | ✅ COMPLETE |
| 24 | L5 G5 Best-For / Not-For validation | ✅ COMPLETE |
| 25 | L5 G6 Tier Tag validation | ✅ COMPLETE |
| 26 | L5 G6.1 Tier-Locked Intent field enforcement | ✅ COMPLETE |
| 27 | L5 G7 Expert Authority validation | ✅ COMPLETE |
| 28 | L5 VEC cosine similarity (≥0.72) with fail-soft | ✅ COMPLETE |
| 29 | L5 Harvest gate suspension (G4/G5/G7) | ✅ COMPLETE |
| 30 | L5 Kill tier full field lockout | 🔶 PARTIAL |
| 31 | L6 N8N W6 Channel Deploy workflow | ❌ MISSING |
| 32 | L6 Shopify metafield push | ❌ MISSING |
| 33 | L6 Google MC feed generation | ❌ MISSING |
| 34 | L6 Amazon SP-API push | ❌ MISSING |
| 35 | L6 Channel COMPETE/SKIP governor logic | ✅ COMPLETE |
| 36 | L7 N8N W8 AI Audit Scheduler (Monday 09:00) | ❌ MISSING |
| 37 | L7 20 golden questions per category in ai_golden_queries table | 🔶 PARTIAL |
| 38 | L7 4-engine scoring (ChatGPT, Gemini, Perplexity, Google SGE) | ✅ COMPLETE |
| 39 | L7 Decay loop (yellow_flag → alert → auto_brief → escalated) | ✅ COMPLETE |
| 40 | L7 POST /api/v1/brief/generate | ✅ COMPLETE |
| 41 | L7 content_briefs table | ✅ COMPLETE |
| 42 | L8 D+15 and D+30 scheduled jobs | ❌ MISSING |
| 43 | L8 CIS score computation | ❌ MISSING |
| 44 | DB all schema tables present | 🔶 PARTIAL |
| 45 | DB audit_log immutable (UPDATE/DELETE blocked) | ✅ COMPLETE |
| 46 | N8N W1 ERP Sync workflow | ❌ MISSING |
| 47 | N8N W2 Vision AI Extract | ❌ MISSING |
| 48 | N8N W3 Semantic Embed & Cluster | ❌ MISSING |
| 49 | N8N W4 Content Draft Generation | ❌ MISSING |
| 50 | N8N W5 Gate Validation (server-side) | N/A (PHP does validation) |
| 51 | N8N W7 Decay Check (daily 06:00) | 🔶 PARTIAL |
| 52 | RBAC middleware (8 roles in code) | ✅ COMPLETE |
| 53 | RBAC 2 seed user accounts (writer + reviewer) | ✅ COMPLETE |
| 54 | RBAC route guards on all three view groups | ✅ COMPLETE |
| 55 | UI Login screen + role-based redirect | ✅ COMPLETE |
| 56 | UI /writer/queue | ✅ COMPLETE |
| 57 | UI /writer/edit — field cards + tier banner | ✅ COMPLETE |
| 58 | UI /writer/edit — coloured gate hint borders | ✅ COMPLETE |
| 59 | UI /writer/edit — AI Suggestions panel (right 30%) | ✅ COMPLETE |
| 60 | UI /writer/edit — 4 AI suggestion card types | 🔶 PARTIAL |
| 61 | UI /review/dashboard | ✅ COMPLETE |
| 62 | UI /review/maturity | ✅ COMPLETE |
| 63 | UI /review/ai-audit | ✅ COMPLETE |
| 64 | UI /review/channels | ✅ COMPLETE |
| 65 | UI /review/kpis + weekly score input + trend line | ✅ COMPLETE |
| 66 | UI /admin/clusters | ✅ COMPLETE |
| 67 | UI /admin/business-rules | 🔶 PARTIAL |
| 68 | UI /admin/users | ❌ MISSING |
| 69 | UI /admin/audit-log | ✅ COMPLETE |
| 70 | UI /admin/semrush-import (CSV upload) | ❌ MISSING |
| 71 | UI /help/flow + /help/gates + /help/roles (3-tab page) | ✅ COMPLETE |
| 72 | UI ? help icon in both navbars | ✅ COMPLETE |
| 73 | UI Light theme applied globally (all screens) | ✅ COMPLETE |
| 74 | semrush_imports table | ❌ MISSING |
| 75 | Semrush CSV import flow → /admin/semrush-import | ❌ MISSING |
| 76 | weekly_scores table | ✅ COMPLETE |
| 77 | Weekly score input on /review/kpis | ✅ COMPLETE |
| 78 | golden_test_data.json wired to automated test suite | 🔶 PARTIAL |

**Notes for Section B:**
- **L0:** POST /api/v1/erp/sync exists (TierController::erpSync); Python cron is a stub (`nightly_erp_sync.py` only prints).
- **L1 Admin UI:** BusinessRules.jsx and API exist; **no route in App.jsx** for `/admin/business-rules` — Sidebar links there but route falls through to `/admin/*` → redirect to clusters.
- **L3 CPS:** Formula uses raw velocity term (`velocity * wVelocity`) instead of spec’s `velocity/max_velocity * 0.20`.
- **L5 Kill lockout:** Backend G6 blocks edits for Kill; frontend audit notes KILL tier fields not disabled on SkuEdit (frontend gap).
- **L7 20 questions:** ai_golden_queries exists; seed/count per category not verified as exactly 20.
- **N8N W7:** Python `weekly_decay_check.py` / decay_cron exist; no N8N workflow definition in repo.
- **DB tables missing:** gsc_baselines, semrush_imports, sku_readiness (canonical). cluster_master, sku_master, etc. in 024; some app code uses `skus`/`clusters` (non-canonical names).
- **weekly_scores:** Spec defines exactly 5 columns (id, week_start, score, notes, created_at). Migrations 038/045 match spec. No actor_id column.
- **golden_test_data:** File exists; SKU codes differ from spec example “SKU-CABLE-001”; validate_golden_skus.php and seed_golden_data.php reference it but full test wiring not confirmed.

---

## SECTION C — COMPLETION SUMMARY TABLE

| Layer/Area | Total Components | Complete | Partial | Missing | % Done |
|------------|------------------|----------|---------|---------|--------|
| Backend Core (PHP) | 18 | 14 | 3 | 1 | ~78 |
| Python Jobs | 8 | 1 | 1 | 6 | ~19 |
| N8N Workflows | 7 | 0 | 0 | 7 | 0 |
| Database Schema | 12 | 8 | 2 | 2 | ~67 |
| Frontend Routes | 18 | 15 | 2 | 1 | ~83 |
| RBAC | 3 | 3 | 0 | 0 | 100 |
| AI Agent | 4 | 2 | 2 | 0 | ~50 |
| Content Enforcement (Gates) | 12 | 11 | 0 | 1 | ~92 |
| Channel Deployment | 5 | 1 | 0 | 4 | 20 |
| Audit & Decay | 6 | 5 | 1 | 0 | ~83 |
| CIS Measurement | 2 | 0 | 0 | 2 | 0 |
| UI Theme | 1 | 1 | 0 | 0 | 100 |

---

## SECTION D — OVERALL COMPLETION ESTIMATE

Weighted by complexity (as specified):

- Database + migrations: 15% × ~67% ≈ **10%**
- Backend API + gate logic: 25% × ~85% ≈ **21%**
- Python jobs (ERP, GSC, GA4, CIS, decay): 15% × ~20% ≈ **3%**
- N8N workflows: 10% × 0% ≈ **0%**
- Frontend screens + routing: 20% × ~80% ≈ **16%**
- AI Agent integration: 10% × ~50% ≈ **5%**
- Testing / golden data validation: 5% × ~40% ≈ **2%**

**Overall completion estimate: ~57%**

---

## SECTION E — MISSING COMPONENTS LIST

- L2 GSC weekly pull Python job  
- L2 GA4 weekly pull Python job  
- L2 gsc_baselines table (with d15_*, d30_*, cis_score)  
- L2 POST /api/gsc/baseline/{sku_id}  
- L2 POST /api/ga4/baseline/{sku_id}  
- L6 N8N W6 Channel Deploy workflow  
- L6 Shopify metafield push (and endpoint/callback)  
- L6 Google MC feed generation  
- L6 Amazon SP-API push  
- L6 POST /api/skus/{sku_code}/channel-deployed  
- L6 POST /api/skus/{sku_code}/channel-failed  
- L7 N8N W8 AI Audit Scheduler (Monday 09:00 UTC)  
- L8 D+15 scheduled job (fill d15_* from GSC/GA4)  
- L8 D+30 scheduled job + CIS score computation  
- N8N W1 ERP Sync workflow (Cron 02:00 UTC)  
- N8N W2 Vision AI Extract  
- N8N W3 Semantic Embed & Cluster  
- N8N W4 Content Draft Generation  
- N8N W7 Decay Check workflow (daily 06:00) — workflow definition only; Python decay exists  
- DB table: gsc_baselines  
- DB table: semrush_imports  
- DB table: sku_readiness (canonical) — channel_readiness exists in 024  
- UI /admin/users (user management screen)  
- UI /admin/semrush-import (CSV upload screen)  
- POST /api/v1/sku/{id}/publish  
- GET /api/v1/sku/{id}/ai-suggestions  
- GET /api/v1/briefs/{sku_id} (briefs by SKU)  
- POST /api/admin/semrush-import  
- GET /api/admin/semrush-import/latest  
- GET /api/admin/sync-failed  

---

## SECTION F — INCOMPLETE / DIVERGED ITEMS

- **L0 ERP Sync Python cron:** POST /api/v1/erp/sync exists and triggers tier recomputation. Python `nightly_erp_sync.py` is a stub (no real pull). N8N W1 not in repo.
- **L1 Admin UI for business rules:** Backend GET/PUT /admin/business-rules and BusinessRules.jsx exist. App.jsx has no route for `/admin/business-rules`; link in Sidebar goes to that path which matches `/admin/*` and redirects to `/admin/clusters`. **Fix:** Add route and mount BusinessRules page.
- **L3 Tier calculation engine (CPS formula):** Weights from BusinessRules and percentile bands (p80/p30/p10) are used. Velocity term is `velocity * wVelocity` instead of spec `(velocity/max_velocity) * 0.20`. **Gap:** Normalise velocity by cohort max.
- **L4 AI Agent — content suggestions pre-fill:** Writer edit uses FAQ suggestions and Semrush-style data; no dedicated GET /api/v1/sku/{id}/ai-suggestions or full pre-fill from Claude.
- **L4 AI Agent — gate failure explainer:** Validation returns error_code, detail, user_message; no separate “plain English” explainer endpoint or dedicated UI block.
- **L5 Kill tier full field lockout:** G6_CommercialPolicyGate fails Kill with blocking; frontend (per audit) does not disable all content fields and hide submit for Kill.
- **L7 20 golden questions per category:** ai_golden_queries table and migrations exist; seed data for exactly 20 per category not verified.
- **N8N W7 Decay Check:** Python decay check/decay_cron exist; no N8N workflow JSON in repo.
- **DB all schema tables:** cluster_master, sku_master, intent_taxonomy, sku_secondary_intents, sku_content, sku_gate_status, sku_tier_history, channel_readiness, ai_audit_runs, ai_audit_results, ai_golden_queries, content_briefs, business_rules, business_rules_audit, audit_log, weekly_scores present (canonical or legacy). **Missing:** gsc_baselines, semrush_imports; sku_readiness as canonical table (readiness in channel_readiness / app columns).
- **weekly_scores table:** Has week_start, score, notes, created_at; matches spec exactly (5 columns, no actor_id).
- **UI /admin/business-rules:** Page and API exist; route missing (see L1 Admin UI).
- **UI /writer/edit — 4 AI suggestion card types:** WriterEdit has suggestion cards (e.g. keyword, competitor gap); four distinct types (Keyword Opportunity, AI Visibility Issue, Trending Search, Competitor Gap) from semrush_imports + AI audit not all confirmed wired.
- **golden_test_data.json wired to automated test suite:** File at `database/seeds/golden_test_data.json`; referenced by `seed_golden_data.php` and `validate_golden_skus.php`. No SKU-CABLE-001 in file; automated test suite wiring not fully verified.

---

## SECTION G — CRITICAL PATH STATUS

| Step | Description | Status |
|------|-------------|--------|
| 1 | ERP sync → tier assignment | 🔶 PARTIAL (API exists; Python/N8N stub or missing) |
| 2 | Business Rules loaded | ✅ COMPLETE |
| 3 | RBAC auth | ✅ COMPLETE |
| 4 | Writer queue displayed | ✅ COMPLETE |
| 5 | Writer opens edit screen | ✅ COMPLETE |
| 6 | G1–G7 + VEC validation passes | ✅ COMPLETE |
| 7 | GSC + GA4 baseline captured | ❌ MISSING (no endpoints/tables) |
| 8 | N8N W6 deploys to channel | ❌ MISSING |
| 9 | Readiness score updated | ✅ COMPLETE (service exists; post-deploy callback missing) |
| 10 | audit_log entry written | ✅ COMPLETE |

**Critical path end-to-end runnable: NO.**  
Steps 7 and 8 are missing (GSC/GA4 baselines and channel deploy). Path is runnable from login → queue → edit → validate → (no publish/channel deploy or baseline capture).

---

*End of report. No code was modified.*
