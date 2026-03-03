import React, { useState, useEffect, useContext } from 'react';
import { businessRulesApi } from '../services/api';
import { AppContext } from '../App';
import { canModifyConfig } from '../lib/rbac';

const BusinessRules = () => {
    const { user, addNotification } = useContext(AppContext);
    const [rules, setRules] = useState([]);
    const [audit, setAudit] = useState([]);
    const [loading, setLoading] = useState(true);
    const [auditLoading, setAuditLoading] = useState(false);
    const [activeTab, setActiveTab] = useState('rules');
    const [editingKey, setEditingKey] = useState(null);
    const [editValue, setEditValue] = useState('');
    const [savingKey, setSavingKey] = useState(null);
    const [pendingKeys, setPendingKeys] = useState(new Set());

    const canEdit = canModifyConfig(user);

    const fetchRules = async () => {
        try {
            const res = await businessRulesApi.list();
            const data = res.data?.data ?? res.data ?? [];
            setRules(Array.isArray(data) ? data : []);
        } catch (err) {
            addNotification({ type: 'error', message: 'Failed to load business rules' });
            setRules([]);
        } finally {
            setLoading(false);
        }
    };

    const fetchAudit = async () => {
        setAuditLoading(true);
        try {
            const res = await businessRulesApi.getAudit();
            const data = res.data?.data ?? res.data ?? [];
            setAudit(Array.isArray(data) ? data : []);
        } catch (err) {
            addNotification({ type: 'error', message: 'Failed to load audit log' });
            setAudit([]);
        } finally {
            setAuditLoading(false);
        }
    };

    useEffect(() => {
        fetchRules();
    }, []);

    useEffect(() => {
        if (activeTab === 'audit') fetchAudit();
    }, [activeTab]);

    const startEdit = (row) => {
        setEditingKey(row.rule_key);
        setEditValue(row.rule_value ?? '');
    };

    const cancelEdit = () => {
        setEditingKey(null);
        setEditValue('');
    };

    const handleSave = async () => {
        if (!editingKey || !canEdit) return;
        setSavingKey(editingKey);
        try {
            const res = await businessRulesApi.update(editingKey, editValue);
            const data = res.data?.data ?? res.data;
            const status = res.status;
            if (status === 202 || data?.pending_second_approval) {
                setPendingKeys((prev) => new Set(prev).add(editingKey));
            } else {
                setPendingKeys((prev) => {
                    const next = new Set(prev);
                    next.delete(editingKey);
                    return next;
                });
            }
            setRules((prev) =>
                prev.map((r) =>
                    r.rule_key === editingKey ? { ...r, rule_value: editValue, last_changed_at: new Date().toISOString() } : r
                )
            );
            setEditingKey(null);
            setEditValue('');
            addNotification({ type: 'success', message: status === 202 ? 'Change pending second approval' : 'Rule updated' });
        } catch (err) {
            addNotification({ type: 'error', message: err.response?.data?.message || 'Failed to update rule' });
        } finally {
            setSavingKey(null);
        }
    };

    if (loading) {
        return (
            <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>
                Loading business rules...
            </div>
        );
    }

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Business Rules</h1>
                <div className="page-subtitle">Admin only — edit rule values from the business_rules table</div>
            </div>

            <div className="flex gap-12 mb-16" style={{ borderBottom: '1px solid var(--border-light)' }}>
                <button
                    type="button"
                    className={activeTab === 'rules' ? 'active' : ''}
                    style={{
                        padding: '8px 12px',
                        border: 'none',
                        borderBottom: activeTab === 'rules' ? '2px solid var(--primary)' : '2px solid transparent',
                        background: 'none',
                        cursor: 'pointer',
                        fontWeight: 600,
                        color: activeTab === 'rules' ? 'var(--primary)' : 'var(--text-muted)',
                    }}
                    onClick={() => setActiveTab('rules')}
                >
                    Rules
                </button>
                <button
                    type="button"
                    style={{
                        padding: '8px 12px',
                        border: 'none',
                        borderBottom: activeTab === 'audit' ? '2px solid var(--primary)' : '2px solid transparent',
                        background: 'none',
                        cursor: 'pointer',
                        fontWeight: 600,
                        color: activeTab === 'audit' ? 'var(--primary)' : 'var(--text-muted)',
                    }}
                    onClick={() => setActiveTab('audit')}
                >
                    Audit
                </button>
            </div>

            {activeTab === 'rules' && (
                <div className="card" style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.8rem' }}>
                        <thead>
                            <tr style={{ borderBottom: '2px solid var(--border)', textAlign: 'left' }}>
                                <th style={{ padding: '10px 12px' }}>rule_key</th>
                                <th style={{ padding: '10px 12px' }}>label</th>
                                <th style={{ padding: '10px 12px' }}>rule_value</th>
                                <th style={{ padding: '10px 12px' }}>data_type</th>
                                <th style={{ padding: '10px 12px' }}>module</th>
                                <th style={{ padding: '10px 12px' }}>unit</th>
                                <th style={{ padding: '10px 12px' }}>approval_level</th>
                                <th style={{ padding: '10px 12px' }}>last_changed_at</th>
                                {canEdit && <th style={{ padding: '10px 12px' }}>Actions</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rules.map((row) => (
                                <tr key={row.rule_key} style={{ borderBottom: '1px solid var(--border-light)' }}>
                                    <td style={{ padding: '8px 12px', fontFamily: 'var(--mono)' }}>{row.rule_key}</td>
                                    <td style={{ padding: '8px 12px', color: 'var(--text-muted)' }}>{row.label ?? ''}</td>
                                    <td style={{ padding: '8px 12px' }}>
                                        {editingKey === row.rule_key ? (
                                            <input
                                                type="text"
                                                value={editValue}
                                                onChange={(e) => setEditValue(e.target.value)}
                                                style={{
                                                    width: '100%',
                                                    maxWidth: 200,
                                                    padding: '4px 8px',
                                                    border: '1px solid var(--border)',
                                                    borderRadius: 4,
                                                    fontFamily: 'var(--mono)',
                                                }}
                                                autoFocus
                                            />
                                        ) : (
                                            <span style={{ fontFamily: 'var(--mono)' }}>{row.rule_value}</span>
                                        )}
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>{row.data_type ?? ''}</td>
                                    <td style={{ padding: '8px 12px' }}>{row.module ?? ''}</td>
                                    <td style={{ padding: '8px 12px' }}>{row.unit ?? '—'}</td>
                                    <td style={{ padding: '8px 12px' }}>{row.approval_level ?? '—'}</td>
                                    <td style={{ padding: '8px 12px', color: 'var(--text-dim)' }}>
                                        {row.last_changed_at
                                            ? new Date(row.last_changed_at).toLocaleString()
                                            : '—'}
                                        {pendingKeys.has(row.rule_key) && (
                                            <span style={{ marginLeft: 8, color: 'var(--orange)', fontSize: '0.7rem' }}>
                                                Pending second approval
                                            </span>
                                        )}
                                    </td>
                                    {canEdit && (
                                        <td style={{ padding: '8px 12px' }}>
                                            {editingKey === row.rule_key ? (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="btn btn-primary"
                                                        style={{ marginRight: 8 }}
                                                        onClick={handleSave}
                                                        disabled={savingKey === row.rule_key}
                                                    >
                                                        {savingKey === row.rule_key ? 'Saving...' : 'Save'}
                                                    </button>
                                                    <button type="button" className="btn btn-secondary" onClick={cancelEdit}>
                                                        Cancel
                                                    </button>
                                                </>
                                            ) : (
                                                <button type="button" className="btn btn-secondary" onClick={() => startEdit(row)}>
                                                    Edit
                                                </button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {activeTab === 'audit' && (
                <div className="card" style={{ overflowX: 'auto' }}>
                    {auditLoading ? (
                        <div style={{ padding: 24, textAlign: 'center', color: 'var(--text-dim)' }}>Loading audit...</div>
                    ) : (
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.8rem' }}>
                            <thead>
                                <tr style={{ borderBottom: '2px solid var(--border)', textAlign: 'left' }}>
                                    <th style={{ padding: '10px 12px' }}>rule_key</th>
                                    <th style={{ padding: '10px 12px' }}>old_value</th>
                                    <th style={{ padding: '10px 12px' }}>new_value</th>
                                    <th style={{ padding: '10px 12px' }}>changed_at</th>
                                    <th style={{ padding: '10px 12px' }}>changed_by</th>
                                </tr>
                            </thead>
                            <tbody>
                                {audit.map((a) => (
                                    <tr key={a.id} style={{ borderBottom: '1px solid var(--border-light)' }}>
                                        <td style={{ padding: '8px 12px', fontFamily: 'var(--mono)' }}>{a.rule_key}</td>
                                        <td style={{ padding: '8px 12px', fontFamily: 'var(--mono)' }}>{a.old_value ?? '—'}</td>
                                        <td style={{ padding: '8px 12px', fontFamily: 'var(--mono)' }}>{a.new_value}</td>
                                        <td style={{ padding: '8px 12px', color: 'var(--text-dim)' }}>
                                            {a.changed_at ? new Date(a.changed_at).toLocaleString() : '—'}
                                        </td>
                                        <td style={{ padding: '8px 12px' }}>{a.changed_by ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    {!auditLoading && audit.length === 0 && (
                        <div style={{ padding: 24, textAlign: 'center', color: 'var(--text-dim)' }}>No audit entries yet.</div>
                    )}
                </div>
            )}
        </div>
    );
};

export default BusinessRules;
