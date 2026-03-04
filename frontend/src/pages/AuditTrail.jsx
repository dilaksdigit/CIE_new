import React, { useState, useEffect } from 'react';
import { RoleBadge } from '../components/common/UIComponents';
import { auditLogApi } from '../services/api';

const AuditTrail = () => {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [filterSku, setFilterSku] = useState('');
    const [filterUser, setFilterUser] = useState('');
    const [filterAction, setFilterAction] = useState('');

    useEffect(() => {
        let cancelled = false;
        const load = async () => {
            try {
                setLoading(true);
                setError('');
                const params = {};
                if (filterSku.trim()) params.sku = filterSku.trim();
                if (filterUser.trim()) params.user = filterUser.trim();
                if (filterAction) params.action = filterAction;
                const res = await auditLogApi.getLogs(params);
                if (!cancelled) {
                    const raw = res.data?.data ?? res.data ?? [];
                    // Handle paginated response (data.data) or plain array
                    const list = Array.isArray(raw) ? raw : (raw?.data ?? []);
                    setLogs(list);
                }
            } catch (err) {
                if (!cancelled) setError('Failed to load audit trail.');
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        load();
        return () => { cancelled = true; };
    }, [filterSku, filterUser, filterAction]);

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Audit Trail Viewer</h1>
                <div className="page-subtitle">Immutable log — REVOKE UPDATE/DELETE enforced at database level</div>
            </div>

            <div className="flex gap-8 mb-14 flex-wrap">
                <input
                    className="search-input"
                    placeholder="Filter by SKU..."
                    value={filterSku}
                    onChange={e => setFilterSku(e.target.value)}
                />
                <input
                    className="search-input"
                    placeholder="Filter by user..."
                    value={filterUser}
                    onChange={e => setFilterUser(e.target.value)}
                />
                <select className="filter-select" value={filterAction} onChange={e => setFilterAction(e.target.value)}>
                    <option value="">All Actions</option>
                    <option value="create">Create</option>
                    <option value="update">Content Edit</option>
                    <option value="publish">Publish</option>
                    <option value="tier_change">Tier Change</option>
                    <option value="permission_change">Permission Denied</option>
                    <option value="audit_run">Audit Run</option>
                    <option value="gate_pass">Gate Pass</option>
                    <option value="gate_fail">Gate Fail</option>
                </select>
            </div>

            <div className="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>SKU</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr key="loading">
                                <td colSpan="6" style={{ textAlign: 'center', padding: '24px', color: 'var(--text-dim)' }}>
                                    Loading audit trail...
                                </td>
                            </tr>
                        )}
                        {!loading && error && (
                            <tr key="error">
                                <td colSpan="6" style={{ textAlign: 'center', padding: '24px', color: 'var(--red)' }}>
                                    {error}
                                </td>
                            </tr>
                        )}
                        {!loading && !error && logs.length === 0 && (
                            <tr key="empty">
                                <td colSpan="6" style={{ textAlign: 'center', padding: '24px', color: 'var(--text-dim)' }}>
                                    No audit log entries found.
                                </td>
                            </tr>
                        )}
                        {!loading && !error && logs.map((row, i) => (
                            <tr key={row.id}>
                                <td className="mono" style={{ fontSize: '0.65rem' }}>
                                    {row.timestamp ?? row.created_at ?? '—'}
                                </td>
                                <td style={{ fontSize: '0.75rem' }}>{row.actor_id ?? row.user_id ?? '—'}</td>
                                <td><RoleBadge role={row.actor_role ?? row.role ?? ''} /></td>
                                <td>
                                    <span style={{
                                        color: row.action === 'permission_change' ? 'var(--red)' : 'var(--text)',
                                        fontSize: '0.7rem',
                                        fontWeight: row.action === 'permission_change' ? 600 : 400
                                    }}>{row.action}</span>
                                </td>
                                <td className="mono">{row.entity_id ?? '—'}</td>
                                <td style={{ fontSize: '0.7rem' }}>{row.field_name ?? row.new_value ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default AuditTrail;
