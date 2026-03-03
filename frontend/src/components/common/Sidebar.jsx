import React, { useState, useContext } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { RoleBadge } from './UIComponents';
import { AppContext } from '../../App';
import { normalizeRole } from '../../lib/rbac';

const NAV_BY_GROUP = {
    writer: [
        { to: '/writer/queue', icon: 'Q', label: 'My Queue' },
        { to: '/help/flow', icon: '?', label: 'Help' },
    ],
    reviewer: [
        { to: '/review/dashboard', icon: 'D', label: 'Portfolio' },
        { to: '/review/maturity', icon: 'M', label: 'Maturity' },
        { to: '/review/ai-audit', icon: 'A', label: 'AI Audit' },
        { to: '/review/channels', icon: 'C', label: 'Channels' },
        { to: '/review/kpis', icon: 'K', label: 'Staff KPIs' },
        { to: '/help/flow', icon: '?', label: 'Help' },
    ],
    admin: [
        { to: '/admin/clusters', icon: 'C', label: 'Clusters' },
        { to: '/admin/config', icon: 'S', label: 'Config' },
        { to: '/admin/business-rules', icon: 'R', label: 'Business Rules' },
        { to: '/admin/tiers', icon: 'T', label: 'Tiers' },
        { to: '/admin/audit-trail', icon: 'A', label: 'Audit Trail' },
        { to: '/admin/bulk-ops', icon: 'B', label: 'Bulk Ops' },
        { to: '/admin/semrush-import', icon: 'K', label: 'Semrush Import' },
        { to: '/review/dashboard', icon: 'D', label: 'Reviewer View' },
        { to: '/writer/queue', icon: 'Q', label: 'Writer View' },
        { to: '/help/flow', icon: '?', label: 'Help' },
    ],
};

const navGroupForRole = (role) => {
    if (role === 'content_editor' || role === 'product_specialist') return 'writer';
    if (role === 'content_lead' || role === 'seo_governor') return 'reviewer';
    if (role === 'admin') return 'admin';
    return 'writer';
};

const Sidebar = () => {
    const [collapsed, setCollapsed] = useState(false);
    const { user, logout } = useContext(AppContext);
    const navigate = useNavigate();
    const role = normalizeRole(user?.role);
    const navItems = NAV_BY_GROUP[navGroupForRole(role)];

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <aside className={`sidebar ${collapsed ? 'collapsed' : ''}`}>
            {/* Logo */}
            <div className="sidebar-logo" onClick={() => setCollapsed(!collapsed)}>
                <div className="sidebar-logo-icon">C</div>
                {!collapsed && (
                    <div className="sidebar-logo-text">
                        <h2>CIE</h2>
                        <span>v2.3.2</span>
                    </div>
                )}
            </div>

            {/* Nav items */}
            <nav className="sidebar-nav">
                {navItems.map((item) => (
                    <NavLink
                        key={item.to}
                        to={item.to}
                        className={({ isActive }) => `sidebar-item ${isActive ? 'active' : ''}`}
                    >
                        <span className="icon">{item.icon}</span>
                        {!collapsed && (
                            <span className="label">{item.label}</span>
                        )}
                    </NavLink>
                ))}
            </nav>

            {/* User footer */}
            <div className="sidebar-footer">
                <div className="sidebar-avatar">{user?.name?.substring(0, 2)?.toUpperCase() || 'DL'}</div>
                {!collapsed && (
                    <div className="sidebar-user-info">
                        <div style={{ fontSize: '0.7rem', color: 'var(--text)', fontWeight: 600 }}>{user?.name || 'User'}</div>
                        <RoleBadge role={user?.role || 'governor'} />
                    </div>
                )}
                <button 
                    onClick={handleLogout}
                    className="sidebar-logout-btn"
                    title="Logout"
                >
                    🚪
                </button>
            </div>
        </aside>
    );
};

export default Sidebar;
