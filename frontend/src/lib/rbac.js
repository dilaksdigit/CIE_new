// SOURCE: CIE_v231_Developer_Build_Pack.pdf — RBAC & Permissions Matrix
//         (All 8 roles: ADMIN, SEO_GOVERNOR, CONTENT_EDITOR, CONTENT_LEAD,
//          PRODUCT_SPECIALIST, CHANNEL_MANAGER, FINANCE, AI_OPS)
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §3.1 — RBAC Code box
//         ("existing RBAC middleware with all 8 roles stays in code UNCHANGED")
// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §1.4 — Role definitions

/**
 * Role-Based Access Control (RBAC) — CIE 3.1 / 3.2 Permission Matrix
 * No superuser bypass. Content editors CANNOT override validation gate failures.
 *
 * Roles: ADMIN, SEO_GOVERNOR, CONTENT_EDITOR, CONTENT_LEAD, PRODUCT_SPECIALIST,
 *       CHANNEL_MANAGER, FINANCE, AI_OPS
 *
 * Key restrictions:
 * - Only ADMIN + FINANCE can trigger tier recalculation
 * - Only SEO_GOVERNOR can modify cluster intent statements
 * - Only ADMIN can modify the 9-intent taxonomy
 * - Content editors CANNOT override validation gate failures
 * - KILL-tier SKUs: all edit disabled
 */

const ROLES = {
    ADMIN: 'admin',
    SEO_GOVERNOR: 'seo_governor',
    CONTENT_EDITOR: 'content_editor',
    CONTENT_LEAD: 'content_lead',
    PRODUCT_SPECIALIST: 'product_specialist',
    CHANNEL_MANAGER: 'channel_manager',
    FINANCE: 'finance',
    AI_OPS: 'ai_ops',
};

/** Normalize role from backend (e.g. ADMIN, CONTENT_LEAD) to lowercase snake_case. PORTFOLIO_HOLDER → content_lead */
export function normalizeRole(role) {
    if (!role) return '';
    const r = String(role).toLowerCase().trim().replace(/-/g, '_');
    const legacy = {
        governor: 'seo_governor',
        editor: 'content_editor',
        portfolio_holder: 'content_lead',
        finance_director: 'finance',
    };
    return legacy[r] || r;
}

export function canAccess(user) {
    return !!user && !!normalizeRole(user.role);
}

// --- 3.2 Permission Matrix ---

/** Create/edit content fields. Editor, Prod Spec, Ch Mgr YES; ADMIN has full access; CONTENT_LEAD/Finance NO for content. */
export function canEditContentFields(user, sku) {
    if (!user || !sku) return false;
    if (sku.tier === 'KILL') return false;
    const role = normalizeRole(user.role);
    if (role === ROLES.ADMIN) return true;
    if (role === ROLES.CONTENT_LEAD || role === ROLES.FINANCE) return false;
    return [ROLES.CONTENT_EDITOR, ROLES.PRODUCT_SPECIALIST, ROLES.CHANNEL_MANAGER].includes(role);
}

/** Tier-lock: content_editor can only edit SUPPORT & HARVEST; not HERO. ADMIN has no tier restriction. */
export function canEditContentFieldsForTier(user, sku) {
    if (!canEditContentFields(user, sku)) return false;
    const role = normalizeRole(user.role);
    if (role === ROLES.ADMIN) return true;
    if (role === ROLES.CONTENT_EDITOR && sku && !['SUPPORT', 'HARVEST'].includes(sku.tier)) return false;
    return true;
}

/** Edit expert authority / safety certs. Product Specialist only (Admin has full access elsewhere). */
export function canEditExpertAuthority(user, sku) {
    if (!user || !sku) return false;
    if (sku.tier === 'KILL') return false;
    const role = normalizeRole(user.role);
    return role === ROLES.PRODUCT_SPECIALIST || role === ROLES.ADMIN;
}

/** Assign/change cluster_id. SEO Governor only (Admin has full access). */
export function canAssignCluster(user) {
    if (!user) return false;
    const role = normalizeRole(user.role);
    return role === ROLES.SEO_GOVERNOR || role === ROLES.ADMIN;
}

/** Modify 9-intent taxonomy. ADMIN only (should never happen in practice). */
export function canModifyIntentTaxonomy(user) {
    if (!user) return false;
    return normalizeRole(user.role) === ROLES.ADMIN;
}

export function canProposeTaxonomyChange(user) {
    return normalizeRole(user.role) === ROLES.SEO_GOVERNOR;
}

/** Manual tier change: DUAL sign-off. Portfolio Holder (CONTENT_LEAD) */
export function canApproveTierAsPortfolioHolder(user) {
    if (!user) return false;
    return normalizeRole(user.role) === ROLES.CONTENT_LEAD;
}

export function canApproveTierAsFinance(user) {
    if (!user) return false;
    return normalizeRole(user.role) === ROLES.FINANCE;
}

/** Trigger tier recalculation. ADMIN + FINANCE only. */
export function canTriggerTierRecalculation(user) {
    if (!user) return false;
    const role = normalizeRole(user.role);
    return role === ROLES.ADMIN || role === ROLES.FINANCE;
}

/** Publish SKU / submit for review. Editor, SEO Gov, Ch Mgr, CONTENT_LEAD (PH). */
export function canPublishSku(user, sku) {
    if (!user || !sku) return false;
    if (sku.tier === 'KILL') return false;
    const role = normalizeRole(user.role);
    return [ROLES.CONTENT_EDITOR, ROLES.SEO_GOVERNOR, ROLES.CHANNEL_MANAGER, ROLES.CONTENT_LEAD, ROLES.ADMIN].includes(role);
}

/** Run AI audit. AI Ops, Admin, System. */
export function canRunAIAudit(user) {
    if (!user) return false;
    const role = normalizeRole(user.role);
    return [ROLES.AI_OPS, ROLES.ADMIN].includes(role);
}

/** Manage golden queries. Matrix: Editor, Ch Mgr, AI Ops, PH, Finance, Admin. */
export function canManageGoldenQueries(user) {
    if (!user) return false;
    const role = normalizeRole(user.role);
    return [ROLES.CONTENT_EDITOR, ROLES.CHANNEL_MANAGER, ROLES.AI_OPS, ROLES.CONTENT_LEAD, ROLES.FINANCE, ROLES.ADMIN].includes(role);
}

export function canViewAuditLogs(user) {
    return !!user && canAccess(user);
}

/** Manage users/roles. Admin only. */
export function canManageUsers(user) {
    if (!user) return false;
    return normalizeRole(user.role) === ROLES.ADMIN;
}

/** ERP sync trigger. Finance, Admin, System. */
export function canTriggerERPSync(user) {
    if (!user) return false;
    const role = normalizeRole(user.role);
    return [ROLES.FINANCE, ROLES.ADMIN].includes(role);
}

/** System config (gate thresholds, tier weights). Admin only. */
export function canModifyConfig(user) {
    if (!user) return false;
    return normalizeRole(user.role) === ROLES.ADMIN;
}

export function canViewReadiness(user) {
    return canAccess(user);
}

/** Manage channel mappings. Channel Manager, Admin. */
export function canManageChannelMappings(user) {
    if (!user) return false;
    const role = normalizeRole(user.role);
    return role === ROLES.CHANNEL_MANAGER || role === ROLES.ADMIN;
}

export function canViewTierAssignments(user) {
    return canAccess(user);
}

/** No role can override validation gate failures. */
export function canOverrideGateFailures(user) {
    return false;
}

/** Can user edit anything on this SKU? (content OR expert OR cluster, subject to tier) */
export function canEditSkuAny(user, sku) {
    if (!user || !sku) return false;
    if (sku.tier === 'KILL') return false;
    return canEditContentFieldsForTier(user, sku) || canEditExpertAuthority(user, sku) || canAssignCluster(user);
}

/** Assign/approve briefs + view effort reports. CONTENT_LEAD. */
export function canAssignBriefs(user) {
    if (!user) return false;
    return normalizeRole(user.role) === ROLES.CONTENT_LEAD || normalizeRole(user.role) === ROLES.ADMIN;
}

export default {
    ROLES,
    normalizeRole,
    canAccess,
    canEditContentFields,
    canEditContentFieldsForTier,
    canEditExpertAuthority,
    canAssignCluster,
    canModifyIntentTaxonomy,
    canProposeTaxonomyChange,
    canApproveTierAsPortfolioHolder,
    canApproveTierAsFinance,
    canTriggerTierRecalculation,
    canPublishSku,
    canRunAIAudit,
    canManageGoldenQueries,
    canViewAuditLogs,
    canManageUsers,
    canTriggerERPSync,
    canModifyConfig,
    canViewReadiness,
    canManageChannelMappings,
    canViewTierAssignments,
    canOverrideGateFailures,
    canEditSkuAny,
    canAssignBriefs,
};
