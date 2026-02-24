import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import useStore from '../../store/index';
import { authApi } from '../../services/api';

const Register = () => {
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'content_editor'
    });
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const navigate = useNavigate();

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        // Validate passwords match
        if (formData.password !== formData.password_confirmation) {
            setError('Passwords do not match');
            setLoading(false);
            return;
        }

        try {
            const response = await authApi.register(
                formData.name,
                formData.email,
                formData.password,
                formData.password_confirmation,
                formData.role
            );
            
            // Account created successfully, redirect to login
            setError('');
            navigate('/login', { state: { message: 'Account created successfully! Please log in.' } });
        } catch (err) {
            console.error('Registration failed:', err);
            const msg = err.response?.data?.error || 'Registration failed. Please try again.';
            setError(msg);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="login-page">
            <div className="login-box" style={{ maxWidth: 420 }}>
                <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 16 }}>
                    <div className="sidebar-logo-icon" style={{ width: 42, height: 42, fontSize: '1.2rem', borderRadius: 6 }}>C</div>
                </div>
                <h2>Create Account</h2>
                <div className="login-sub">CIE v2.3.2 — Sign up for secure access</div>

                {error && <div className="error-msg" style={{ marginBottom: 14 }}>{error}</div>}

                <form onSubmit={handleSubmit}>
                    <div className="mb-14">
                        <label className="field-label">Full Name</label>
                        <input
                            type="text"
                            className="field-input"
                            placeholder="John Doe"
                            name="name"
                            value={formData.name}
                            onChange={handleChange}
                            required
                        />
                    </div>

                    <div className="mb-14">
                        <label className="field-label">Organization Email</label>
                        <input
                            type="email"
                            className="field-input"
                            placeholder="name@company.com"
                            name="email"
                            value={formData.email}
                            onChange={handleChange}
                            required
                        />
                    </div>

                    <div className="mb-14">
                        <label className="field-label">Password</label>
                        <input
                            type="password"
                            className="field-input"
                            placeholder="••••••••"
                            name="password"
                            value={formData.password}
                            onChange={handleChange}
                            required
                        />
                    </div>

                    <div className="mb-14">
                        <label className="field-label">Confirm Password</label>
                        <input
                            type="password"
                            className="field-input"
                            placeholder="••••••••"
                            name="password_confirmation"
                            value={formData.password_confirmation}
                            onChange={handleChange}
                            required
                        />
                    </div>

                    <div className="mb-20">
                        <label className="field-label">Account Role</label>
                        <select
                            className="field-input"
                            name="role"
                            value={formData.role}
                            onChange={handleChange}
                            required
                        >
                            <option value="content_editor">Content Editor (Edit SKU content, SUPPORT/HARVEST)</option>
                            <option value="product_specialist">Product Specialist (Expert authority & safety certs)</option>
                            <option value="seo_governor">SEO Governor (Clusters, lock/unlock taxonomy, approve cluster changes)</option>
                            <option value="channel_manager">Channel Manager (Readiness, channel mappings)</option>
                            <option value="ai_ops">AI Ops (Run audits, decay monitor, golden queries)</option>
                            <option value="content_lead">Content Lead (Edit + approve + assign briefs + effort reports)</option>
                            <option value="portfolio_holder">Portfolio Holder (Same as Content Lead)</option>
                            <option value="finance">Finance (ERP sync, tier recalculation — no content edit)</option>
                            <option value="admin">Admin (Full access — config, users, taxonomy)</option>
                            <option value="viewer">Viewer (Read-only dashboard)</option>
                        </select>
                    </div>

                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={loading}
                        style={{ width: '100%' }}
                    >
                        {loading ? 'Creating Account...' : 'Create Account'}
                    </button>
                </form>

                <div style={{ marginTop: 18, textAlign: 'center', fontSize: '0.9rem', color: 'var(--text-dim)' }}>
                    Already have an account?{' '}
                    <Link to="/login" style={{ color: 'var(--accent)', textDecoration: 'none', fontWeight: 500 }}>
                        Sign In
                    </Link>
                </div>

                <div style={{ margin: '14px 0', borderTop: '1px solid var(--border)', position: 'relative' }}>
                    <span style={{
                        position: 'absolute', top: -10, left: '50%', transform: 'translateX(-50%)',
                        background: 'var(--surface)', padding: '0 10px', fontSize: '0.6rem', color: 'var(--text-dim)'
                    }}>OR</span>
                </div>

                <Link to="/login" className="btn btn-secondary" style={{ textDecoration: 'none', display: 'block', textAlign: 'center' }}>
                    🚀 Try Demo Mode
                </Link>
            </div>
        </div>
    );
};

export default Register;
