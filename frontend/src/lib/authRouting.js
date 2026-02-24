/**
 * Role-based post-login redirect and route guard helpers.
 * Uses only: CONTENT_EDITOR, CONTENT_LEAD, ADMIN, PRODUCT_SPECIALIST, SEO_GOVERNOR.
 * No role names exposed in UI; no gate codes.
 */

function normalizeRole(userOrRole) {
    if (!userOrRole) return '';

    // Accept raw role string, role object, or full user object.
    let rawRole = userOrRole;
    if (typeof userOrRole === 'object') {
        rawRole =
            userOrRole.role?.name ||
            userOrRole.role ||
            (Array.isArray(userOrRole.roles) && userOrRole.roles.length > 0 ? userOrRole.roles[0] : '');
    }

    const role = String(rawRole || '').toLowerCase().trim().replace(/-/g, '_');
    if (role === 'editor') return 'content_editor';
    if (role === 'governor') return 'seo_governor';
    if (role === 'portfolio_holder') return 'content_lead';
    return role;
}

/**
 * Get the permitted home route for the user's role(s).
 * CONTENT_EDITOR or PRODUCT_SPECIALIST → /writer/queue
 * CONTENT_LEAD or SEO_GOVERNOR → /review/dashboard
 * ADMIN → /admin/clusters
 * Other roles → /help/flow (single allowed route)
 */
export function getHomeForRole(user) {
    if (!user) return '/login';
    const role = normalizeRole(user);
    if (role === 'content_editor' || role === 'product_specialist') return '/writer/queue';
    if (role === 'content_lead' || role === 'seo_governor') return '/review/dashboard';
    if (role === 'admin') return '/admin/clusters';
    return '/help/flow';
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
    const role = normalizeRole(user);
    const path = pathname.replace(/\/$/, '') || '/';

    if (role === 'content_editor' || role === 'product_specialist') {
        return path.startsWith('/writer') || path.startsWith('/help');
    }
    if (role === 'content_lead' || role === 'seo_governor') {
        return path.startsWith('/review') || path.startsWith('/help');
    }
    if (role === 'admin') {
        return true;
    }
    return path.startsWith('/help');
}
