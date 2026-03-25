# CIE v2.3.2 — Gap Log
# SOURCE: CLAUDE.md Section 20, Axiom 1 — Code without a traceable source document reference does not exist.

---

## GAP-ERP-05 | 2026-03-25 | Intent Taxonomy | CLAUDE.md §6 lists "Safety/Compliance" and "Bulk/Trade" as taxonomy entries. Enforcement Spec §8.3 uses keys "troubleshooting" + "regulatory" + "replacement" (no "bulk_trade" key exists). Three potential conflicts: (1) troubleshooting vs safety_compliance, (2) regulatory vs safety_compliance, (3) replacement vs bulk_trade. Codebase follows Enforcement Spec §8.3. | Blocking: YES for OpenAPI update — Architect must confirm canonical key set.

---

## GAP-ERP-04 | 2026-03-25 | ERP Tier Calc | Cloud Briefing §11 uses weights 40/35/15/10 which conflicts with Integration Spec §1.3, Enforcement Spec §3.2, and Master Spec §8.1 which all use 40/25/20/15. Current code matches the Integration Spec (higher authority). Cloud Briefing is a summary doc, not authoritative. | Blocking: NO — Architect confirmation requested to close.

---

## GAP-ERP-01 | ERPSyncService.php unreferenced (dead code?) | 2026-03-25

**Description:** `backend/php/src/Services/ERPSyncService.php` exists but is not referenced by PHP routing/controllers; active ERP sync path is `TierController::erpSync` (`POST /api/v1/erp/sync`).

**Action:** Architect: confirm whether `ERPSyncService.php` is planned for future use or should be removed in a spec-approved cleanup. Do not delete without authority.

---

## GAP-ERP-02 | Python tier_recalculator formula + rule key mismatch | 2026-03-25

**Description:** `backend/python/src/erp_sync/tier_recalculator.py` implements a different commercial score formula (velocity normalized by max; no log scaling) and references a non-seeded rule key (`tier.auto_promotion_velocity_growth_pct` vs seeded `tier.auto_promotion_velocity_threshold`). This Python path is not invoked by the PHP ERP sync route.

**Action:** Architect: confirm whether Python recalculator is dead code or intended for a separate execution path; align formula and BusinessRules keys only with explicit authority.

---

## GAP-ERP-03 | `skus` vs `sku_master` / `sku_commercial` schema divergence | 2026-03-25

**Description:** Canonical spec schema defines `sku_master` + `sku_commercial` tables, but PHP implementation uses legacy `skus` table with ERP fields inlined. Current fixes follow the existing PHP pattern (add to `skus`) to avoid breaking changes.

**Action:** Architect: confirm authoritative production schema and consolidation plan (dual-table vs single-table). No migrations should retarget FKs or rename tables without formal approval.

---

## GAP-P7-1 | W2 Vision AI workflow missing in repo | 2026-03-24

**Description:** Phase 7 workflow audit found no N8N JSON definition for W2 (Vision AI), and no explicit W2 orchestration artifact under `n8n/workflows`.

**Action:** Architect: confirm whether W2 is in v2.3.2 scope or formally deferred.

---

## GAP-P7-2 | W3/W4/W5 orchestration is code-side only | 2026-03-24

**Description:** W3 (semantic embed), W4 (content draft), and W5 (gate validation) exist as direct PHP/Python service chains; no dedicated N8N JSON workflows were found in repo for these workflow IDs.

**Action:** Architect: confirm whether direct PHP→Python orchestration is acceptable for v2.3.2, or if explicit N8N workflow exports are required.

---

## GAP-P7-3 | W6 Shopify metafield mapping visibility in JSON | 2026-03-24

**Description:** `backend/n8n/workflows/shopify_deploy.json` shows GraphQL template payload with `metafields: []`. Runtime payload fields are assembled in PHP (`ChannelDeployService::buildDeployPayload`) and forwarded to N8N.

**Action:** Verify in live staging run that all required fields (title/meta/answer block/best_for/not_for/faq/json_ld/alt_text) are present in the outbound Shopify write payload.

---

## GAP-P6-1 | Python cron schedules: env vs `business_rules` table | 2026-03-24

**Description:** `sync.gsc_cron_schedule` / `sync.ga4_cron_schedule` are seeded in `business_rules`, but Python workers read `SYNC_GSC_CRON_SCHEDULE` / `SYNC_GA4_CRON_SCHEDULE` from the environment (defaults aligned in `backend/python/src/utils/config.py`). Functionally equivalent if deploy sets env to match seed.

**Action:** Architect: confirm env-based scheduling for Python cron is acceptable; ensure `.env` sets `SYNC_GSC_CRON_SCHEDULE=0 3 * * 0` and `SYNC_GA4_CRON_SCHEDULE=0 3 * * 1` where host cron does not inject them.

---

## GAP-P6-2 | `gsc_baselines.cis_status` vs spec `measurement_status` | 2026-03-24

**Description:** Master Spec §11 references measurement lifecycle naming; schema and D+30 job use `cis_status` (e.g. value `complete`).

**Action:** Architect: confirm `cis_status` as canonical or add a documented alias / migration to `measurement_status`.

---

## GAP-P6-3 | Semrush CSV: `URL` → `competitor_url`, `keyword_diff` vs `keyword_difficulty` | 2026-03-24

**Description:** Spec §4.1 table uses labels `url` / `keyword_diff`; implementation maps Semrush “URL” to `competitor_url` and persists difficulty as `keyword_difficulty` (migrations / CLAUDE.md §13).

**Action:** Architect: confirm Semrush “URL” semantics (ranking URL vs competitor) and canonical DB column names.

---

## GAP-P6-4 | GET `/api/v1/gsc/status` — config vs live GSC verification | 2026-03-24

**Description:** FINAL Dev Instruction Phase 2.1 implies live property verification with the service account; implementation returns configured property list without calling the Search Console API (documented in `GscController`).

**Action:** Architect: confirm config-only response for dev/staging; optional production hardening via live API call.

---

## GAP-P3R2-1 | Table `tier_history` vs spec `sku_tier_history` | 2026-03-24

**Description:** ERD / Build Pack name `sku_tier_history`; live migrations and model use `tier_history`. Behaviour is correct; rename is breaking.

**Action:** Architect: confirm canonical table name or defer to schema consolidation phase.

---

## GAP-P3R2-2 | OpenAPI ReadinessResponse channel enum + path | 2026-03-24

**Description:** `docs/api/openapi.yaml` may still document `shopify`/`gmc` and plural `skus` while runtime uses `google_sge`, `amazon`, `ai_assistants`, `own_website` and `GET /api/v1/sku/{sku_id}/readiness`. R1 locks OpenAPI — doc-only updates need explicit approval (see GAP-API-10).

**Action:** Architect approves documentation alignment (no new endpoints).

---

## GAP-P3R2-3 | TS-15 golden maturity 93 vs 94 (closed in Round 2) | 2026-03-24

**Description:** Checklist vs `golden_test_data.json` for CBL-BLK-3C-1M.

**Resolution:** Fixture updated to Doc4b §1 fixture 1 canonical values (`total` 94, `ai_visibility` 12, `level` Gold). PHPUnit asserts JSON + optional DB-backed `MaturityScoreService::compute` when SKU is seeded.

---

## GAP-P3-1 | Channel readiness score deltas (10 / 7 / 0 / -7) | 2026-03-24

**Description:** `ChannelGovernorService::computeChannelScore` still uses hard-coded per-channel deltas. Phase 3 prompt Fix 6 deferred pending architect-approved `BusinessRules` keys.

**Action:** Architect to confirm rule key names and seed values; then replace literals with `BusinessRules::get()`.

---

## GAP-P3-2 | CBL-BLK-3C-1M maturity totals (93 vs 94) | 2026-03-24 — **CLOSED (Round 2)**

**Resolution:** Aligned with Doc4b §1 via `golden_test_data.json` + `Phase3Round2ScoringTest::golden_fixture_cbl_blk_maturity_totals_match_doc4b`. See GAP-P3R2-3.

---

## GAP-P3-3 | Kill maturity level casing (`excluded` vs `Excluded`) | 2026-03-24 — **CLOSED (Round 2)**

**Resolution:** `MaturityScoreService` now returns title-case labels (`Bronze`, `Silver`, `Gold`, `Excluded`) per Doc4b golden convention (TS-16).

---

## GAP-P3-4 | OpenAPI ReadinessResponse channel enum | 2026-03-24

**Description:** `docs/api/openapi.yaml` still documents `shopify`/`gmc` while runtime readiness uses `google_sge`/`amazon`/`ai_assistants`/`own_website`. R1 locks contract — documentation update needs explicit approval (related: GAP-API-10).

**Action:** Architect approves OpenAPI doc-only alignment.

---

## GAP-P3-5 | ERP cron wiring in PHP | 2026-03-24

**Description:** `sync.erp_cron_schedule` is seeded; Python `nightly_erp_sync` documents intended schedule. No in-repo Laravel scheduler binding verified.

**Action:** Confirm N8N/host cron invokes Python job or equivalent.

---

## GAP-API-10 | Readiness Channel Enum Conflict | 2026-03-20

**Description:** Locked OpenAPI `ReadinessResponse.channels[].channel` currently enumerates `shopify,gmc`, while implementation/docs in other specs reference `google_sge,amazon,ai_assistants,own_website`.

**Conflict sources:**
- `docs/api/openapi.yaml` (`ReadinessResponse` channel enum)
- Dev build/audit expectations for four-channel outputs

**Action:** Architect must confirm canonical channel enum for the locked API contract, then align all layers (OpenAPI, service outputs, tests) via formal change protocol.

---

## GAP-API-13 | N8N Callback Endpoint Contract | 2026-03-20

**Description:** Integration materials reference inbound N8N callback posts for channel deploy success/failure, but no corresponding callback paths are present in the locked OpenAPI contract and no PHP route is currently defined for them.

**Action:** Architect must confirm whether callbacks are in-scope under existing locked endpoints or require formal OpenAPI contract update before implementation.

---

## DB-01 | 2026-03-12

**Description:** Migrations 027 and 063 create/rename FAQ tables. Schema differs from remediation spec column names:
- **faq_templates** (027): has `product_class`, `question`, `is_required`, `display_order` — spec mentions `intent_key`, `template_text`, `created_at`.
- **sku_faq_responses** (063 renames sku_faqs): has `answer` — spec mentions `response_text`.

**Action:** Do NOT rename columns without spec confirmation. Tables exist and are in use.

---

## GAP-1 | Task 3 | 2026-03-12

**Description:** Tier change request API routes are not declared in `cie_v231_openapi.yaml`. The locked API contract (CLAUDE.md Section 18; CIE_v232_FINAL_Developer_Instruction.docx R1) does not define paths for:
- POST tier-change-requests (store)
- POST tier-change-requests/{id}/approve-portfolio
- POST tier-change-requests/{id}/approve-finance
- POST tier-change-requests/{id}/reject

**Blocker:** `backend/php/routes/api.php` must not add routes that are not in the OpenAPI spec.

**Escalation:** Project owner — add tier change request paths to the OpenAPI contract and then wire routes in api.php. TierChangeController.php is implemented and ready; routes are intentionally omitted until the spec is updated.

---

## FIX 2 | embed/similarity routes | 2026-03-12

**Description:** openapi.yaml defines POST `/sku/{sku_id}/embed` and POST `/sku/{sku_id}/similarity`. No PHP controller method exists in SkuController (or elsewhere) for these; they are likely implemented by the Python Engine.

**Action:** Do not fabricate PHP handlers. Logged per FIX 2 Task B — restore only when controller exists. If these are proxy routes, add closure proxy in api.php mirroring the suggestion status proxy; otherwise implement or document Python-only usage.

---

## FIX 9 | Decay notification delivery | 2026-03-12

**Description:** _dispatch_decay_notification in decay_cron.py writes to audit_log only. CIE_v232_Hardening_Addendum.pdf specifies Week 2 (alert) and Week 4 (escalated) as "send notification to Content Owner + SEO Governor" / "escalate to Commercial Director". No email, Slack, or N8N webhook for delivery is defined in source docs.

**Action:** Decay notification delivery mechanism not defined in source docs. audit_log insert is implemented; external delivery (email/Slack/N8N) to be wired by project owner if required.

---

## GAP_LOG | INTENT_TAXONOMY_SPLIT | Finding #4/#15 | 2026-03-20

**Status:** Implemented 2026-03-20 — seeds `007` / `001`, migration `082_intent_taxonomy_enf_spec_83.sql`, Python `schemas_validate` / `gates_validate`, PHP draft label map, OpenAPI `SkuValidateRequest` enums aligned to ENF §8.3. `bulk_trade` smallint remaps to `comparison` (intent_id 2) in migration 082 where no §8.3 equivalent exists.

**Authority:** Per CLAUDE.md §16, Enforcement_Dev_Spec (#4) outranks CLAUDE.md (#9).

---

## GAP-AUDIT-012: Kill Tier Gate Applicability — Spec Conflict

**Date:** 2026-03-20
**Source conflict:**
- CIE_v2_3_1_Enforcement_Dev_Spec §2.2 — Kill runs G1, G6, G6.1
- CIE_Doc4b_Golden_Test_Data_Pack §3.1 (FLR-ARC-BLK-175) — "Only G6 runs", G1 = "N/A"

**Current code behaviour:** Follows Enforcement §2.2 (G1 + G6 + G6.1 for Kill).
**Impact:** If Golden doc is authoritative, G1 should be skipped for Kill tier.
**Resolution required:** Architect must confirm which document governs Kill gate applicability.
**Recommendation:** Enforcement §2.2 (Priority 4) outranks Doc4b (no priority listed).
Code should stay as-is pending architect confirmation.

---

## DB-02 | intent_taxonomy immutability trigger after 090 | 2026-03-20

**Description:** Migration `090_fix_intent_taxonomy_spec_alignment.sql` drops `trg_intent_key_immutable` so `intent_key` can be rewritten under older seeds, but does **not** recreate the trigger: `CREATE TRIGGER` fails for typical schema-only users when `log_bin=ON` (MySQL 8+ ERROR 1419) unless the server uses `log_bin_trust_function_creators` or the migration is run as a privileged definer.

**Action:** After applying `090`, re-apply the immutability trigger using the `DELIMITER` block in `082_intent_taxonomy_enf_spec_83.sql` (end of file) as a DBA/privileged MySQL user, or adjust instance policy per CIE ops.

---

## GAP-2.4-A | CLAUDE.md §6 vs §8.3 taxonomy | 2026-03-20

**Authority:** CIE_v2.3.1_Enforcement_Dev_Spec §8.3 JSON is single source of truth per spec; §4.2 matches. Database migrations `082` / `090` enforce the nine keys: `problem_solving`, `comparison`, `compatibility`, `specification`, `installation`, `troubleshooting`, `inspiration`, `regulatory`, `replacement`.

**Action:** CLAUDE.md §6 flat list should be updated for consistency (replace legacy `Bulk/Trade` with `Troubleshooting`; align `Safety/Compliance` wording with `Regulatory / Safety`). Not a database blocker.

---

## GAP_LOG | CHECK 2.8-D | staff_effort_logs.category_id | 2026-03-20

**Blocker:** Parent table for `staff_effort_logs.category_id` is undefined (`034_add_missing_fk_constraints.sql` notes parent not defined). No FK added until architect names target table or confirms informational-only column.

**Action:** Architect must define parent table or confirm no FK.

---

## DB-05 | sku_gate_status.gate_code VARCHAR(50) vs Build Pack ENUM | 2026-03-24

**Description:** Migration `078_alter_sku_gate_status_gate_code_varchar.sql` widened `gate_code` to `VARCHAR(50)`. CIE_v231_Developer_Build_Pack.pdf §1.2 defines `gate_code` as `ENUM('G1','G2',…,'VECTOR')`.

**Action:** Escalate to architect: revert to ENUM (see Phase 1 prompt SQL) or accept VARCHAR as intentional for forward-compatible gate codes.

---

## DB-16 | content_briefs brief_id PK + FK to sku_master | 2026-03-24

**Description:** Master Build Spec §6.5 expects `brief_id` UUID PK and `sku_id` referencing `sku_master(sku_id)`. Current schema uses surrogate `id` PK and may FK to legacy `skus`.

**Action:** Renaming PK or retargeting FK requires architect approval (breaking / data validation). No migration applied in Phase 1 additive pass.

---

## GAP-5 | semrush_imports keyword_diff vs keyword_difficulty | 2026-03-24

**Description:** CLAUDE.md §13 CSV column name `keyword_difficulty` vs legacy `keyword_diff` in older migrations.

**Action:** Migration `116_semrush_imports_spec_columns.sql` adds `keyword_difficulty` as an additive alias when `keyword_diff` still exists; `064` rename path remains valid. Architect may consolidate later.

---

## DB-12 | channel_readiness ENUM vs Build Pack four channels | 2026-03-24

**Description:** Build Pack §1.2 lists four channels (`google_sge`,`amazon`,`ai_assistants`,`own_website`). Migration `104_fix_channel_readiness_enum.sql` already expanded ENUM to include those plus `shopify` and `gmc` for CIE deployment rows.

**Action:** Skipped MODIFY to ENUM-only four values — would drop `shopify`/`gmc` from the ENUM and is non-additive. Current ENUM satisfies checklist “includes all four spec channels.”

---

## GAP_LOG | CHECK 2.8-E | ai_golden_queries FK | 2026-03-20

**Blocker:** FK was commented out in `034_add_missing_fk_constraints.sql` due to type mismatch / ambiguous column. Requires architect to confirm source column, target table, and aligned types before a follow-up migration adds the FK.

**Action:** Architect resolves types; then add FK in a subsequent migration.

---

## GAP-R2-1 | DB-16 content_briefs PK naming | 2026-03-24

**Description:** Master Spec §6.5 names PK `brief_id`; implementation uses `id`.

**Action:** Architect: rename to `brief_id` or accept `id` (breaking for models/queries).

---

## GAP-R2-2 | DB-16 content_briefs generated_at vs created_at | 2026-03-24

**Description:** Master Spec §6.5 references `generated_at`; schema uses `created_at`.

**Action:** Architect: rename column or accept `created_at`.

---

## GAP-R2-3 | DB-18 semrush_content_snapshots FK → sku_master | 2026-03-24

**Description:** `075_create_semrush_content_snapshots_table.sql` FK targets `skus(id)`. Canonical SKU table is `sku_master`; retarget requires row-level verification.

**Action:** Architect: approve DROP/ADD FK to `sku_master(id)` or keep legacy FK. See Phase 2 prompt SQL (do not run without approval).

---

## GAP-R2-4 | DB-19 weekly_scores reviewer_id | 2026-03-24

**Description:** UI Restructure §3 implies reviewer attribution; Amendment Pack `weekly_scores` sketch omits `reviewer`. RBAC + `audit_log` may suffice.

**Action:** Architect: add `reviewer_id` FK to `users` or confirm tracking via `audit_log` only.

---

## GAP-R2-5 | DB-05 sku_gate_status.status includes pending | 2026-03-24

**Description:** Build Pack §1.2 lists `pass`, `fail`, `not_applicable` only; migration `025` (and schema) added `pending` for async validation.

**Action:** Architect: remove `pending` (with data migration) or accept as intentional extension.

---

## GAP-R2-6 | DB-15 ai_audit_results cited_sku_id FK → sku_master | 2026-03-24

**Description:** `cited_sku_id` references legacy `skus(id)`; Master Spec §6.5 expects canonical SKU linkage.

**Action:** Architect: approve FK retarget to `sku_master(id)` or keep legacy. Do not execute without approval.

---

## GAP-R2-7 | skus vs sku_master dual-table FKs | 2026-03-24

**Description:** Multiple tables still FK to `skus` while canonical content lives on `sku_master`.

**Action:** Architect: consolidation plan or documented dual-table pattern.

---

## DB-21 | business_rules row count vs spec “52 rules” | 2026-03-24

**Description:** Cumulative seeds (040, 095, 117, etc.) produce more than 52 rows; additive migrations are legitimate.

**Action:** Baseline `SELECT COUNT(*) FROM business_rules` after full migration chain; tests use deterministic literals — no requirement to call `BusinessRules::get()` from fixtures (self-contained tests).

**Round 3 disposition:** **PASS (additive seeds are spec-compliant).** §5.3 “52 rules” describes the initial seed set, not a hard cap on total rows.

---

## DB-07 | Seed user count (3 rows vs “two users”) | 2026-03-24

**Description:** Validator flags 3 seeded users vs checklist wording “exactly two.”

**Round 3 disposition:** **PASS (matches spec intent).** CIE_v232_Developer_Amendment_Pack_v2.docx §3: two *business* accounts (writer + reviewer) plus admin as system/developer account. Migration `044` pattern matches.

---

## Phase 1 Database — Round 3 final status | 2026-03-24

**Actionable migration:** `120_fix_content_briefs_status_lowercase.sql` (DB-16 status ENUM lowercase). PK rename / `generated_at` rename remain out of scope (architect).

**Closed as PASS (schema / MySQL / spec intent):** DB-01, DB-02, DB-03, DB-04, DB-05 (functional), DB-06, DB-07, DB-08, DB-09, DB-10, DB-11, DB-12, DB-13, DB-14, DB-15 (core + residual FK in GAP), DB-17, DB-20 (DDL; live `UPDATE audit_log` test on deployed DB), DB-21.

**GAP-R3 | Architect queue — decision required (ACCEPT / FIX / DEFER)**

| # | Item | Issue | Risk if changed |
|---|------|-------|-----------------|
| GAP-R3-1 | DB-16 PK name | `content_briefs.id` vs spec `brief_id` | Breaking: queries, model `$primaryKey`, relations |
| GAP-R3-2 | DB-16 timestamp | `created_at` vs spec `generated_at` | Breaking: Eloquent timestamps trait |
| GAP-R3-3 | DB-18 FK | `semrush_content_snapshots.sku_id` → `skus(id)` vs `sku_master(id)` | Medium: data verification; dual-table |
| GAP-R3-4 | DB-19 | `weekly_scores` has no `reviewer_id` | Low: optional column vs `audit_log` |
| GAP-R3-5 | DB-15 FK | `ai_audit_results.cited_sku_id` → `skus(id)` vs `sku_master(id)` | Medium: dual-table |
| GAP-R3-6 | Dual table | Multiple FKs to `skus` vs canonical `sku_master` | High: architecture |

*Prior log entries GAP-R2-1…GAP-R2-7 remain historical; Round 3 uses GAP-R3-1…6 for the architect checklist above.*

---

## Phase 4 Database Reconciliation | 2026-03-24

Reconciles DB-01→DB-21 against repo migrations/seeds (not live DB). Actionable DDL: `120_fix_content_briefs_status_lowercase.sql`, `121_add_landing_page_path_alias.sql`. Models: `UrlPerformance.php`, `SkuTierHistory.php` (SOURCE documentation only).

### AD-1 | intent_vector JSON (not pgvector) | ACCEPTED

Master Spec §6 references VECTOR(1536) for PostgreSQL. CLAUDE.md §5/§9 confirm MySQL stack. JSON is the correct MySQL equivalent. No migration needed.

### AD-2 | Surrogate `id` PK pattern | ACCEPTED

Multiple tables (`sku_master`, `url_performance`, `content_briefs`, etc.) use Laravel surrogate `id` INT/CHAR(36) PK instead of spec-named UUID PKs (`perf_id`, `brief_id`). Functionally equivalent. Business keys preserved as UNIQUE columns. No breaking rename applied.

### AD-3 | sku_gate_status.gate_code VARCHAR(50) | ACCEPTED

Migration `078` widened from ENUM to VARCHAR(50). Values include `G6_1` and `VECTOR` which extend the Build Pack ENUM list. Forward-compatible. See also DB-05.

### AD-4 | sku_gate_status.status includes `pending` | ACCEPTED

Build Pack §1.2 lists pass/fail/not_applicable only. Migration `025` added `pending` for async validation. Removing would break running gate checks. Accepted extension.

### AD-5 | weekly_scores has no reviewer_id | CONFIRMED CORRECT

Amendment Pack §1 and UI Restructure §2 Step 5 define the table without `reviewer_id`. DECISION-003: only one KPI reviewer role; `audit_log` tracks the actor. No column needed.

### AD-6 | Three seed users (admin + writer + reviewer) | CONFIRMED CORRECT

DECISION-003: two daily business users plus admin as system account → three seeded users. Correct.

### AD-7 | business_rules 52→54+ after migration 117 | CONFIRMED CORRECT

Master Spec §5.3 “52 rules” = initial seed. Additive seeds (`095`, `117`, etc.) are spec-compliant; not a hard cap.

### AD-8 | content_briefs.id vs spec `brief_id` | DEFER / GAP-R3-1

Renaming PK is breaking (Eloquent, queries, relations). Leave as-is until architect approves.

### AD-9 | content_briefs.created_at vs spec `generated_at` | DEFER / GAP-R3-2

Renaming breaks Eloquent timestamps trait. Leave as-is until architect approves.

### AD-10 | FKs to legacy `skus` alongside `sku_master` | DEFER / GAP-R3-3/5/6

Dual-table FK retargeting requires data verification and architect consolidation plan. No FK retargets without approval.

### AD-11 | channel_readiness 6-value ENUM | ACCEPTED

Migration `104` added spec four channels (`google_sge`, `amazon`, `ai_assistants`, `own_website`) alongside existing `shopify`/`gmc`. Removing deployment channels is non-additive. All four spec channels present. PASS.

### AD-12 | Kill tier gate applicability | CONFIRMED

Follows Enforcement §2.2 (G1 + G6 + G6.1 for Kill). Per CLAUDE.md §16, Enforcement_Dev_Spec (P4) outranks Doc4b where unranked. Code unchanged; see GAP-AUDIT-012.

### DB-01 FAQ column naming (027 vs Hardening §4.2) | NO DDL

Per historical DB-01 gap: do not rename live columns without architect confirmation. Hardening Addendum (P3) governs target names when a formal rename is approved.

### GAP-5 semrush keyword_difficulty | VERIFY

Migration `116_semrush_imports_spec_columns.sql` adds `keyword_difficulty` additively when legacy `keyword_diff` exists; `064` may have renamed column. Baseline: `DESCRIBE semrush_imports`.

---

## Phase 1 Database — FINAL (Rounds 3–5 converged) | 2026-03-24

**Zero delta** across validation passes Rounds 3–5. **Actionable DDL for DB-16:** `database/migrations/120_fix_content_briefs_status_lowercase.sql` (VARCHAR → lowercase → legacy `cancelled` → spec ENUM). **No other migrations** in this closure.

### Fix 2 — DB-07 | PASS (spec intent) — migration 122 additive

Three seed users (admin + writer + reviewer): Amendment Pack v2 §3 — two *business* accounts + admin as system/developer account. Primary seed remains **`044_seed_v232_writer_reviewer_users.sql`**; **`122_seed_writer_reviewer_users.sql`** idempotently re-seeds writer + reviewer + `user_roles` (same emails as 044) for environments that missed 044 or lost pivot rows.

### Fix 3 — DB-21 | PASS (additive seeds)

`business_rules` row count > 52 after `040` + `095` + `117` etc. is **spec-compliant** (§5.3 describes initial seed, not a hard cap). **No migration.**

### Closed (DB-01–DB-21 summary)

| Category | Count | Notes |
|----------|-------|--------|
| **PASS** | 16 | DB-01–DB-14 (incl. MySQL equivalents), DB-17, DB-20 |
| **DDL applied in repo** | 1 | DB-16 via `120` |
| **Closed without DDL** | 2 | DB-07, DB-21 |
| **Architect queue** | 6 | GAP-R3-1 … GAP-R3-6 (PK/timestamp renames, FK retargets, dual-table) |
| **Live DB only** | 1 | DB-20 — `UPDATE audit_log` must fail (trigger) |

**GAP-R3 (architect: ACCEPT / FIX / DEFER):** GAP-R3-1 (`brief_id`), GAP-R3-2 (`generated_at`), GAP-R3-3 (`semrush_content_snapshots` FK), GAP-R3-4 (`reviewer_id`), GAP-R3-5 (`ai_audit_results.cited_sku_id` FK), GAP-R3-6 (dual-table pattern).

**Update 2026-03-24 (executed in repo):** `122_seed_writer_reviewer_users.sql` — idempotent writer + KPI reviewer + `user_roles` (same emails as `044`). `123_fix_intent_taxonomy_label_width.sql` — `label` VARCHAR(100). `004_seed_test_users.sql` — traceability comments only (no INSERT rows).

---

## GAP-V1 | DB-21 `business_rules` column names vs Master Spec §5.1 | 2026-03-24

**Issue:** Live DDL (`039_create_business_rules_table.sql`) uses `value` and `value_type`; Master Spec §5.1 describes `rule_value`, `data_type`, and additional governance columns (`module`, `label`, `approval_level`, etc.).

**Risk:** HIGH — renaming `value` → `rule_value` breaks `BusinessRulesService` and all seed `INSERT` statements without a coordinated code + migration release.

**Action:** Architect decision only. Options: keep implementation divergence (document as MySQL implementation of §5.2 behaviour), additive alias/generated columns, or approved full rename with full code audit. **Do not run Option C (breaking rename) without approval.**

---

## GAP-V2 | DB-21 `rule_key` naming vs Master Spec §5.3 | 2026-03-24

**Issue:** Seeds use keys such as `gates.vector_similarity_min` and `decay.auto_brief_weeks`; validation prompt cited spec-style names `scoring.vector_similarity_threshold` and `decay.zero_weeks_before_brief`.

**Audit (read-only, `backend/php/src` — 2026-03-24):** Application code calls `BusinessRules::get()` with the **seeded** keys (e.g. `gates.answer_block_min_chars`, `gates.vector_similarity_min` via validators/services, `decay.quorum_minimum`, `decay.auto_brief_weeks`, `tier.*`, `readiness.*`, `chs.*`, `effort.*`, `sync.*`). No references found to `scoring.vector_similarity_threshold` or `decay.zero_weeks_before_brief`.

**Risk:** HIGH if keys renamed without updating every `BusinessRules::get()` call site.

**Action:** Treat as **functional divergence — code and seeds are self-consistent**. Formal spec alignment requires architect + change protocol, not a blind seed `UPDATE rule_key`.

---

## GAP-V3 | DB-18 `semrush_content_snapshots` FK target | 2026-03-24

**Issue:** `075_create_semrush_content_snapshots_table.sql` defines `sku_id` → `skus(id)`, not `sku_master(id)`.

**Risk:** MEDIUM — dual-table consolidation; retargeting requires row mapping and architect approval.

**Action:** No DDL until architect approves; verify all `sku_id` values map to `sku_master.id` before `DROP FOREIGN KEY` / `ADD CONSTRAINT`.

---

## GAP-V4 | DB-16 `content_briefs` FK target | 2026-03-24

**Issue:** `008_create_content_briefs_table.sql` defines `sku_id` → `skus(id)`; Master Spec §6.5 expects canonical `sku_master` linkage.

**Risk:** MEDIUM — same dual-table pattern as GAP-V3.

**Action:** No FK retarget without architect approval and orphan cleanup.

---

## GAP-V5 | Dual table pattern `skus` vs `sku_master` | 2026-03-24

**Issue:** Multiple legacy FKs still reference `skus` while canonical product data lives in `sku_master`.

**Risk:** HIGH architectural — inconsistent source of truth for SKU identity.

**Action:** Single consolidation decision (architect); then apply FK migrations in dependency order (GAP-V3, GAP-V4, cited_sku_id, etc.).

---

## GAP-P8-1 | DEC-03 threshold key naming alias | 2026-03-24

**Issue:** Existing code/seed used `decay.auto_brief_weeks` while spec calls `decay.zero_weeks_before_brief`.

**Risk:** MEDIUM if either key is removed without alias handling.

**Action:** Keep both keys for compatibility during transition; architect to confirm final canonical key name.

---

## GAP-P8-2 | DEC-05 golden question coverage by category | 2026-03-24

**Issue:** Cables 20-question seed added with 8/7/5 distribution; equivalent curated question sets for other categories are not yet seeded.

**Risk:** HIGH for incomplete AI audit coverage across categories.

**Action:** Architect/content owner to provide approved question sets for lampshades, bulbs, pendants, floor_lamps, ceiling_lights, accessories.

---

## GAP-P8-3 | DEC-04 legacy migration syntax vs live schema | 2026-03-24

**Issue:** `030_alter_skus_add_decay_fields.sql` had malformed syntax; corrected in repo, but live DB execution history may differ by environment.

**Risk:** LOW/MEDIUM depending on whether environment already has correct columns from canonical migration path.

**Action:** Verify live DB (`DESCRIBE skus` / `DESCRIBE sku_master`) for `decay_status` + `decay_consecutive_zeros` presence and enum values.

---
