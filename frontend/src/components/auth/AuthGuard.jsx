import React, { useContext } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { AppContext } from '../../App';
import { getHomeForRole, isPathAllowedForUser } from '../../lib/authRouting';

/**
 * Route guard: unauthenticated → /login; wrong role for path → role home.
 * Does not modify RBAC middleware; frontend-only.
 */
export default function AuthGuard({ children }) {
    const location = useLocation();
    const { user, token } = useContext(AppContext);
    const isAuthenticated = !!token;

    if (!isAuthenticated || !user) {
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    if (!isPathAllowedForUser(user, location.pathname)) {
        const home = getHomeForRole(user);
        return <Navigate to={home} replace />;
    }

    return children;
}
