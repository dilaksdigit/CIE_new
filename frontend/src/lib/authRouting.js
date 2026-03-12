// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §1.4 — Role-Based Login Routing
//         (CONTENT_EDITOR → /writer/queue, CONTENT_LEAD → /review/dashboard,
//          ADMIN → /admin/clusters)
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §3.1 — RBAC Code box
//         (2 seed accounts: writer=[CONTENT_EDITOR+PRODUCT_SPECIALIST],
//          reviewer=[CONTENT_LEAD+SEO_GOVERNOR])
// SOURCE: CIE_v232_Developer_LLM_Workspace_Guide.docx §Trap 3 — Role-based routing
//         (frontend routing logic only; do NOT modify RBAC middleware or DB tables)

/**
 * Role-based post-login redirect and route guard helpers.
 * Uses only: CONTENT_EDITOR, CONTENT_LEAD, ADMIN, PRODUCT_SPECIALIST, SEO_GOVERNOR.
 * No role names exposed in UI; no gate codes.
 */

function normalizeRole(roleLike) {
    if (!roleLike) return '';

    // Accept raw role string or role-like object (e.g. { name }).
    let rawRole = roleLike;
    if (typeof roleLike === 'object') {
        rawRole = roleLike.name || roleLike.role || '';
    }

    const role = String(rawRole || '').toLowerCase().trim().replace(/-/g, '_');
    if (role === 'editor') return 'content_editor';
    if (role === 'governor') return 'seo_governor';
    if (role === 'portfolio_holder') return 'content_lead';
    return role;
}

function getUserRoles(user) {
    if (!user) return [];
    // Prefer roles array from backend (multi-role writers/reviewers)
    if (Array.isArray(user.roles) && user.roles.length > 0) {
        return user.roles.map(normalizeRole).filter(Boolean);
    }
    // Fallback: single role (backend may send only user.role, or old session)
    const single =
        (typeof user.role === 'object' && user.role !== null)
            ? (user.role.name || user.role.role || '')
            : (user.role ?? '');
    const normalized = normalizeRole(single);
    return normalized ? [normalized] : [];
}

const hasRole = (user, ...targetRoles) => {
    if (!user) return false;
    const userRoles = getUserRoles(user);
    if (userRoles.length === 0) return false;

    const targets = targetRoles.map(normalizeRole);
    return userRoles.some(r => targets.includes(r));
};

/**
 * Get the permitted home route for the user's role(s).
 * CONTENT_EDITOR or PRODUCT_SPECIALIST → /writer/queue
 * CONTENT_LEAD or SEO_GOVERNOR → /review/dashboard
 * ADMIN → /admin/clusters
 * Other roles → /help (single allowed route)
 */
export function getHomeForRole(user) {
    if (!user) return '/login';
    if (hasRole(user, 'CONTENT_EDITOR', 'PRODUCT_SPECIALIST')) return '/writer/queue';
    if (hasRole(user, 'CONTENT_LEAD', 'SEO_GOVERNOR')) return '/review/dashboard';
    if (hasRole(user, 'ADMIN')) return '/admin/clusters';
    return '/help';
}

/**
 * Check if the user is allowed to access the given path.
 * writer: only /writer/* and /help/*
 * reviewer: only /review/* and /help/*
 * admin: full access
 * other roles: only /help/*
 */
export function isPathAllowedForUser(user, pathname) {
    if (!user) return false;
    const path = pathname.replace(/\/$/, '') || '/';

    if (hasRole(user, 'CONTENT_EDITOR', 'PRODUCT_SPECIALIST')) {
        return path.startsWith('/writer') || path.startsWith('/help');
    }
    if (hasRole(user, 'CONTENT_LEAD', 'SEO_GOVERNOR')) {
        return path.startsWith('/review') || path.startsWith('/help');
    }
    if (hasRole(user, 'ADMIN')) {
        return true;
    }
    return path.startsWith('/help');
}

/**
 * Nav group for Sidebar: 'writer' | 'reviewer' | 'admin' | 'other'.
 * Use so sidebar and route guards stay in sync.
 */
export function getNavGroupForUser(user) {
    if (!user) return 'other';
    if (hasRole(user, 'CONTENT_EDITOR', 'PRODUCT_SPECIALIST')) return 'writer';
    if (hasRole(user, 'CONTENT_LEAD', 'SEO_GOVERNOR')) return 'reviewer';
    if (hasRole(user, 'ADMIN')) return 'admin';
    return 'other';
}
