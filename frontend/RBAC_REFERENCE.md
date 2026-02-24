# CIE Project — RBAC: Who Can Access Which Action

This document reflects the permissions implemented in `frontend/src/lib/rbac.js`.  
**KILL-tier SKUs:** All edit permissions are revoked for every role.  
**Gate overrides:** No role can bypass validation gate failures.

---

## 1. By action → who can do it

| Action | Roles that can do it | Notes |
|--------|----------------------|--------|
| **Edit content fields** (title, descriptions, answer block, best-for, not-for) | content_editor, product_specialist*, channel_manager* | content_editor: **SUPPORT & HARVEST only**. PH & Finance = NO. *Category-bound (not enforced in UI yet). |
| **Edit expert authority / safety certs** (G7) | product_specialist | Only this role. |
| **Assign / change cluster_id** (G1) | seo_governor | Only this role. |
| **Modify / propose intent taxonomy** (REVIEW*) | seo_governor | SEO Gov proposes; quarterly Commercial Director review to activate. **Admin = NO.** |
| **Approve tier change (Portfolio Holder)** | portfolio_holder | Part of **dual sign-off**. |
| **Approve tier change (Finance)** | finance | Part of **dual sign-off**. |
| **Trigger tier recalculation** | admin, finance | Only these two. |
| **Publish SKU / Submit for review** | content_editor, seo_governor, channel_manager, portfolio_holder | Admin **cannot** publish. |
| **Run AI audit** | ai_ops, admin, system | |
| **Manage golden queries** | seo_governor, ai_ops | |
| **View audit logs** | All except viewer | (Scope OWN/ALL/CAT not yet enforced in UI.) |
| **Manage users / roles** | admin | |
| **Trigger ERP sync** | finance, admin, system | |
| **Modify system config** (Config page) | admin | Gate thresholds, tier weights, etc. |
| **View readiness** | Any authenticated user | |
| **Manage channel mappings** | channel_manager | |
| **View tier assignments** | Any authenticated user | |
| **Override gate failures** | **Nobody** | No role can bypass gates. |
| **Access app (dashboard, read-only views)** | Any role including viewer | |

---

## 2. By role → what they can do

| Role | Can do |
|------|--------|
| **content_editor** | Edit content fields on **SUPPORT & HARVEST** SKUs only; publish SKU; view readiness, tier assignments, audit logs. **Cannot** edit HERO, cluster, expert authority, config, users, or trigger ERP/tier recalc. |
| **product_specialist** | Edit **expert authority & safety certs** (G7); edit content fields (tier-locked like editor in current UI); view readiness, audit logs. **Cannot** assign cluster, modify taxonomy, approve tier, run audit, manage config/users/ERP. |
| **seo_governor** | **Assign/change cluster_id**; propose taxonomy changes; manage golden queries; publish SKU; view audit logs, readiness. **Cannot** edit content fields or expert authority, modify config, approve tier alone, or trigger ERP/tier recalc. |
| **channel_manager** | Edit content fields (subject to tier); **manage channel mappings**; view readiness; publish SKU; view audit logs. **Cannot** assign cluster, edit expert authority, run audit, manage config/users, or approve tier. |
| **ai_ops** | **Run AI audit**; manage golden queries; view audit logs, readiness, decay monitor. **Cannot** edit content, cluster, or config; cannot approve tier or trigger ERP. |
| **portfolio_holder** | **Approve tier change (PH half of dual sign-off)**; publish SKU; view audit logs, tier assignments, readiness. **Cannot** edit content, assign cluster, edit expert authority, run audit, modify config, or trigger ERP/tier recalc. |
| **finance** | **Approve tier change (Finance half of dual sign-off)**; **trigger tier recalculation**; **trigger ERP sync**; view tier assignments, audit logs, readiness. **Cannot** edit SKU content, cluster, or config; cannot run audit or manage users. |
| **admin** | **Modify system config**; **manage users/roles**; trigger tier recalculation; trigger ERP sync; run AI audit; view audit logs, readiness, tier assignments. **Cannot** edit SKU content, assign cluster, or publish SKU. **No superuser bypass.** |
| **system** | Run AI audit; trigger ERP sync (automated). No human login. |
| **viewer** | **Read-only:** view dashboard, SKU details, readiness, tier assignments. **Cannot** edit anything, approve tier, run audit, or change config. |

---

## 3. Critical rules (enforced in code)

1. **No superuser:** Admin cannot edit SKU content or bypass gates.
2. **KILL tier:** All edit permissions are revoked for every role.
3. **Content editors cannot override gates:** Submit is blocked if required gates (e.g. G2, G4) are not passing.
4. **Manual tier change:** Requires **both** Portfolio Holder **and** Finance approval (dual sign-off).
5. **Tier recalculation:** Only Admin and Finance can trigger it.
6. **Cluster intent / taxonomy:** Only SEO Governor can assign cluster and propose taxonomy changes (REVIEW*); Admin cannot modify taxonomy.

---

## 4. Where it’s used in the app

| Page / area | Permission(s) used |
|-------------|--------------------|
| **SkuEdit** | `canEditSkuAny`, `canEditContentFieldsForTier`, `canEditExpertAuthority`, `canAssignCluster`, `canPublishSku` |
| **Config** | `canModifyConfig` |
| **Tier Mgmt** | `canApproveTierAsPortfolioHolder`, `canApproveTierAsFinance`, `canTriggerTierRecalculation` |
| **AI Audit** | `canRunAIAudit` |
| **Register** | Role dropdown uses these role values (e.g. `content_editor`, `seo_governor`) |

Source of truth: `frontend/src/lib/rbac.js`.

---

## 5. Verification vs 3.2 Permission Matrix

| Action | Editor | Prod Spec | SEO Gov | Ch Mgr | AI Ops | PH | Finance | Admin | System | Implemented |
|--------|--------|-----------|---------|--------|--------|-----|---------|-------|--------|-------------|
| Create/edit content fields | YES | YES* | NO | YES* | NO | NO | NO | NO | NO | ✓ `canEditContentFields` (PH/Finance excluded) |
| Edit expert authority | NO | YES | NO | NO | NO | NO | NO | NO | NO | ✓ `canEditExpertAuthority` |
| Assign/change cluster_id | NO | NO | YES | NO | NO | NO | NO | NO | NO | ✓ `canAssignCluster` |
| Modify intent taxonomy | NO | NO | REVIEW* | NO | NO | NO | NO | NO | NO | ✓ `canModifyIntentTaxonomy` (SEO Gov only) |
| Change SKU tier (manual) | - | - | - | - | - | DUAL | DUAL | NO | AUTO | ✓ `canApproveTierAsPortfolioHolder`, `canApproveTierAsFinance` |
| Publish SKU | YES | NO | YES | YES | NO | YES | NO | NO | NO | ✓ `canPublishSku` |
| Run AI audit | NO | NO | NO | NO | YES | NO | NO | YES | YES | ✓ `canRunAIAudit` |
| Manage golden queries | NO | NO | YES | NO | YES | NO | NO | NO | NO | ✓ `canManageGoldenQueries` |
| View audit logs | OWN | OWN | ALL | OWN | ALL | CAT | ALL | ALL | - | ✓ `canViewAuditLogs` (scope not yet enforced) |
| Manage users/roles | NO | NO | NO | NO | NO | NO | NO | YES | NO | ✓ `canManageUsers` |
| ERP sync trigger | NO | NO | NO | NO | NO | NO | YES | YES | YES | ✓ `canTriggerERPSync` |

**Critical rules enforced:** (1) No superuser — Admin cannot edit content or bypass gates. (2) Content editors cannot change cluster_id, tier, or intent taxonomy. (3) KILL-tier revokes all edit. (4) Gate overrides disabled (`canOverrideGateFailures` = false). (5) Dual tier approval = PH + Finance.
