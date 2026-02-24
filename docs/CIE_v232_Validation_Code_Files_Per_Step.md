# CIE v2.3.2 — Code Files Required for Each Validation Step

For each step in the **Complete Project Workflow Guide**, use this list when running the corresponding **Cursor Validation Prompt** in LLMSpace2. Paste the listed files (and only those) so the validator can check them against the source documents.

---

## Validation Report Expected Files (Full Submission)

To avoid **BLOCKED** results (“Cursor-Generated Code to Validate section is BLANK” / “no component files submitted”), submit the following files when running the **Step 1 theme migration** (or a full) validation report. Paste these paths and their contents into the validator so all checks (routes, colors, role mapping, gate display, tier counts, backend, auto-publish, RBAC, database, Help tabs, hover states, package.json) can run.

```
frontend/src/theme.js
frontend/src/scripts/darkToLightMap.js
frontend/src/scripts/findReplaceDarkColors.js
frontend/src/styles/globals.css
frontend/src/App.jsx
frontend/src/components/common/UIComponents.jsx
frontend/src/components/common/Sidebar.jsx
frontend/src/components/common/Header.jsx
frontend/src/components/common/Footer.jsx
frontend/src/components/common/Button.jsx
frontend/src/components/common/Input.jsx
frontend/src/components/common/Modal.jsx
frontend/src/components/common/Toast.jsx
frontend/src/pages/WriterQueue.jsx
frontend/src/pages/WriterEdit.jsx
frontend/src/pages/Help.jsx
frontend/src/pages/Dashboard.jsx
frontend/src/pages/Maturity.jsx
frontend/src/pages/AiAudit.jsx
frontend/src/pages/AuditTrail.jsx
frontend/src/pages/StaffKpis.jsx
frontend/src/pages/SkuEdit.jsx
frontend/src/pages/ClustersPage.jsx
frontend/src/pages/Config.jsx
frontend/src/pages/TierMgmt.jsx
frontend/src/pages/BulkOps.jsx
frontend/src/pages/Channels.jsx
frontend/src/components/sku/TierBadge.jsx
frontend/src/components/sku/SkuEditForm.jsx
frontend/src/components/sku/ValidationPanel.jsx
frontend/src/components/auth/Login.jsx
frontend/src/components/auth/AuthGuard.jsx
frontend/src/components/auth/DefaultRedirect.jsx
frontend/src/lib/authRouting.js
frontend/src/lib/tierFieldMap.js
frontend/package.json
backend/php/routes/api.php
backend/php/src/Middleware/RBACMiddleware.php
database/migrations/044_seed_v232_writer_reviewer_users.sql
database/migrations/038_create_weekly_scores_table.sql
```

*If your repo has additional components under `frontend/src/components/` (e.g. audit, brief, cluster) that set colors, include those too. Semrush import migration only if Step 8 is in scope.*

---

## Step 0: Workspace Setup

**Validation type:** Document/workspace only (no code generated yet).

- **Code files:** None. Confirm only that the correct documents are loaded and superseded/archived docs are not loaded.

---

## Step 1: Light Theme Restyle — All Screens

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/styles/globals.css` | Global theme, CSS variables, hex values |
| `frontend/src/App.jsx` | App layout, any root-level background/theme |
| `frontend/src/components/common/UIComponents.jsx` | Shared UI tokens (e.g. C palette), buttons, inputs |
| `frontend/src/components/common/Sidebar.jsx` | Nav background, text, active states |
| `frontend/src/components/common/Header.jsx` | Header background, text colors |
| `frontend/src/components/common/Footer.jsx` | Footer colors |
| `frontend/src/components/common/Button.jsx` | Button colors |
| `frontend/src/components/common/Input.jsx` | Input borders, text |
| `frontend/src/components/common/Modal.jsx` | Modal background, borders |
| `frontend/src/components/common/Toast.jsx` | Toast colors |
| `frontend/src/pages/WriterQueue.jsx` | Writer queue screen colors |
| `frontend/src/pages/WriterEdit.jsx` | Writer edit screen colors |
| `frontend/src/pages/Help.jsx` | Help 3-tab page (restyle only) |
| `frontend/src/pages/Dashboard.jsx` | Review dashboard colors |
| `frontend/src/pages/Maturity.jsx` | Maturity dashboard colors |
| `frontend/src/pages/AiAudit.jsx` | AI Audit screen colors |
| `frontend/src/pages/AuditTrail.jsx` | Audit trail screen colors |
| `frontend/src/pages/StaffKpis.jsx` | Staff KPIs screen colors |
| `frontend/src/pages/SkuEdit.jsx` | SKU edit (if still used) colors |
| `frontend/src/pages/ClustersPage.jsx` | Admin clusters colors |
| `frontend/src/pages/Config.jsx` | Admin config colors |
| `frontend/src/pages/TierMgmt.jsx` | Admin tiers colors |
| `frontend/src/pages/BulkOps.jsx` | Admin bulk-ops colors |
| `frontend/src/pages/Channels.jsx` | Review channels colors |
| `frontend/src/components/sku/TierBadge.jsx` | Tier badge hex (Hero, Support, Harvest, Kill) |
| Any other `frontend/src/**/*.jsx` or `*.css` that set `background`, `color`, `borderColor`, or hex values | Catch-all for theme |

---

## Step 2: Login + Role Routing + Seed Users

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/App.jsx` | Routes, AuthGuard wrapping, redirects |
| `frontend/src/components/auth/Login.jsx` | Login form, no new DB for routing |
| `frontend/src/components/auth/Register.jsx` | Registration (if used) |
| `frontend/src/components/auth/AuthGuard.jsx` | Route guard, role check |
| `frontend/src/components/auth/DefaultRedirect.jsx` | Post-login/default redirect |
| `frontend/src/lib/authRouting.js` | getHomeForRole, isPathAllowedForUser |
| `frontend/src/components/common/Sidebar.jsx` | Nav links by role (writer/reviewer/admin) |
| `frontend/src/store/index.js` | Auth state (user/role) if applicable |
| `backend/php/routes/api.php` | Auth and protected routes (verify no new routing tables) |
| `backend/php/src/Middleware/RBACMiddleware.php` | Verify all 8 roles unchanged |
| `backend/php/src/Enums/RoleType.php` | Role definitions |
| `database/migrations/044_seed_v232_writer_reviewer_users.sql` | 2 seed users (writer, reviewer) |
| `database/migrations/002_create_roles_table.sql` | Roles schema |
| `database/migrations/001_create_users_table.sql` | Users schema |
| `database/seeds/002_seed_roles.sql` | Role seeds (if used) |

---

## Step 3: Writer Queue Screen — /writer/queue

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/pages/WriterQueue.jsx` | Queue layout, sort order, tier badges, stats bar, filters, Kill dimming |
| `frontend/src/services/api.js` | queue/today, skus, dashboard/summary or stats calls |
| `frontend/src/App.jsx` | Route `/writer/queue` |
| `frontend/src/components/common/Sidebar.jsx` | Writer nav entry to queue |
| `frontend/src/components/sku/TierBadge.jsx` | Tier badge colors and labels |
| `backend/php/routes/api.php` | `/v1/queue/today`, `/skus`, dashboard/stats |
| `backend/php/src/Controllers/SkuController.php` | queueToday, index, stats |
| `backend/php/src/Controllers/DashboardController.php` | summary (if stats from here) |

---

## Step 4: Writer Edit Screen + Gate Hints — /writer/edit/:skuId

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/pages/WriterEdit.jsx` | 70/30 layout, tier banner, field cards, borders, hints, submit, publish redirect |
| `frontend/src/services/api.js` | get SKU, validate, publish/update |
| `frontend/src/App.jsx` | Route `/writer/edit/:skuId` |
| `frontend/src/components/sku/SkuEditForm.jsx` | Field cards, character counters, border colors |
| `frontend/src/components/sku/ValidationPanel.jsx` | Gate result display (no G1–G7 codes) |
| `frontend/src/components/sku/TierLockBanner.jsx` | Tier banner text and time guide |
| `frontend/src/lib/tierFieldMap.js` | Tier → field count (Hero 6, Support 5, Harvest 1, Kill 0) |
| `backend/php/routes/api.php` | `skus/{id}`, `skus/{id}/validate`, publish/update |
| `backend/php/src/Controllers/SkuController.php` | show, update |
| `backend/php/src/Controllers/ValidationController.php` | validate |
| `backend/php/src/Services/ValidationService.php` | validateSku, gate orchestration |
| `backend/php/src/Validators/GateValidator.php` | Gate runner |
| `backend/php/src/Validators/GateResult.php` | Result shape (for reference only) |
| `backend/php/src/Validators/Gates/G1_BasicInfoGate.php` | Gate behaviour (reference) |
| `backend/php/src/Validators/Gates/G2_IntentGate.php` | (reference) |
| `backend/php/src/Validators/Gates/G3_SecondaryIntentGate.php` | (reference) |
| `backend/php/src/Validators/Gates/G4_AnswerBlockGate.php` | (reference) |
| `backend/php/src/Validators/Gates/G4_VectorGate.php` | (reference) |
| `backend/php/src/Validators/Gates/G5_TechnicalGate.php` | (reference) |
| `backend/php/src/Validators/Gates/G6_CommercialPolicyGate.php` | (reference) |
| `backend/php/src/Validators/Gates/G7_ExpertGate.php` | (reference) |

---

## Step 5: AI Suggestions Panel — Right Column (30%)

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/pages/WriterEdit.jsx` | Right panel (30%), 4 card types, collapsible, empty state |
| `frontend/src/services/api.js` | Existing audit, Semrush, GA/suggestion endpoints only |
| `docs/api/openapi.yaml` | Confirm no new endpoints (reference) |
| `backend/php/routes/api.php` | Confirm no new suggestion routes (reference) |

*No new backend controllers or routes; validation checks use of existing APIs only.*

---

## Step 6: KPI Reviewer View + Weekly Scoring

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/pages/StaffKpis.jsx` | /review/kpis, weekly score input, notes, trend line |
| `frontend/src/pages/Dashboard.jsx` | /review/dashboard |
| `frontend/src/pages/Maturity.jsx` | /review/maturity |
| `frontend/src/pages/AiAudit.jsx` | /review/ai-audit |
| `frontend/src/pages/Channels.jsx` | /review/channels |
| `frontend/src/App.jsx` | All `/review/*` routes |
| `frontend/src/components/common/Sidebar.jsx` | Reviewer nav |
| `frontend/src/lib/authRouting.js` | Reviewer path allow/block |
| `backend/php/routes/api.php` | audit-results/weekly-scores GET/POST |
| `backend/php/src/Controllers/DashboardController.php` | weeklyScores, storeWeeklyScore |
| `database/migrations/038_create_weekly_scores_table.sql` | weekly_scores schema |

---

## Step 7: Help Pages + Admin Screens

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/pages/Help.jsx` | 3 tabs at /help/flow, /help/gates, /help/roles; restyle only |
| `frontend/src/App.jsx` | /help/* and /admin/* routes |
| `frontend/src/components/common/Sidebar.jsx` | Help icon (?) for writer and reviewer, admin links |
| `frontend/src/pages/ClustersPage.jsx` | /admin/clusters |
| `frontend/src/pages/Config.jsx` | /admin/config |
| `frontend/src/pages/TierMgmt.jsx` | /admin/tiers |
| `frontend/src/pages/AuditTrail.jsx` | /admin/audit-trail |
| `frontend/src/pages/BulkOps.jsx` | /admin/bulk-ops |
| `backend/php/routes/api.php` | Admin-only route protection |
| `backend/php/src/Middleware/RBACMiddleware.php` | ADMIN guard |

---

## Step 8: Semrush Import Admin Screen — /admin/semrush-import

**Relevant code files:**

| File | Purpose |
|------|--------|
| `frontend/src/pages/SemrushImport.jsx` | Or `frontend/src/pages/admin/SemrushImport.jsx` — 6 zones, CSV upload, validation, history |
| `frontend/src/App.jsx` | Route `/admin/semrush-import` |
| `frontend/src/services/api.js` | Semrush import API calls (upload, history, delete batch) |
| `frontend/src/components/common/Sidebar.jsx` | Admin nav entry to Semrush Import |
| `backend/php/routes/api.php` | Semrush import route, ADMIN only |
| Backend controller for Semrush import (e.g. `backend/php/src/Controllers/SemrushImportController.php`) | If present |
| `database/migrations/*_create_semrush_imports_table.sql` | semrush_imports table (id, import_batch, keyword, position, etc., indexes) |

*If the Semrush screen or migration does not exist yet, list the files that the spec says must exist so LLMSpace2 can report FAIL until they are created.*

---

## Step 9: Full System Validation

**Relevant code files:** All of the files listed in Steps 1–8, plus:

| File | Purpose |
|------|--------|
| `backend/php/src/Enums/RoleType.php` | All 8 roles present |
| `backend/php/src/Middleware/RBACMiddleware.php` | Unchanged RBAC |
| `docs/api/openapi.yaml` or `docs/api/cie_v231_openapi.yaml` | No new endpoints beyond spec |
| `database/migrations/*.sql` | Only weekly_scores and semrush_imports as new tables |
| `frontend/package.json` | No new npm packages vs source docs |

---

## Quick reference: steps → file count focus

| Step | Focus |
|------|--------|
| 0 | No code files |
| 1 | Theme: globals.css, UIComponents, all pages and common components that use color |
| 2 | Auth: Login, AuthGuard, DefaultRedirect, authRouting, Sidebar, RBAC middleware, seed migration |
| 3 | WriterQueue, api (queue/skus/stats), SkuController, DashboardController |
| 4 | WriterEdit, SkuEditForm, ValidationPanel, tierFieldMap, ValidationController, ValidationService, GateValidator, Gates/* |
| 5 | WriterEdit (right panel), api.js, openapi.yaml (no new endpoints) |
| 6 | StaffKpis, Dashboard, Maturity, AiAudit, Channels, App, Sidebar, authRouting, DashboardController weeklyScores, 038 migration |
| 7 | Help, App, Sidebar, ClustersPage, Config, TierMgmt, AuditTrail, BulkOps, api.php, RBACMiddleware |
| 8 | SemrushImport page, App, api.js, Semrush route/controller, semrush_imports migration |
| 9 | All of the above + RoleType, RBACMiddleware, openapi, migrations, package.json |
