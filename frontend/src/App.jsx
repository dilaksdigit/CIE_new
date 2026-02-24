import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import Sidebar from './components/common/Sidebar';
import Toast from './components/common/Toast';
import Login from './components/auth/Login';
import Register from './components/auth/Register';
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

const AppLayout = ({ children }) => (
  <div className="app-layout">
    <Sidebar />
    <div className="main-content">
      {children}
    </div>
    <Toast />
  </div>
);

const App = () => {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />

        <Route path="/writer/queue" element={<AuthGuard><AppLayout><WriterQueue /></AppLayout></AuthGuard>} />
        <Route path="/writer/edit/:skuId" element={<AuthGuard><AppLayout><WriterEdit /></AppLayout></AuthGuard>} />
        <Route path="/writer/*" element={<AuthGuard><Navigate to="/writer/queue" replace /></AuthGuard>} />

        <Route path="/review/dashboard" element={<AuthGuard><AppLayout><Dashboard /></AppLayout></AuthGuard>} />
        <Route path="/review/maturity" element={<AuthGuard><AppLayout><Maturity /></AppLayout></AuthGuard>} />
        <Route path="/review/ai-audit" element={<AuthGuard><AppLayout><AiAudit /></AppLayout></AuthGuard>} />
        <Route path="/review/channels" element={<AuthGuard><AppLayout><Channels /></AppLayout></AuthGuard>} />
        <Route path="/review/kpis" element={<AuthGuard><AppLayout><StaffKpis /></AppLayout></AuthGuard>} />
        <Route path="/review" element={<AuthGuard><Navigate to="/review/dashboard" replace /></AuthGuard>} />
        <Route path="/review/*" element={<AuthGuard><Navigate to="/review/dashboard" replace /></AuthGuard>} />

        <Route path="/admin/clusters" element={<AuthGuard><AppLayout><Clusters /></AppLayout></AuthGuard>} />
        <Route path="/admin/config" element={<AuthGuard><AppLayout><Config /></AppLayout></AuthGuard>} />
        <Route path="/admin/tiers" element={<AuthGuard><AppLayout><TierMgmt /></AppLayout></AuthGuard>} />
        <Route path="/admin/audit-trail" element={<AuthGuard><AppLayout><AuditTrail /></AppLayout></AuthGuard>} />
        <Route path="/admin/bulk-ops" element={<AuthGuard><AppLayout><BulkOps /></AppLayout></AuthGuard>} />
        <Route path="/admin/*" element={<AuthGuard><Navigate to="/admin/clusters" replace /></AuthGuard>} />

        <Route path="/help" element={<AuthGuard><AppLayout><Help /></AppLayout></AuthGuard>} />
        <Route path="/help/flow" element={<AuthGuard><AppLayout><Help /></AppLayout></AuthGuard>} />
        <Route path="/help/gates" element={<AuthGuard><AppLayout><Help /></AppLayout></AuthGuard>} />
        <Route path="/help/roles" element={<AuthGuard><AppLayout><Help /></AppLayout></AuthGuard>} />

        <Route path="/" element={<DefaultRedirect />} />
        <Route path="/dashboard" element={<AuthGuard><Navigate to="/review/dashboard" replace /></AuthGuard>} />
        <Route path="/maturity" element={<AuthGuard><Navigate to="/review/maturity" replace /></AuthGuard>} />
        <Route path="/audit" element={<AuthGuard><Navigate to="/review/ai-audit" replace /></AuthGuard>} />
        <Route path="/channels" element={<AuthGuard><Navigate to="/review/channels" replace /></AuthGuard>} />
        <Route path="/staff" element={<AuthGuard><Navigate to="/review/kpis" replace /></AuthGuard>} />
        <Route path="/clusters" element={<AuthGuard><Navigate to="/admin/clusters" replace /></AuthGuard>} />
        <Route path="/config" element={<AuthGuard><Navigate to="/admin/config" replace /></AuthGuard>} />
        <Route path="/tiers" element={<AuthGuard><Navigate to="/admin/tiers" replace /></AuthGuard>} />
        <Route path="/audit-trail" element={<AuthGuard><Navigate to="/admin/audit-trail" replace /></AuthGuard>} />
        <Route path="/bulk" element={<AuthGuard><Navigate to="/admin/bulk-ops" replace /></AuthGuard>} />

        <Route path="*" element={<DefaultRedirect />} />
      </Routes>
    </BrowserRouter>
  );
};

export default App;
