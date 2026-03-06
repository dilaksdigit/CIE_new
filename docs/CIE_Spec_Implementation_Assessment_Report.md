# CIE v2.3.2 Spec vs Implementation Assessment Report

**Date:** March 2026  
**Scope:** All specs in `docs/reference` (Enforcement Edition, v2.3.1 Dev Spec + Build Pack, Integration Spec, OpenAPI, v2.3.2 addenda).  
**Layers:** Backend (PHP + Python), frontend, database migrations, N8N workflows.

---

## 1. Spec Pillars and Scoring Model

Spec pillars and acceptance criteria were derived from:

- **CIE v2.3.1 Developer Build Pack** (8 docs: canonical schema, OpenAPI, RBAC, test fixtures, sequence diagrams, title engine, audit logging, channel mapping)
- **CIE v2.3.1 Enforcement + Dev Spec** (gates G1–G7+G6.1, tiering, AI audit, decay loop, API detail)
- **CIE v2.3 Enforcement Edition** (seven gates, tier matrix, 9-intent taxonomy)
- **CIE Integration Specification** (ERP, N8N W1–W8, Shopify, GMC, Amazon)
- **cie_v231_openapi.yaml** (paths and schemas)
- **v2.3.2 guidance** (CLAUDE.md authority order, Semrush CSV, UI restructure, fail-soft vector, Amazon deferred)

### Pillars and Acceptance Items

| Pillar | Weight | Key acceptance items |
|--------|--------|---------------------|
| **1. Canonical Data Model** | 15% | 12+ tables per spec; cluster_master, sku_master, intent_taxonomy, sku_content, sku_gate_status, channel_readiness, sku_tier_history, ai_audit_*, audit_log immutable, material_wikidata, semrush_imports (+ v2.3.2 columns) |
| **2. Enforcement Gates & Validation** | 20% | G1–G7+G6.1 implemented; Harvest suspends G4/G5/G7; Kill only G1+G6; /sku/{id}/validate + /publish + /readiness per OpenAPI; vector 0.72; user_message not gate codes |
| **3. RBAC & Audit Logging** | 15% | 8 roles; no superuser; dual sign-off for tier override; Kill edit blocked; audit_log INSERT-only + trigger; event types logged (publish, gate_pass/fail, tier_change, etc.); GET /audit-logs for UI |
| **4. Tiering & ERP Integration** | 12% | Tier formula (margin 40%, cppc 25%, velocity 20%, returns 15%); percentile bands; tier_history on change; auto-promote Harvest→Support; ERP sync route; N8N W1 |
| **5. AI Audit & Decay Loop** | 12% | 20 golden questions per category; 4 engines; 0–3 scoring; decay stages (yellow_flag→alert→auto_brief→escalated); /audit/run, /audit/results/{category}; /brief/generate with failing_questions; N8N W8 |
| **6. Channel Integrations & JSON-LD** | 10% | Shopify cie:* metafields; GMC feed; JSON-LD/FAQ in head; W6 channel deploy; Amazon deferred per DECISION-001 |
| **7. Semrush Loop** | 8% | CSV import columns; import_batch_id; semrush_content_snapshots on publish; /review/semrush (Rank Movement, Competitor Gaps, Quick Wins); queue badges |
| **8. CMS/UI & Workflow** | 8% | Writer queue + edit; reviewer dashboard/maturity/ai-audit/channels/kpis; admin clusters/config/tiers/audit-trail/bulk-ops/semrush-import; G6.1 field visibility by tier; no gate codes/cosine in UI; light theme; desktop 1280px+ |

Scoring: **0** = missing, **0.5** = partial, **1** = complete per spec.

---

## 2. Canonical Schema vs Migrations

### Summary

- **cluster_master, intent_taxonomy, sku_master, sku_secondary_intents, sku_content, sku_gate_status, channel_readiness, sku_tier_history, material_wikidata, tier_types, tier_intent_rules:** Present in `024_create_canonical_cie_schema.sql` with types and FKs largely aligned. Minor deviations: `intent_vector` as JSON (spec VECTOR(1536)); `cluster_master` uses `id` UUID + `cluster_id` unique (spec has cluster_id PK).
- **ai_golden_queries, ai_audit_runs, ai_audit_results:** Present in `023_create_ai_audit_tables.sql`. `ai_audit_results.cited_sku_id` references `skus(id)` (legacy); spec references `sku_master(sku_id)`. Extra columns: `engines_available`, `quorum_met`. `intent_type_id` vs spec `intent_type` FK.
- **audit_log:** Original `011_create_audit_log_table.sql` uses `user_id`, `entity_id` CHAR(36). Later migrations add `actor_id`, `actor_role`, `timestamp`, canonical action ENUM (`051_ensure_audit_log_timestamp.sql`, `026_alter_audit_log_to_canonical.sql`). **Immutability:** `042_audit_log_immutability_triggers.sql` adds BEFORE UPDATE/DELETE triggers (MySQL). Spec also requires REVOKE UPDATE/DELETE; not verified in migrations.
- **semrush_imports:** `046_create_semrush_imports_table.sql` and `052_ensure_semrush_imports_table.sql` define table. **Gap:** v2.3.2 addendum columns `position`, `competitor_position`, `import_batch_id`, `imported_at` — `imported_at` present; `import_batch_id` (UUID) not present (batch is `import_batch` string date). `semrush_content_snapshots` table not found in migrations.
- **validation_logs:** Evolved via `050_alter_validation_logs_add_new_gates.sql`, `053_add_user_id_to_validation_logs.sql`, `054_align_validation_logs_with_service.sql` (validation_status, results_json, passed). Aligned with ValidationService/ValidationLog usage.

### Schema Completion Score: **0.78**

- Full: canonical core tables, tier_history, audit_log structure + immutability trigger, semrush_imports base.
- Partial: intent_vector type, ai_audit_results cited_sku + intent_type naming, audit_log REVOKE not in migrations.
- Missing: semrush_content_snapshots; import_batch_id (UUID) in semrush_imports per v2.3.2.

---

## 3. Enforcement Gates G1–G7 + G6.1 and Validation Flows

### Backend

- **PHP:** `ValidationController` calls `ValidationService::validateSku($id)`. Route: `POST /api/v1/sku/{sku_id}/validate`. Service uses `GateValidator` (G1–G7 + G4_VectorGate). Gates: G1_BasicInfoGate, G2_IntentGate, G3_SecondaryIntentGate, G4_AnswerBlockGate, G4_VectorGate, G5_TechnicalGate, G6_CommercialPolicyGate, G7_ExpertGate. G6.1 tier-lock logic is in Python; PHP GateValidator does not explicitly implement Harvest “suspend G4/G5/G7” or Kill “only G1+G6” in the same order as spec — validation is delegated to PHP gates and optionally Python.
- **Python:** `api/gates_validate.py` implements `run_g1`–`run_g7`, `run_g61`. Harvest: G4, G5, G7 skipped. Kill: only G1+G6 then return. Intent keywords and tier rules match spec. `POST /api/v1/sku/validate` in Python accepts body and returns 200 pass or 400 fail with failures list. PHP does not consistently proxy to Python for validation; it uses its own GateValidator and PythonWorkerClient for vector only where used.
- **OpenAPI alignment:** Routes exist for `/sku/{sku_id}/validate`, `/sku/{sku_id}/publish`, `/sku/{sku_id}/readiness`. Request/response shapes: ValidationController returns `valid`, `status`, `gates`, `failures`, `can_publish`, `vector_validation`; OpenAPI expects `status`, `gates`, `vector_check`, `publish_allowed`. Partial alignment (naming and structure differ slightly).
- **Vector:** Python `/api/v1/sku/similarity` and embed are fail-soft (pending on error). CLAUDE.md specifies fail-soft for v2.3.2; Enforcement v2.3.1 specifies hard block. Treated as **approved deviation**. GateValidator sanitizes similarity values from writer-facing output (R4).

### Gates Completion Score: **0.82**

- Full: G1–G7+G6.1 logic in Python; Harvest/Kill rules; vector fail-soft; gate_pass/gate_fail audit; no gate codes in API user_message where implemented.
- Partial: PHP validation path may not call Python for full gate set; response shape vs OpenAPI; single code path (path param is internal `id` vs spec `sku_id` business id) may need confirmation.

---

## 4. RBAC and Audit Logging

### RBAC

- **Roles:** Middleware uses `rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,...`. Roles referenced: CONTENT_EDITOR, PRODUCT_SPECIALIST, CONTENT_LEAD, SEO_GOVERNOR, CHANNEL_MANAGER, PORTFOLIO_HOLDER, ADMIN, AI_OPS, FINANCE. Matches 8-role hierarchy.
- **Kill SKU:** `034_prevent_kill_tier_update_trigger.sql` and tier logic prevent updates for Kill. WriterEdit `FIELDS_BY_TIER.kill = []` — all content fields disabled.
- **Dual sign-off:** Tier change from manual override: TierCalculationService and TierController do not show a second-approver workflow; TierHistory has `changed_by`. Spec requires Portfolio Holder + Finance dual sign-off for manual override — **not fully implemented**.
- **No superuser:** Admin cannot edit SKU content (content routes use CONTENT_EDITOR, etc.); admin has config/semrush/audit-trail.

### Audit log

- **Write:** AuditLog::create() used in ValidationController, SkuController (publish/content), GateValidator (gate_pass/gate_fail), TierController, SemrushImportController, DecayService, ERPSyncService. Actions: validate, publish, gate_pass, gate_fail, tier_change, semrush import, etc. Canonical fields: entity_type, entity_id, action, field_name, old_value, new_value, actor_id, actor_role, timestamp, ip_address, user_agent.
- **Immutability:** MySQL triggers in `042_audit_log_immutability_triggers.sql` block UPDATE/DELETE.
- **Read:** **Gap —** No GET endpoint for audit log. `AuditLogController` is empty. Frontend `auditLogApi.getLogs(params)` calls `GET /api/audit-logs`; no route registered in `api.php`. Audit Trail page will 404 or fail.

### RBAC & Audit Completion Score: **0.58**

- Full: 8 roles, Kill lockout, audit_log writes and immutability trigger.
- Partial: Dual sign-off for manual tier override not implemented; audit_log event coverage incomplete (e.g. permission_change, login).
- Missing: GET /audit-logs (and AuditLogController implementation).

---

## 5. Tiering, ERP Sync, and KPI Surfaces

### Tier calculation

- **TierCalculationService:** Implements composite score from margin, cppc, velocity, return rate with configurable weights (BusinessRules: tier.margin_weight 0.40, etc.). Percentile thresholds: hero 80th, support 30th, harvest 10th. Auto-promote: Harvest → Support when velocity +30% vs previous. TierHistory created on change. Aligned with spec.
- **ERP sync:** TierController::erpSync; ERPSyncService. Route `POST /api/v1/erp/sync` with RBAC FINANCE, ADMIN. Payload and tier recalculation wired.

### Dashboards and KPIs

- **DashboardController:** summary(): tier_summary, category_heatmap, decay_monitor, effort_allocation, staff_kpis. weeklyScores() from weekly_scores table. ReadinessScoreService used for per-channel scores. buildDecayMonitor() uses decay_status and decay_consecutive_zeros. buildEffortAllocation() uses StaffEffortLog with hero_pct and hero_alert &lt; 60%. Staff KPIs from ValidationLog (validated_by, passed).
- **Frontend:** Dashboard, StaffKpis, Maturity, AiAudit, Channels consume dashboard and audit-result APIs. Category heatmap and tier summary present.

### Tier/ERP/KPIs Completion Score: **0.85**

- Full: Tier formula, percentile bands, tier_history, auto-promote, ERP sync route, dashboard summary, decay monitor, effort allocation, staff KPIs.
- Partial: Strategic hero override and kill rules in TierCalculationService (shouldBeKilled, strategic_hero) — spec alignment OK; some KPI definitions (e.g. &gt;60% content hours on Hero) are reflected in hero_alert.

---

## 6. AI Audit, Decay Loop, and Brief Generation

### AI audit engine

- **Python:** `src/ai_audit/` contains audit_engine, weekly_service, decay_detector, decay_cron, engine adapters (OpenAI, Anthropic, Gemini, Perplexity). Jobs: weekly_ga4_sync, weekly_gsc_sync, cis_d15_job, cis_d30_job, weekly_decay_check, run_decay_escalation.
- **PHP:** AuditController::runByCategory, resultsByCategory. Routes: POST /api/v1/audit/run, GET /api/v1/audit/results/{category}. Category-level audit trigger and results retrieval present.
- **Golden queries:** Table ai_golden_queries with question_id, category, question_text, intent_type_id, query_family, target_tier, target_skus, success_criteria, locked_until. Structure matches spec; 20 questions per category and versioning are data/process, not schema-only.
- **Scoring and decay:** weekly_scores, ai_audit_results (score 0–3), decay_status on sku_master. Decay stages (yellow_flag, alert, auto_brief, escalated) in schema and Dashboard decay monitor.

### Brief generation

- **BriefController::generate:** Accepts sku_id, failing_questions. Creates ContentBrief (DECAY_REFRESH, deadline now + 14 days). Does not include “current Answer Block, competitor answers, suggested revision direction” per spec auto-brief contents — minimal implementation.
- **N8N W8:** `W8_ai_audit_scheduler.json` exists for weekly audit scheduling.

### AI Audit & Decay Completion Score: **0.72**

- Full: Audit run/results API, golden queries table, decay_status and consecutive zeros, brief generation endpoint, decay monitor in UI, N8N W8.
- Partial: Auto-brief content (competitor answers, suggested revision) not generated; 4-engine execution and quorum logic partially in Python (evaluateAuditQuorum in ValidationService); citation 0–3 scoring in results storage.

---

## 7. Channel Integrations (Shopify, GMC, Amazon) and JSON-LD

### N8N W6 Channel Deploy

- **W6_channel_deploy.json:** Webhook `w6/channel-deploy/approved`; PHP channel_governor check; Split by channel; Switch by channel (shopify, gmc, amazon). Shopify and GMC HTTP nodes; Amazon branch explicitly deferred (sticky note + comment). POST channel-deployed and channel-failed to CIE API. Aligned with DECISION-001 (Shopify + GMC only; Amazon deferred).
- **Gap:** Backend routes `/api/skus/{sku_code}/channel-governor`, `/api/skus/{sku_code}/channel-deployed`, `/api/skus/{sku_code}/channel-failed` are not present in `api.php`; W6 uses placeholder URLs (cie-api.example.com). So workflow structure is spec-aligned but integration is incomplete.

### Shopify / GMC / JSON-LD

- No Laravel commands or services found for `cie:generate-gmc-feed` or Shopify metafield push in the codebase. No PHP `render_cie_jsonld()` or equivalent in the repo. JSON-LD and FAQ schema generation are **not implemented** in the provided code; channel deploy is stubbed in N8N with example URLs.

### Amazon

- Intentionally deferred per CLAUDE.md; no Amazon SP-API code expected. Marked as **approved deviation** for completion %.

### Channels & JSON-LD Completion Score: **0.35**

- Full: W6 workflow structure, Shopify/GMC branches, Amazon deferred note.
- Partial: N8N W6 exists but backend endpoints for channel-governor and channel-deployed/failed are missing; placeholder URLs.
- Missing: GMC feed generation command; Shopify metafield mapping implementation; JSON-LD/FAQ injection in PHP.

---

## 8. Semrush Import and Performance Loop

### Import pipeline

- **SemrushImportController:** POST /api/admin/semrush-import (file upload), GET latest, DELETE by batch_date. CSV columns mapped: keyword, position, prev_position, search_volume, keyword_diff, url, traffic_pct, trend. import_batch as date string; inserted into semrush_imports. Audit log entry on import. Admin-only.
- **Schema:** semrush_imports has no `import_batch_id` (UUID) or `competitor_position`; v2.3.2 addendum columns partially missing. `semrush_content_snapshots` table not in migrations — “on publish” snapshot not implemented.

### UI and queue

- **SemrushImport.jsx:** Admin page for upload, latest batches, delete. No `/review/semrush` page with Rank Movement, Competitor Gaps, Quick Wins zones. Writer queue badges (Quick Win, Gaps) not found in WriterQueue/WriterEdit (suggestion types include keyword, citation, trend, competitor but no dedicated Semrush badge wiring from semrush_imports).

### Semrush Completion Score: **0.55**

- Full: CSV upload, batch storage, admin UI, audit log on import.
- Partial: Column set and batch identifier (UUID) per v2.3.2.
- Missing: semrush_content_snapshots; /review/semrush 3-zone screen; Quick Wins/Gaps logic and queue badges.

---

## 9. Frontend CMS/UI and Gate Behaviour

### Route coverage

- **Writer:** /writer/queue, /writer/edit/:skuId.
- **Reviewer:** /review/dashboard, /review/maturity, /review/ai-audit, /review/channels, /review/kpis.
- **Admin:** /admin/clusters, /admin/config, /admin/tiers, /admin/audit-trail, /admin/bulk-ops, /admin/semrush-import.
- **Help:** /help/flow, /help/gates, /help/roles.
- Missing from routing: /review/semrush; BusinessRules and Briefs pages exist but are not routed.

### Tier-based field visibility (G6.1)

- **WriterEdit.jsx:** FIELDS_BY_TIER: hero (title, description, answer_block, best_for, not_for, expert_authority); support (same minus expert_authority); harvest (specification only); kill ([]). TierLockBanner and tier-specific messaging. Matches G6.1 intent: Kill no fields, Harvest limited, Hero/Support full or reduced set.

### UX rules

- Gate codes: GateValidator sanitizes similarity; API returns error_code (e.g. G4_ANSWER_TOO_SHORT) — spec says no gate codes in UI; user_message is plain English. Partial (error_code may still appear in some responses).
- Cosine: Not shown to writer (sanitized in GateValidator).
- Theme: theme.js and THEME used; light palette. Desktop-only not verified in CSS (min-width 1280px).

### Frontend Completion Score: **0.78**

- Full: Writer/reviewer/admin routes, tier-based fields and banners, suggestion types and priority, dashboard/decay/KPIs consumption.
- Partial: Audit Trail UI exists but backend GET /audit-logs missing; /review/semrush and queue badges; strict 1280px desktop enforcement.
- Missing: BusinessRules and Briefs in router; optional /review/semrush.

---

## 10. Completion Percentages and Checklist

### Weighted overall completion

| Pillar | Weight | Score | Weighted |
|--------|--------|-------|----------|
| 1. Canonical Data Model | 15% | 0.78 | 0.117 |
| 2. Enforcement Gates & Validation | 20% | 0.82 | 0.164 |
| 3. RBAC & Audit Logging | 15% | 0.58 | 0.087 |
| 4. Tiering & ERP Integration | 12% | 0.85 | 0.102 |
| 5. AI Audit & Decay Loop | 12% | 0.72 | 0.086 |
| 6. Channel Integrations & JSON-LD | 10% | 0.35 | 0.035 |
| 7. Semrush Loop | 8% | 0.55 | 0.044 |
| 8. CMS/UI & Workflow | 8% | 0.78 | 0.062 |
| **Total** | **100%** | — | **~69.7%** |

**Overall project completion: ~70%** (rounded).

---

### Done (spec vs code)

- Canonical tables: cluster_master, sku_master, intent_taxonomy, sku_content, sku_gate_status, channel_readiness, sku_tier_history, material_wikidata, tier_types, tier_intent_rules ([024_create_canonical_cie_schema.sql](database/migrations/024_create_canonical_cie_schema.sql)).
- audit_log immutability triggers ([042_audit_log_immutability_triggers.sql](database/migrations/042_audit_log_immutability_triggers.sql)); canonical action ENUM and timestamp ([051](database/migrations/051_ensure_audit_log_timestamp.sql), [026](database/migrations/026_alter_audit_log_to_canonical.sql)).
- G1–G7+G6.1 in Python ([backend/python/api/gates_validate.py](backend/python/api/gates_validate.py)); Harvest/Kill rules; vector fail-soft ([backend/python/api/main.py](backend/python/api/main.py)).
- PHP GateValidator G1–G7 + vector; gate_pass/gate_fail audit ([backend/php/src/Validators/GateValidator.php](backend/php/src/Validators/GateValidator.php)); no cosine to writer.
- Tier calculation and tier_history ([TierCalculationService.php](backend/php/src/Services/TierCalculationService.php)); ERP sync route ([api.php](backend/php/routes/api.php)); Dashboard summary, decay monitor, effort allocation, staff KPIs ([DashboardController.php](backend/php/src/Controllers/DashboardController.php)).
- Audit run/results API ([AuditController.php](backend/php/src/Controllers/AuditController.php)); brief generate ([BriefController.php](backend/php/src/Controllers/BriefController.php)); ai_golden_queries and ai_audit_* tables ([023](database/migrations/023_create_ai_audit_tables.sql)).
- N8N W6 structure with Shopify/GMC and Amazon deferred ([n8n/workflows/W6_channel_deploy.json](n8n/workflows/W6_channel_deploy.json)).
- Semrush CSV import and admin UI ([SemrushImportController.php](backend/php/src/Controllers/SemrushImportController.php), [SemrushImport.jsx](frontend/src/pages/SemrushImport.jsx)).
- Writer routes and tier-based field visibility ([App.jsx](frontend/src/App.jsx), [WriterEdit.jsx](frontend/src/pages/WriterEdit.jsx)); reviewer and admin routes; theme and TierLockBanner.

---

### Partially done

- **DB:** intent_vector as JSON; ai_audit_results.cited_sku_id and intent_type naming; semrush_imports without import_batch_id (UUID) and semrush_content_snapshots.
- **Gates:** PHP vs Python validation path and response shape vs OpenAPI; ensure single source of truth for gate order and tier rules (PHP or Python).
- **RBAC:** Dual sign-off for manual tier override not implemented; audit_log event coverage (e.g. login, permission_change).
- **Channels:** W6 workflow present but backend channel-governor and channel-deployed/failed endpoints missing; placeholder URLs.
- **Semrush:** v2.3.2 columns and snapshots; /review/semrush and queue badges.
- **UI:** GET /audit-logs backend missing; BusinessRules/Briefs not routed; 1280px desktop enforcement.

---

### Not started / checklist

| ID | Item | Spec reference | Code / action |
|----|------|----------------|---------------|
| DB-01 | Add REVOKE UPDATE/DELETE on audit_log (or document as app-level) | Build Pack Doc 7.2 | migrations or DB docs |
| DB-02 | Create semrush_content_snapshots table; populate on publish | v2.3.2 Semrush addendum | migration + publish flow |
| DB-03 | Add import_batch_id (UUID) and competitor_position to semrush_imports | v2.3.2 Semrush | migration + import logic |
| API-01 | Implement GET /api/v1/audit-logs (or /audit-logs) and AuditLogController::index | OpenAPI / Build Pack Doc 7 | [AuditLogController.php](backend/php/src/Controllers/AuditLogController.php), [api.php](backend/php/routes/api.php) |
| API-02 | Implement POST /api/skus/{sku_code}/channel-governor and channel-deployed, channel-failed | Integration Spec W6 | New or existing controller + routes |
| API-03 | Align validation response with OpenAPI (status, gates, vector_check, publish_allowed) | cie_v231_openapi.yaml | ValidationController / ResponseFormatter |
| RBAC-01 | Dual sign-off for manual tier override (Portfolio Holder + Finance) | Build Pack Doc 3.2 | TierController / workflow + TierHistory |
| CH-01 | Implement GMC feed generation (e.g. artisan cie:generate-gmc-feed) | Integration Spec §4 | New command + feed mapping |
| CH-02 | Implement Shopify metafield push and cie:* namespace | Integration Spec §3 | Laravel job or N8N + PHP endpoint |
| CH-03 | Implement JSON-LD and FAQ schema injection (e.g. render_cie_jsonld) | Enforcement Spec 11.1; Build Pack | PHP view/helper or head injection |
| SEM-01 | Add /review/semrush page (Rank Movement, Competitor Gaps, Quick Wins) | v2.3.2 Semrush | New page + API for Semrush aggregates |
| SEM-02 | Wire Semrush Quick Win / Gaps badges to writer queue | v2.3.2 Semrush | WriterQueue + API |
| UI-01 | Route BusinessRules and Briefs if required by spec | UI Restructure | App.jsx |
| UI-02 | Enforce min viewport 1280px (desktop only) | CLAUDE R6 | CSS / theme |

---

### Approved deviations (not counted as incomplete)

- **Vector validation fail-soft:** v2.3.2 CLAUDE.md overrides Enforcement v2.3.1 hard block; implementation is fail-soft. Correct.
- **Amazon:** Deferred per DECISION-001; W6 and code correctly omit Amazon implementation.

---

## 11. Summary

- **Approximate completion: ~70%** across backend, frontend, and data/migrations, with all referenced specs considered.
- **Strongest:** Canonical schema, enforcement gates (Python + PHP), tiering and ERP sync, dashboard and KPIs, Semrush import and admin UI, writer/reviewer/admin route set and tier-based fields.
- **Weakest:** Audit log read API, channel deploy backend (channel-governor, channel-deployed/failed), GMC/Shopify/JSON-LD implementation, Semrush content snapshots and /review/semrush, dual sign-off for tier override.
- **Checklist:** Use the “Not started / checklist” table above to close gaps; “Partially done” items should be completed to reach full spec compliance.
