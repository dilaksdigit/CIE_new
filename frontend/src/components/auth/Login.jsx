// SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 1
import React, { useState, useEffect, useContext } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { AppContext } from '../../App';
import { authApi } from '../../services/api';
import { getHomeForRole } from '../../lib/authRouting';

/** Singular `user.role` for rbac.js; prefer higher-privilege role when user has many (matches backend role list). */
function pickPrimaryRoleForSession(roles) {
    if (!Array.isArray(roles) || roles.length === 0) return '';
    const priority = [
        'ADMIN',
        'SEO_GOVERNOR',
        'CONTENT_LEAD',
        'PRODUCT_SPECIALIST',
        'CONTENT_EDITOR',
        'CHANNEL_MANAGER',
        'FINANCE',
        'AI_OPS',
    ];
    for (const p of priority) {
        if (roles.includes(p)) return p;
    }
    return roles[0];
}

const Login = () => {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [loading, setLoading] = useState(false);

    const navigate = useNavigate();
    const location = useLocation();
    const { login } = useContext(AppContext);

    useEffect(() => {
        if (location.state?.message) {
            setSuccess(location.state.message);
        }
    }, [location]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await authApi.login(username, password);
            const data = response.data || {};
            const token = data.token;
            const roles = Array.isArray(data.roles) ? data.roles : [];
            if (!token || !data.user_id) {
                setError('Invalid credentials. Please try again.');
                return;
            }
            const user = {
                id: data.user_id,
                email: username.trim(),
                roles,
                role: pickPrimaryRoleForSession(roles),
                name: username.trim().split('@')[0] || 'User',
            };
            login(user, token);
            navigate(data.redirect_to || getHomeForRole(user), { replace: true });
        } catch (err) {
            const msg = err.response?.data?.message;
            setError(typeof msg === 'string' ? msg : 'Invalid credentials. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="login-page">
            <div className="login-box">
                <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 16 }}>
                    <div
                        className="sidebar-logo-icon"
                        style={{ width: 42, height: 42, fontSize: '1.2rem', borderRadius: 6 }}
                    >
                        C
                    </div>
                </div>
                <h2>CIE Global Content</h2>
                <div className="login-sub">Secure access</div>

                {error && <div className="error-msg">{error}</div>}
                {success && (
                    <div
                        style={{
                            padding: 10,
                            background: 'var(--green-bg)',
                            border: '1px solid var(--green)',
                            borderRadius: 4,
                            color: 'var(--green)',
                            marginBottom: 14,
                            fontSize: '0.85rem',
                        }}
                    >
                        {success}
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <div className="mb-14">
                        <label className="field-label">Username</label>
                        <input
                            type="text"
                            className="field-input"
                            placeholder="Enter username"
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            required
                            autoComplete="username"
                        />
                    </div>
                    <div className="mb-20">
                        <label className="field-label">Password</label>
                        <input
                            type="password"
                            className="field-input"
                            placeholder="••••••••"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                            autoComplete="current-password"
                        />
                    </div>

                    <button type="submit" className="btn btn-primary" disabled={loading}>
                        {loading ? 'Signing in...' : 'Sign in'}
                    </button>
                </form>
            </div>
        </div>
    );
};

export default Login;
