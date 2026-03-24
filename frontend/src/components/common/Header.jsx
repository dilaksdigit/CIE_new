import React, { useState, useContext } from 'react';
import { AppContext } from '../../App';
import { useNavigate, Link } from 'react-router-dom';
import { roleLabel } from './UIComponents';

const Header = () => {
    const { user, isAuthenticated, logout } = useContext(AppContext);
    const navigate = useNavigate();
    const [showDropdown, setShowDropdown] = useState(false);

    const handleLogout = () => {
        logout();
        navigate('/login');
        setShowDropdown(false);
    };

    return (
        <header className="app-header">
            <div className="header-brand">
                <div className="logo">CIE</div>
                <h1>Content Intelligence Engine</h1>
                <span className="version">v2.3.2</span>
            </div>
            <div className="header-actions">
                {/* SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 4 (top nav help icon); Section 1.5 (light palette) */}
                {isAuthenticated && (
                    <Link
                        // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §5 Step 6
                        // FIX: UI-11 — help icon links to /help/flow.
                        to="/help/flow"
                        title="How the system works"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            width: 28,
                            height: 28,
                            borderRadius: '50%',
                            border: '1.5px solid #E5E5E5',
                            background: '#FFFFFF',
                            color: '#5B7A3A',
                            fontSize: '0.85rem',
                            fontWeight: 700,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >?</Link>
                )}
                <div className="header-status">
                    <span className="status-dot"></span>
                    <span>System Online</span>
                </div>
                {isAuthenticated && (
                    <div className="header-user-menu">
                        <div 
                            className="header-user" 
                            onClick={() => setShowDropdown(!showDropdown)}
                            title="User menu"
                        >
                            {user?.name?.charAt(0)?.toUpperCase() || 'U'}
                        </div>
                        {showDropdown && (
                            <div className="user-dropdown">
                                <div className="dropdown-user-info">
                                    <div className="user-name">{user?.name || 'User'}</div>
                                    <div className="user-email">{user?.email || 'No email'}</div>
                                    <div className="user-role">{roleLabel(user?.role?.name ?? user?.role) || 'No role'}</div>
                                </div>
                                <div className="dropdown-divider"></div>
                                <button className="dropdown-item logout-btn" onClick={handleLogout}>
                                    {/* SOURCE: CLAUDE.md §8
                                       FIX: UI-05 — no emojis in production UI. */}
                                    Logout
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </header>
    );
};

export default Header;
