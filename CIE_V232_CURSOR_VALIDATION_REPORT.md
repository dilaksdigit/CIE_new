# CIE v2.3.2 — CURSOR VALIDATION REPORT

**Standing instruction:** CLAUDE.md was not found in the project root. Audit proceeded using the checklist and spec references in code comments only.

---

## BLOCK 1 — HARD RULES: 4/8 PASS

| ID | Result | Details |
|----|--------|---------|
| **HR-01** | ❌ FAIL + 🔴 DRIFT | **Amazon references in application code.** (1) `frontend/src/components/common/UIComponents.jsx` line 169: ChannelBadge map includes `Amazon: THEME.hero` — channel name "Amazon" rendered in UI. Spec: Amazon deferred; zero integration. (2) `cie_v231_openapi.yaml` lines 85, 108, 513: publish description and enum include "amazon". (3) `database/migrations/024_create_canonical_cie_schema.sql` line 174: `channel_readiness.channel` ENUM includes `'amazon'`. **FIX:** Remove "Amazon" from ChannelBadge map (or replace with "Deferred"); remove amazon from OpenAPI examples/enums; consider channel enum per spec (or document exception). |
| **HR-02** | ✅ PASS | No gate codes (G1_FAIL, G2_FAIL, … CIE_G) found in frontend component files. |
| **HR-03** | ❌ FAIL | **Cosine similarity number visible to writer.** `frontend/src/components/sku/SkuEditForm.jsx` line 243: `<input type="text" value={sku.similarity_score ?? '—'} disabled ... />` — writer sees numeric similarity. Spec: writer must never see the number. **FIX:** Remove the "Similarity Score" field from the writer-facing form, or show only a pass/warn/— label (no numeric value). |
| **HR-04** | ✅ PASS | No review_queue, approval_queue, pending_review, ReviewQueue, awaiting_approval in codebase. |
| **HR-05** | ✅ PASS | No `@media` in `frontend/src`; `frontend/src/styles/globals.css` uses `min-width`/`max-width` only for fixed layout (e.g. .stat-card, .login-box), not responsive breakpoints. |
| **HR-06** | ✅ PASS | No dark-mode, darkMode, prefers-color-scheme, theme-toggle, or dark: in frontend src. |
| **HR-07** | ❌ FAIL + 🔴 DRIFT | **Routes in code not in OpenAPI.** `cie_v231_openapi.yaml` defines a subset of paths. Laravel `backend/php/routes/api.php` defines many more (e.g. GET /sku, POST /sku, GET /sku/stats, PUT /sku/{id}/content, GET /queue/today, GET /dashboard/*, GET/POST /briefs, POST /tiers/recalculate, GET|PUT /config, /admin/business-rules*, GET /admin/semrush-import/latest, DELETE /admin/semrush-import/{batch_date}, GET /audit-logs, POST /gsc|ga4/baseline/{id}, POST /sku/.../suggestions/.../status). Only POST /api/admin/semrush-import is the permitted exception. **FIX:** Add all API routes to the OpenAPI spec or remove/redirect undocumented routes. |
| **HR-08** | ✅ PASS | No occurrence of "kpi_conductor" in codebase; kpi_reviewer used in seed. |

---

## BLOCK 2 — DATABASE: 6/10 PASS

| ID | Result | Details |
|----|--------|---------|
| **DB-01** | ⚠️ PARTIAL | Expected: cluster_master, sku_master, sku_content, sku_gate_status, sku_secondary_intents, sku_tier_history, channel_readiness, ai_audit_runs, ai_audit_results, ai_golden_queries, audit_log, intent_taxonomy, material_wikidata, semrush_imports, faq_templates, sku_faq_responses, weekly_scores. Present in migrations: cluster_master, sku_master, sku_content, sku_gate_status, sku_secondary_intents, sku_tier_history, channel_readiness, ai_audit_runs, ai_audit_results, ai_golden_queries, audit_log, intent_taxonomy, material_wikidata, semrush_imports, faq_templates, **sku_faqs** (not sku_faq_responses), weekly_scores. **FIX:** Align naming: either rename sku_faqs to sku_faq_responses or update spec. |
| **DB-02** | ✅ PASS | `database/migrations/024_create_canonical_cie_schema.sql` line 56: sku_master.tier ENUM('hero','support','harvest','kill') — exact. |
| **DB-03** | ✅ PASS | Canonical schema (024) uses CHARACTER SET utf8mb4 / DEFAULT CHARSET=utf8mb4; 045, 052 use utf8mb4. |
| **DB-04** | ✅ PASS | `database/migrations/042_audit_log_immutability_triggers.sql`: BEFORE UPDATE and BEFORE DELETE triggers on audit_log SIGNAL SQLSTATE '45000'. |
| **DB-05** | ✅ PASS | `database/seeds/007_seed_canonical_cie.sql`: 9 rows; intent_key values match (compatibility, comparison, problem_solving, inspiration, specification, installation, safety_compliance, replacement, bulk_trade). `055_enforce_intent_taxonomy.sql` CHECK matches. |
| **DB-06** | ❌ FAIL | **semrush_imports columns deviate.** Spec: keyword, search_volume, keyword_difficulty, intent, position, sku_code, cluster_id, competitor_url, competitor_position, import_batch_id (UUID), imported_at. Actual `052_ensure_semrush_imports_table.sql`: keyword, position, prev_position, search_volume, **keyword_diff** (not keyword_difficulty), **url** (not competitor_url), traffic_pct, trend, competitor_position, import_batch_id, imported_by, imported_at. Missing: intent, sku_code, cluster_id. **FIX:** Add missing columns; align keyword_difficulty/competitor_url naming or document mapping. |
| **DB-07** | ✅ PASS | `045_create_weekly_scores.sql`: id INT PK, week_start DATE, score INT CHECK (1–10), notes TEXT, created_at TIMESTAMP. |
| **DB-08** | ❌ FAIL | **Tier formula not spec-exact.** Spec: composite_score = (margin × 0.40) + ((1/cppc)×10×0.25) + (log10(velocity)×25×0.20) + ((1 - return_rate/100)×0.15). `TierCalculationService.php` uses BusinessRules weights but: velocity term is normalised (0–1) vs cohort max, not log10(velocity)×25×0.20; cppc term is (1/cppc)×wCppc without ×10. `TierController::erpSync` same pattern (velocity/maxVelocity, no log10; no ×10 on cppc). **FIX:** Implement exact formula: (1/cppc)*10*0.25 and log10(velocity)*25*0.20 (with safe handling for velocity=0). |
| **DB-09** | ✅ PASS | Percentile bands: BusinessRules tier.hero_percentile_threshold (0.80), support (0.30), harvest (0.10). TierController uses p80/p30/p10 from sorted cohort scores; assignment is percentile-based across active SKUs. |
| **DB-10** | ✅ PASS | 024: sku_master cluster_id → cluster_master(cluster_id), primary_intent_id → intent_taxonomy(intent_id); sku_secondary_intents sku_id → sku_master(sku_id), intent_id → intent_taxonomy(intent_id). All explicit FK constraints. |

---

## BLOCK 3 — GATE ENFORCEMENT: 8/12 PASS

| ID | Result | Details |
|----|--------|---------|
| **GATE-01** | ✅ PASS | ValidationController::validate called from route POST /api/v1/sku/{sku_id}/validate. SkuController::update (line 249) runs validation after update; publish (line 311) runs validate before publish. Save and Publish both trigger validation. |
| **GATE-02** | ✅ PASS | G1_BasicInfoGate: cluster_master lookup WHERE cluster_id = input AND is_active = true; on not found returns error_code CIE_G1_INVALID_CLUSTER and user_message 'Complete required basic info and choose a valid cluster.' (no code in user_message). |
| **GATE-03** | ❌ FAIL | G2_IntentGate when intent not in taxonomy returns error_code **CIE_G2_INTENT_TAXONOMY** (line 53). Spec requires **CIE_G2_INVALID_INTENT**. **FIX:** Use error_code 'CIE_G2_INVALID_INTENT' in metadata for invalid primary intent. |
| **GATE-04** | ❌ FAIL | G3_SecondaryIntentGate: min 1 and duplicate-primary check present. **Support tier max:** code uses `$count > 3` for both Hero and Support (line 53). Spec/OpenAPI: Support max_secondary = 2. **FIX:** For Support tier, enforce max 2 secondary intents (fail when count > 2). |
| **GATE-05** | ✅ PASS | G4_AnswerBlockGate: length from BusinessRules (250/300); `$len < $minLen` / `$len > $maxLen` → 250 PASS, 300 PASS, 249 FAIL, 301 FAIL. Keyword presence check (primary intent stemmed) present. |
| **GATE-06** | ✅ PASS | G5_TechnicalGate: Hero/Support require best_for_min (2) and not_for_min (1) from BusinessRules; Harvest/Kill return not_applicable (passed true, reason suspended). |
| **GATE-07** | ⚠️ MISSING | **G6 “missing tier” check not found.** Spec: SKU must have non-null tier; return CIE_G6_MISSING_TIER if tier is null. No gate in GateValidator explicitly checks tier === null and returns that code. G6_CommercialPolicyGate checks Kill/Harvest; G6_DescriptionQualityGate checks word count/similarity. **FIX:** Add a gate (or step in an existing G6 gate) that fails with CIE_G6_MISSING_TIER when tier is null. |
| **GATE-08** | ❌ FAIL | **G6.1 Kill: error_code missing.** G6_CommercialPolicyGate (lines 16–23): when tier === Kill returns blocking GateResult but **no metadata error_code**. Spec: return CIE_G6_1_KILL_EDIT_BLOCKED. Harvest arm correctly uses CIE_G6_1_TIER_INTENT_BLOCKED. **FIX:** Add metadata: ['error_code' => 'CIE_G6_1_KILL_EDIT_BLOCKED', 'user_message' => '…'] to the Kill-tier GateResult. |
| **GATE-09** | ✅ PASS | Readiness rules in BusinessRules (readiness.hero_primary_channel_min 85, hero_all_channels_min 70); ChannelGovernorService/ReadinessScoreService compute from components (not manually set). |
| **GATE-10** | ✅ PASS | G4_VectorGate: below threshold returns blocking: false, can_save: true, can_publish: false; audit_log records vector_similarity_warn; threshold from BusinessRules::get('gates.vector_similarity_min'). Save allowed, publish blocked. |
| **GATE-11** | ✅ PASS | ValidationService builds failures[] with error_code, detail, user_message. GateResult::toArray() strips error_code from writer-facing payload; failures in response retain error_code for internal use. WriterEdit displays user_message only. |
| **GATE-12** | ⚠️ PARTIAL | Publish flow: SkuController::publish validates → on pass captures baseline → audit_log → returns 200 with channels_updated. **No actual Shopify or GMC deploy call** in the traced path; response is success and channels_updated list. Spec: “Shopify metafield update + GMC supplemental feed regeneration. Both triggered automatically.” **FIX:** Implement or wire the actual deploy step (Shopify API + GMC feed) after validation and baseline, or document that deploy is out-of-scope/stub. |

---

## BLOCK 4 — FRONTEND & UI: 3/8 PASS

| ID | Result | Details |
|----|--------|---------|
| **UI-01** | 🔴 DRIFT | **Route count.** App.jsx defines 17 routes (login, writer/queue, writer/edit/:skuId, review/dashboard, maturity, ai-audit, channels, kpis, help/flow, gates, roles, admin/clusters, config, tiers, audit-trail, bulk-ops, semrush-import). Spec: exactly 15. **FIX:** Reduce to 15 or get spec updated to 17. |
| **UI-02** | ❌ FAIL | **Theme hex mismatches.** Spec: bg #FAFAFA, surface #FFFFFF, surfaceAlt #F5F5F4, border #E5E5E5, text #2D2D2D, textMuted #6B6B6B, accent #5B7A3A, table header #1F2D54. `frontend/src/styles/globals.css`: --bg #FAFAF8, --surface #FFFFFF, --surface-alt #F5F4F1, --border #E5E3DE, --text #2D2B28, --text-muted #9B978F, --accent #5B7A3A, --table-header #E5E3DE. **FIX:** Set --bg to #FAFAFA, --surface-alt to #F5F5F4, --border to #E5E5E5, --text to #2D2D2D, --text-muted to #6B6B6B, --table-header to #1F2D54 and ensure table header text is white. |
| **UI-03** | ❌ FAIL | **Tier badge colours.** Spec Harvest text #B8860B; Kill bg #FDEEEB. globals.css: --harvest #9E7C1A (not #B8860B); --kill-bg #FFEBEE (not #FDEEEB). TierBadge.jsx Kill bg #FDEEEB is correct; Harvest color #9E7C1A. **FIX:** Set Harvest text to #B8860B; set Kill bg to #FDEEEB in globals and any component overrides. |
| **UI-04** | ✅ PASS | SkuController::getTierBanner returns tier-specific copy (HERO/SUPPORT/HARVEST/KILL). Kill banner: "KILL SKU — Editing Disabled...". SkuController::update returns 403 for Kill tier; frontend disables fields for Kill. |
| **UI-05** | ✅ PASS | WriterEdit has suggestion cards: keyword (Semrush), citation (AI Audit), trend (GA4), competitor (Semrush); CANONICAL_SOURCE and SUGGESTION_SOURCE_BY_TYPE align; right-column layout for suggestions. |
| **UI-06** | ✅ PASS | WriterQueue (queue/today) sorted by commercial priority; API queue/today expected to return Hero then Support then Harvest, by margin (implementation in backend). |
| **UI-07** | ✅ PASS | HiddenFieldSlot and TIER_FIELD_MAP with TIER_TOOLTIPS; tier-based hidden fields show tooltip/reason. |
| **UI-08** | ❌ FAIL | **Emoji in production UI.** Spec: zero emoji in production-rendered UI; tier badges text only. (1) `frontend/src/components/sku/TierBadge.jsx` line 3: Hero uses icon '⭐'. Line 22 renders `{config.icon} {config.label}`. (2) WriterEdit.jsx: 🔍, 🤖, 📈, ⚔️ in SUGGESTION_CARD_TYPE_META. (3) SkuEditForm.jsx: 💾 Save, 🔬 Run AI Validation. (4) SemrushImport.jsx, Config.jsx, SkuEdit.jsx: 🔒. **FIX:** Remove emoji from TierBadge (text only); remove or replace emoji in buttons and suggestion cards with text or non-emoji icons. |

---

## BLOCK 5 — RBAC & SECURITY: 3/5 PASS

| ID | Result | Details |
|----|--------|---------|
| **RBAC-01** | ✅ PASS | RoleType enum: content_editor, product_specialist, seo_governor, channel_manager, ai_ops, content_lead (portfolio_holder), finance, admin — 8 roles. |
| **RBAC-02** | ✅ PASS | 044_seed_v232_writer_reviewer_users.sql: 3 accounts — admin, writer (Content Writer), kpi_reviewer (KPI Reviewer). Two human + one admin. |
| **RBAC-03** | ✅ PASS | Admin routes use rbac:ADMIN; AuthGuard and role-based routing restrict /admin/* to admin (or developer). |
| **RBAC-04** | ⚠️ MISSING | **Permission-denied audit and admin alert.** Spec: every 403 creates audit_log with actor_id, attempted_action, required_role, actual_role, timestamp, ip_address; and >5 denials from same actor in 24h triggers admin alert. No central middleware or handler found that logs 403 to audit_log with those fields and implements the 24h admin alert. **FIX:** Add middleware (or 403 handler) that writes to audit_log and checks 24h count for admin alert. |
| **RBAC-05** | ❌ FAIL | **Manual tier override dual sign-off.** Spec: manual tier change requires BOTH portfolio_holder AND finance sign-off; two separate approvals before change. TierController::recalculate does not enforce two approvers; erpSync only checks dual approval when preserving an existing manual override during ERP sync. No flow found where a manual tier change request requires portfolio_holder then finance approval before applying. **FIX:** Implement manual tier change workflow: request → portfolio_holder approval → finance approval → then apply; reject if either approval missing. |

---

## BLOCK 6 — AI AUDIT & DECAY: 4/5 PASS

| ID | Result | Details |
|----|--------|---------|
| **AUDIT-01** | ✅ PASS | audit_engine.py: ENGINES = [OpenAIEngine(), GeminiEngine(), PerplexityEngine(), GoogleSGEEngine()]. All 4 present; Google SGE is stub (engine_down) per doc. |
| **AUDIT-02** | ✅ PASS | audit_engine.py enforces score 0–3; invalid or out-of-range scores set to None and not counted. |
| **AUDIT-03** | ✅ PASS | decay_cron.py: Week 1 → yellow_flag; Week 2 → alert; Week 3 → auto_brief (brief_generate_hook); Week 4+ → escalated. All four stages implemented. (Week 2 “notification to Content Owner + SEO Governor” may be elsewhere; not verified.) |
| **AUDIT-04** | ✅ PASS | Quorum: BusinessRules decay.quorum_minimum (3); audit_engine run_status complete when responders >= 3. |
| **AUDIT-05** | ✅ PASS | 040_seed_business_rules.sql: sync.ai_audit_cron_schedule '0 9 * * 1' (Monday 09:00 UTC). Spec said 06:00 UTC; if spec is 06:00, **FIX:** set to '0 6 * * 1'. |

---

## BLOCK 7 — SEMRUSH CSV: 3/4 PASS

| ID | Result | Details |
|----|--------|---------|
| **SEM-01** | ✅ PASS | No SEMRUSH_API_KEY, semrush.com/api, semrush_api, api.semrush in project code (only in vendor author URL). |
| **SEM-02** | ✅ PASS | Route /admin/semrush-import (SemrushImport.jsx); admin-only; file upload for CSV. |
| **SEM-03** | ✅ PASS | SemrushImportController and 052 migration: import_batch_id (VARCHAR(36)) per upload; batch ID used. |
| **SEM-04** | ⚠️ MISSING | **Quick Wins filter.** Spec: position BETWEEN 11 AND 30, keyword_difficulty < 40, search_volume > 500, sku tier IN ('hero','support'). No code found implementing this filter (only mentioned in docs). **FIX:** Implement Quick Wins filter in API and/or frontend using semrush_imports (and keyword_difficulty column when added). |

---

## BLOCK 8 — CHANNEL INTEGRATION: 2/4 PASS

| ID | Result | Details |
|----|--------|---------|
| **CHAN-01** | ⚠️ PARTIAL | Publish flow runs validation and baseline then returns success; no Shopify or GMC deploy call in traced path. See GATE-12. |
| **CHAN-02** | ⚠️ NOT VERIFIED | GMC feed generator and inclusion rules (Kill excluded, Harvest excluded from Shopping, Hero ≥85, Support ≥70) not located in codebase. **FIX:** Implement or point to implementation and verify rules. |
| **CHAN-03** | ⚠️ NOT VERIFIED | No Shopify API client or rate-limiting code found (2 calls/sec). **FIX:** Implement Shopify calls with max 2 calls/second throttle. |
| **CHAN-04** | ✅ PASS | OPENAI_API_KEY and similar read from env (os.getenv / env()); no hardcoded credentials in backend source. |

---

## BLOCK 9 — GOLDEN TEST DATA: 0/14 VERIFIED

Golden test pack references fixtures (e.g. CBL-BLK-3C-1M, FLR-ARC-BLK-175) and expected gate/channel outcomes. `database/seeds/golden_test_data.json` uses different SKU codes and structure. Automated test suite and exact fixture set matching the checklist (SKU-CABLE-001, SKU-SHADE-002, etc.) were not run. **Action:** Align golden_test_data.json (or seed) with the pack’s 10 SKUs and run the automated suite to assert TEST-01 through TEST-14.

---

## SUMMARY

| Block | Score |
|-------|--------|
| BLOCK 1 — HARD RULES | 4/8 PASS |
| BLOCK 2 — DATABASE | 6/10 PASS |
| BLOCK 3 — GATE ENFORCEMENT | 8/12 PASS |
| BLOCK 4 — FRONTEND/UI | 3/8 PASS |
| BLOCK 5 — RBAC/SECURITY | 3/5 PASS |
| BLOCK 6 — AI AUDIT/DECAY | 4/5 PASS |
| BLOCK 7 — SEMRUSH CSV | 3/4 PASS |
| BLOCK 8 — CHANNELS | 2/4 PASS |
| BLOCK 9 — GOLDEN TESTS | 0/14 VERIFIED |
| **TOTAL** | **33/70** (excluding unverified Block 9) |

---

## CRITICAL FAILURES (DO NOT GO LIVE until fixed)

1. **HR-01** — Remove or document Amazon from UI, OpenAPI, and channel enum (UIComponents.jsx L169, cie_v231_openapi.yaml, 024 migration).
2. **HR-03** — frontend/src/components/sku/SkuEditForm.jsx L243: Do not show `similarity_score` to writer; remove field or show only non-numeric status.
3. **HR-07** — Align all API routes with OpenAPI spec or add them to the spec.
4. **DB-06** — semrush_imports: add intent, sku_code, cluster_id; align keyword_difficulty/competitor_url naming.
5. **DB-08** — Tier formula: use (1/cppc)×10×0.25 and log10(velocity)×25×0.20 in TierCalculationService and TierController.
6. **GATE-03** — G2_IntentGate: use error_code CIE_G2_INVALID_INTENT for invalid primary intent.
7. **GATE-04** — G3_SecondaryIntentGate: Support tier max 2 secondary intents.
8. **GATE-08** — G6_CommercialPolicyGate: add error_code CIE_G6_1_KILL_EDIT_BLOCKED for Kill tier.
9. **UI-02** — Theme: exact hex for bg, surfaceAlt, border, text, textMuted, table header (#1F2D54, white text).
10. **UI-03** — Harvest text #B8860B; Kill bg #FDEEEB.
11. **UI-08** — Remove emoji from TierBadge and production UI (buttons, suggestion cards, lock icons).
12. **RBAC-05** — Manual tier override: require portfolio_holder + finance sign-off before applying change.

---

## MISSING IMPLEMENTATIONS

- **CLAUDE.md** — Standing instruction file in project root.
- **GATE-07** — G6 check for null tier returning CIE_G6_MISSING_TIER.
- **GATE-12 / CHAN-01** — Actual Shopify metafield update and GMC feed regeneration after publish.
- **RBAC-04** — 403 → audit_log with required fields and >5/24h admin alert.
- **SEM-04** — Quick Wins filter (position 11–30, keyword_difficulty < 40, search_volume > 500, hero/support tier).
- **CHAN-02** — GMC feed inclusion rules (Kill/Harvest/Hero/Support) verified in code.
- **CHAN-03** — Shopify rate limit 2 calls/sec.

---

## DRIFT DETECTED

- **HR-01** — Amazon in UI and schema (see Critical Failures).
- **HR-07** — API routes beyond OpenAPI (see Critical Failures).
- **UI-01** — 17 routes vs spec 15.

---

## VERDICT

**BLOCKED** — Multiple critical failures (Amazon presence, writer-visible similarity score, route/spec drift, tier formula, G2/G3/G6.1 gate codes, theme/tier colours, emoji in UI, manual tier dual sign-off). Fix critical failures and add missing implementations before go-live.
