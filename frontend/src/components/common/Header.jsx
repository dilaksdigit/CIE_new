import React, { useState } from 'react';
import useStore from '../../store';
import { useNavigate } from 'react-router-dom';

const Header = () => {
    const { user, isAuthenticated, logout } = useStore();
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
                                    <div className="user-role">{user?.role || 'No role'}</div>
                                </div>
                                <div className="dropdown-divider"></div>
                                <button className="dropdown-item logout-btn" onClick={handleLogout}>
                                    🚪 Logout
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
