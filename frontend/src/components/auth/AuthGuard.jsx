import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import useStore from '../../store/index';
import { getHomeForRole, isPathAllowedForUser } from '../../lib/authRouting';

/**
 * Route guard: unauthenticated → /login; wrong role for path → role home.
 * Does not modify RBAC middleware; frontend-only.
 */
export default function AuthGuard({ children }) {
    const location = useLocation();
    const user = useStore((state) => state.user);
    const token = useStore((state) => state.token);
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
