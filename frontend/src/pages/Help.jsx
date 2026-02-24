import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';

const tabs = [
    { id: 'flow', path: '/help/flow', label: 'Full Flow' },
    { id: 'gates', path: '/help/gates', label: 'Gate System' },
    { id: 'roles', path: '/help/roles', label: 'Who Can Do What' },
];

const contentByTab = {
    flow: {
        title: 'How the system works',
        body: 'Writers complete SKU content in queue order. Validation runs continuously while editing. When required checks pass, publishing is direct and immediate.',
    },
    gates: {
        title: 'How validation appears',
        body: 'Writers see simple pass/fail guidance with color and plain language. Internal gate IDs and technical similarity numbers are intentionally hidden in writer views.',
    },
    roles: {
        title: 'Who can access what',
        body: 'Writer accounts access writer and help routes. Reviewer accounts access review and help routes. Admin has full access across the app for governance and support.',
    },
};

const tabFromPath = (pathname) => {
    if (pathname.startsWith('/help/gates')) return 'gates';
    if (pathname.startsWith('/help/roles')) return 'roles';
    return 'flow';
};

const Help = () => {
    const location = useLocation();
    const activeTab = tabFromPath(location.pathname);
    const content = contentByTab[activeTab];

    return (
        <div>
            <h1 className="page-title">Help</h1>
            <div className="page-subtitle">How the system works</div>

            <div className="tab-bar" style={{ marginTop: 14 }}>
                {tabs.map((tab) => (
                    <NavLink
                        key={tab.id}
                        to={tab.path}
                        className={`tab-btn ${tab.id === activeTab ? 'active' : ''}`}
                        style={{ textDecoration: 'none' }}
                    >
                        {tab.label}
                    </NavLink>
                ))}
            </div>

            <div className="card">
                <div style={{ fontSize: '0.86rem', fontWeight: 700, color: 'var(--text)', marginBottom: 8 }}>
                    {content.title}
                </div>
                <p style={{ color: 'var(--text)', fontSize: '0.82rem', lineHeight: 1.5 }}>
                    {content.body}
                </p>
            </div>
        </div>
    );
};

export default Help;
