// SOURCE: CLAUDE.md Section 8 (no emojis in production UI); CIE_v232_Developer_Amendment_Pack Section 8 check #7
import React, { useState, useEffect, useContext } from 'react';
import {
    ReadinessBar,
    SectionTitle
} from '../components/common/UIComponents';
import { clusterApi } from '../services/api';
import { AppContext } from '../App';

const Clusters = () => {
    const [clusters, setClusters] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { addNotification } = useContext(AppContext);
    const { user } = useContext(AppContext);

    const [editingCluster, setEditingCluster] = useState(null);
    const [form, setForm] = useState({
        name: '',
        intent_statement: '',
        is_locked: false,
        requires_approval: true,
        approval_status: 'APPROVED'
    });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const fetchClusters = async () => {
            try {
                setLoading(true);
                setError(null);
                const response = await clusterApi.list();
                const clusterData = response.data.data || [];
                setClusters(clusterData);
            } catch (err) {
                console.error('Failed to fetch clusters:', err);
                setError('Failed to load clusters from database');
                addNotification({
                    type: 'error',
                    message: 'Could not load clusters'
                });
            } finally {
                setLoading(false);
            }
        };
        fetchClusters();
    }, [addNotification]);

    const roleName = (user?.role?.name || '').toUpperCase();
    const canEditIntent = roleName === 'SEO_GOVERNOR';

    const openEdit = (cluster) => {
        setEditingCluster(cluster);
        setForm({
            name: cluster.name || '',
            intent_statement: cluster.intent_statement || '',
            is_locked: !!cluster.is_locked,
            requires_approval: cluster.requires_approval ?? true,
            approval_status: cluster.approval_status || 'APPROVED',
        });
    };

    const closeEdit = () => {
        if (saving) return;
        setEditingCluster(null);
    };

    const handleSave = async () => {
        if (!editingCluster) return;
        try {
            setSaving(true);
            const payload = {
                is_locked: !!form.is_locked,
                requires_approval: !!form.requires_approval,
                approval_status: form.approval_status || 'APPROVED',
            };
            if (canEditIntent) {
                payload.name = form.name;
                payload.intent_statement = form.intent_statement;
            }
            const res = await clusterApi.update(editingCluster.id, payload);
            const updated = res.data?.data ?? res.data;
            setClusters(prev =>
                prev.map(c => (c.id === editingCluster.id ? { ...c, ...updated } : c))
            );
            addNotification({ type: 'success', message: 'Cluster updated.' });
            setEditingCluster(null);
        } catch (err) {
            console.error('Failed to update cluster', err);
            addNotification({
                type: 'error',
                message: err.response?.data?.message || 'Failed to update cluster',
            });
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>Loading clusters...</div>;
    if (error) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)' }}>{error}</div>;
    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Cluster Manager</h1>
                <div className="page-subtitle">SEO/AI Governor — semantic cluster taxonomy governance</div>
            </div>

            <div className="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Cluster ID</th>
                            <th>Name</th>
                            <th>Primary Intent</th>
                            <th>SKUs</th>
                            <th>Avg Readiness</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {clusters.map(cl => (
                            <tr key={cl.id}>
                                <td className="mono">{cl.id}</td>
                                <td>{cl.name}</td>
                                <td>
                                    <span style={{
                                        padding: "2px 8px", borderRadius: 3, fontSize: "0.65rem",
                                        background: "var(--accent-dim)", color: "var(--accent)", fontWeight: 600,
                                        border: `1px solid var(--accent)22`,
                                    }}>{cl.intent_type || cl.primary_intent || 'General'}</span>
                                </td>
                                <td className="mono">{cl.skus_count ?? cl.sku_count ?? 0}</td>
                                <td><ReadinessBar value={cl.avg_readiness || 0} /></td>
                                <td>
                                    <button
                                        className="btn btn-secondary btn-sm"
                                        type="button"
                                        onClick={() => openEdit(cl)}
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {editingCluster && (
                <div className="modal-backdrop">
                    <div className="modal">
                        <h2 className="page-title" style={{ marginBottom: 12 }}>
                            Edit Cluster {editingCluster.id}
                        </h2>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <label className="field-label">
                                Name
                                <input
                                    className="search-input"
                                    type="text"
                                    value={form.name}
                                    onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                                    disabled={!canEditIntent}
                                />
                            </label>
                            <label className="field-label">
                                Intent Statement
                                <textarea
                                    className="search-input"
                                    style={{ minHeight: 80 }}
                                    value={form.intent_statement}
                                    onChange={e => setForm(f => ({ ...f, intent_statement: e.target.value }))}
                                    disabled={!canEditIntent}
                                />
                                {!canEditIntent && (
                                    <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: 4 }}>
                                        Only SEO Governor can modify cluster intent name/statement.
                                    </div>
                                )}
                            </label>
                            <label className="field-label">
                                <input
                                    type="checkbox"
                                    checked={!!form.is_locked}
                                    onChange={e => setForm(f => ({ ...f, is_locked: e.target.checked }))}
                                />{' '}
                                Locked (prevent edits to this cluster)
                            </label>
                            <label className="field-label">
                                <input
                                    type="checkbox"
                                    checked={!!form.requires_approval}
                                    onChange={e => setForm(f => ({ ...f, requires_approval: e.target.checked }))}
                                />{' '}
                                Requires approval for changes
                            </label>
                            <label className="field-label">
                                Approval Status
                                <select
                                    className="filter-select"
                                    value={form.approval_status}
                                    onChange={e => setForm(f => ({ ...f, approval_status: e.target.value }))}
                                >
                                    <option value="DRAFT">Draft</option>
                                    <option value="PENDING">Pending</option>
                                    <option value="APPROVED">Approved</option>
                                    <option value="REJECTED">Rejected</option>
                                </select>
                            </label>
                        </div>
                        <div style={{ marginTop: 20, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                            <button
                                type="button"
                                className="btn btn-secondary"
                                onClick={closeEdit}
                                disabled={saving}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={handleSave}
                                disabled={saving}
                            >
                                {saving ? 'Saving…' : 'Save'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
            <div className="alert-banner warning">
                Warning: Cluster changes require quarterly review. Changes affect all SKUs in the cluster. Governor-only permission.
            </div>
        </div>
    );
};

export default Clusters;
