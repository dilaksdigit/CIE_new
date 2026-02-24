import React from 'react';
import { Navigate } from 'react-router-dom';
import useStore from '../../store/index';
import { getHomeForRole } from '../../lib/authRouting';

/** Unauthenticated → /login; authenticated → role home. */
export default function DefaultRedirect() {
    const user = useStore((state) => state.user);
    const token = useStore((state) => state.token);
    const isAuthenticated = !!token;

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }
    return <Navigate to={getHomeForRole(user)} replace />;
}
