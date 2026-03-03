import React, { useContext } from 'react';
import { Navigate } from 'react-router-dom';
import { AppContext } from '../../App';
import { getHomeForRole } from '../../lib/authRouting';

/** Unauthenticated → /login; authenticated → role home. */
export default function DefaultRedirect() {
    const { user, token } = useContext(AppContext);
    const isAuthenticated = !!token;

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }
    return <Navigate to={getHomeForRole(user)} replace />;
}
