// SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 2.4; CIE_v232_Developer_Amendment_Pack_v2.docx Section 4.1
import React, { createContext, useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import Sidebar from './components/common/Sidebar';
import Header from './components/common/Header';
import Toast from './components/common/Toast';
import Login from './components/auth/Login';
import AuthGuard from './components/auth/AuthGuard';
import DefaultRedirect from './components/auth/DefaultRedirect';
import Dashboard from './pages/Dashboard';
import WriterEdit from './pages/WriterEdit';
import WriterQueue from './pages/WriterQueue';
import Help from './pages/Help';
import Maturity from './pages/Maturity';
import AiAudit from './pages/AiAudit';
import Clusters from './pages/ClustersPage';
import Channels from './pages/Channels';
import Config from './pages/Config';
import TierMgmt from './pages/TierMgmt';
import AuditTrail from './pages/AuditTrail';
import BulkOps from './pages/BulkOps';
import StaffKpis from './pages/StaffKpis';
import SemrushImport from './pages/SemrushImport';

export const AppContext = createContext(null);

const getStoredUser = () => {
  try {
    const u = sessionStorage.getItem('cie_user');
    return u ? JSON.parse(u) : null;
  } catch {
    return null;
  }
};

const getStoredToken = () => sessionStorage.getItem('cie_token') || null;

const AppProvider = ({ children }) => {
  const [user, setUser] = useState(getStoredUser);
  const [token, setToken] = useState(getStoredToken);
  const [notifications, setNotifications] = useState([]);

  const isAuthenticated = !!token;

  const login = (nextUser, nextToken) => {
    sessionStorage.setItem('cie_token', nextToken);
    sessionStorage.setItem('cie_user', JSON.stringify(nextUser));
    setUser(nextUser);
    setToken(nextToken);
  };

  const logout = () => {
    sessionStorage.removeItem('cie_token');
    sessionStorage.removeItem('cie_user');
    setUser(null);
    setToken(null);
  };

  const addNotification = (notification) => {
    const id = Date.now();
    setNotifications((prev) => [...prev, { ...notification, id }]);
    setTimeout(() => {
      setNotifications((prev) => prev.filter((n) => n.id !== id));
    }, 4000);
  };

  const value = {
    user,
    token,
    isAuthenticated,
    login,
    logout,
    notifications,
    addNotification,
  };

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
};

// SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 4; Step 7 — Header shared by writer and reviewer layouts (both)
const AppLayout = ({ children }) => (
  <div className="app-layout app-layout-with-header">
    <Header />
    <div className="app-body">
      <Sidebar />
      <div className="main-content">
        {children}
      </div>
    </div>
    <Toast />
  </div>
);

const App = () => {
  return (
    <BrowserRouter>
      <AppProvider>
        <Routes>
          <Route path="/login" element={<Login />} />

          <Route path="/writer/queue" element={<AuthGuard><AppLayout><WriterQueue /></AppLayout></AuthGuard>} />
          <Route path="/writer/edit/:skuId" element={<AuthGuard><AppLayout><WriterEdit /></AppLayout></AuthGuard>} />

          <Route path="/review/dashboard" element={<AuthGuard><AppLayout><Dashboard /></AppLayout></AuthGuard>} />
          <Route path="/review/maturity" element={<AuthGuard><AppLayout><Maturity /></AppLayout></AuthGuard>} />
          <Route path="/review/ai-audit" element={<AuthGuard><AppLayout><AiAudit /></AppLayout></AuthGuard>} />
          <Route path="/review/channels" element={<AuthGuard><AppLayout><Channels /></AppLayout></AuthGuard>} />
          <Route path="/review/kpis" element={<AuthGuard><AppLayout><StaffKpis /></AppLayout></AuthGuard>} />

          <Route path="/help" element={<AuthGuard><AppLayout><Help /></AppLayout></AuthGuard>} />

          <Route path="/admin/clusters" element={<AuthGuard><AppLayout><Clusters /></AppLayout></AuthGuard>} />
          <Route path="/admin/config" element={<AuthGuard><AppLayout><Config /></AppLayout></AuthGuard>} />
          <Route path="/admin/tiers" element={<AuthGuard><AppLayout><TierMgmt /></AppLayout></AuthGuard>} />
          <Route path="/admin/audit-trail" element={<AuthGuard><AppLayout><AuditTrail /></AppLayout></AuthGuard>} />
          <Route path="/admin/bulk-ops" element={<AuthGuard><AppLayout><BulkOps /></AppLayout></AuthGuard>} />
          <Route path="/admin/semrush-import" element={<AuthGuard><AppLayout><SemrushImport /></AppLayout></AuthGuard>} />
        </Routes>
      </AppProvider>
    </BrowserRouter>
  );
};

export default App;
