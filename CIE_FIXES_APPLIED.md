# CIE v2.3.2 — Fixes Applied (Cursor Session)

**Authority:** CLAUDE.md Section 16. All changes trace to the Cursor Fix Prompt and validation report.

---

## Completed fix blocks

| Block | Item | Action |
|-------|------|--------|
| 1 | HR-01 Amazon | Removed from UIComponents.jsx, OpenAPI, migration 062 (channel enum shopify/gmc only). |
| 2 | HR-03 Similarity | Removed similarity_score field from SkuEditForm.jsx; writers never see numeric value. |
| 3 | HR-07 API routes | All routes documented in cie_v231_openapi.yaml; no new endpoints added. |
| 4 | DB-01 sku_faq_responses | Migration 063 renames sku_faqs → sku_faq_responses. |
| 5 | DB-06 semrush_imports | Migration 064 adds intent, sku_code, cluster_id; renames keyword_diff→keyword_difficulty, url→competitor_url; FK cluster_id. SemrushImportController insert uses new column names. |
| 6 | DB-08 Tier formula | TierCalculationService + TierController erpSync use exact formula with max(cppc,0.001), max(velocity,0.001). |
| 7 | GATE-03 G2 | G2_IntentGate returns error_code `CIE_G2_INVALID_INTENT`. |
| 8 | GATE-04 G3 | G3_SecondaryIntentGate: Hero max 3, Support max 2 secondary intents. |
| 9 | GATE-07 G6 null tier | G6_CommercialPolicyGate first step returns CIE_G6_MISSING_TIER when tier is null. |
| 10 | GATE-08 Kill | G6_CommercialPolicyGate Kill arm returns CIE_G6_1_KILL_EDIT_BLOCKED. |
| 11 | UI-01 15 routes | App.jsx: 15 routes; help consolidated to single /help. |
| 12 | UI-02 Theme | globals.css hex values per CLAUDE.md Section 8; table header #1F2D54 + white text. |
| 13 | UI-03 Tier badges | Harvest text #B8860B, Kill bg #FDEEEB; TierBadge icon removed. |
| 14 | UI-08 No emoji | TierBadge, SkuEditForm, WriterEdit, SemrushImport, Config, SkuEdit — all emoji removed. |
| 15 | RBAC-04 403 audit | RBACMiddleware logs permission_denied to audit_log; >5 in 24h triggers admin_alert_permission_abuse. |
| 16 | RBAC-05 Dual sign-off | Migration 065 creates tier_change_requests table. Two-step approval workflow (portfolio_holder → finance) must be implemented in TierController when applying manual tier overrides; table is ready. |
| 17 | GATE-12 / CHAN-01 | ChannelDeployService added; SkuController::publish calls deployAfterPublish (Shopify 2/sec throttle, GMC stub). channels_updated from deploy result. |
| 18 | CHAN-02 GMC rules | ChannelGovernorService::isEligibleForGMC() added: Kill/Harvest excluded; Hero ≥85, Support ≥70. assess() now returns shopify/gmc only (Amazon removed). |
| 19 | SEM-04 Quick Wins | GET /admin/semrush-import/latest?filter=quick_wins implemented; SemrushImport.jsx has Quick Wins toggle. |
| 20 | AUDIT-05 Cron | sync.ai_audit_cron_schedule set to `0 6 * * 1` (Monday 06:00 UTC). Migration 066 updates existing DB; 040 seed updated for new installs. |
| 21 | Golden tests | golden_test_data.json aligned with CIE_Doc4b (10 SKUs: CBL-BLK-3C-1M, CBL-GLD-3C-1M, CBL-WHT-2C-3M, CBL-RED-3C-2M, SHD-TPE-DRM-35, SHD-GLS-CNE-20, BLB-LED-E27-4W, BLB-LED-B22-8W, PND-SET-BRS-3L, FLR-ARC-BLK-175). validate_golden_skus.php updated for Kill/Harvest SKU codes and shopify/gmc channels. GmcFeedService added for CHAN-02 feed inclusion (logs excluded SKUs to audit_log). |

---

## GAPs (escalate to architect)

1. **Fix Block 16 (RBAC-05):** `tier_change_requests` table exists (065). TierController does not yet create/update tier_change_requests or require portfolio_holder + finance approval before applying a manual tier change. Implement: on manual override request → insert pending_portfolio_approval; portfolio_holder approves → pending_finance_approval; finance approves → apply tier + write both approvers to audit_log.
2. **Fix Block 20 (AUDIT-05):** ~~AI audit cron schedule~~ — Done: set to `'0 6 * * 1'` (migration 066 + 040 seed).
3. **Fix Block 21 (Golden tests):** ~~golden_test_data.json~~ — Done: 10 pack SKUs in golden_test_data.json; validator script updated.

---

## Verification checklist (self-check)

- No Amazon in frontend, OpenAPI, or DB channel enum.
- similarity_score not shown to writers.
- API routes in api.php documented in OpenAPI.
- sku_faq_responses used after migration 063.
- semrush_imports has intent, sku_code, cluster_id, keyword_difficulty, competitor_url; controller uses them.
- Tier formula: log10(velocity)×25×0.20 and (1/cppc)×10×0.25 with safe guards.
- G2 CIE_G2_INVALID_INTENT; G3 Support max 2; G6 null tier CIE_G6_MISSING_TIER; G6 Kill CIE_G6_1_KILL_EDIT_BLOCKED.
- App.jsx has 15 routes; theme hex and table header; Harvest/Kill colours; no emoji.
- 403 → audit_log; >5 denials → admin_alert_permission_abuse.
- Manual tier override: table ready; workflow to be implemented.
- Publish calls ChannelDeployService; GMC eligibility in ChannelGovernorService; Quick Wins filter and UI.
- audit_log: INSERT only; no UPDATE/DELETE in new code.
- No new npm packages; no new API endpoints (except specced semrush-import).

---

*CIE v2.3.2 | Cursor Fix Prompt | March 2026*
