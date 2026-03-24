import React, { useState, useContext } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { RoleBadge } from './UIComponents';
import { AppContext } from '../../App';
import { getNavGroupForUser } from '../../lib/authRouting';

const NAV_BY_GROUP = {
    writer: [
        { to: '/writer/queue', icon: 'Q', label: 'My Queue' },
        { to: '/help', icon: '?', label: 'Help' },
    ],
    reviewer: [
        { to: '/review/dashboard', icon: 'D', label: 'Portfolio' },
        { to: '/review/maturity', icon: 'M', label: 'Maturity' },
        { to: '/review/ai-audit', icon: 'A', label: 'AI Audit' },
        { to: '/review/channels', icon: 'C', label: 'Channels' },
        { to: '/review/semrush', icon: 'R', label: 'Semrush Review' },
        { to: '/review/kpis', icon: 'K', label: 'Staff KPIs' },
        { to: '/help', icon: '?', label: 'Help' },
    ],
    admin: [
        { to: '/admin/clusters', icon: 'C', label: 'Clusters' },
        { to: '/admin/config', icon: 'S', label: 'Config' },
        { to: '/admin/tiers', icon: 'T', label: 'Tiers' },
        { to: '/admin/audit-trail', icon: 'A', label: 'Audit Trail' },
        { to: '/admin/bulk-ops', icon: 'B', label: 'Bulk Ops' },
        { to: '/admin/semrush-import', icon: 'K', label: 'Semrush Import' },
        { to: '/admin/shopify-pull', icon: 'S', label: 'Shopify Pull' },
        { to: '/admin/erp-sync', icon: 'E', label: 'ERP Sync' },
        { to: '/review/dashboard', icon: 'D', label: 'Reviewer View' },
        { to: '/writer/queue', icon: 'Q', label: 'Writer View' },
        { to: '/help', icon: '?', label: 'Help' },
    ],
    other: [
        { to: '/help', icon: '?', label: 'Help' },
    ],
};

const Sidebar = () => {
    const [collapsed, setCollapsed] = useState(false);
    const { user, logout } = useContext(AppContext);
    const navigate = useNavigate();
    const navGroup = getNavGroupForUser(user);
    const navItems = NAV_BY_GROUP[navGroup] || NAV_BY_GROUP.other;

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
                    {/* SOURCE: CLAUDE.md §8
                       FIX: UI-05 — no emojis in production UI. */}
                    L
                </button>
            </div>
        </aside>
    );
};

export default Sidebar;
