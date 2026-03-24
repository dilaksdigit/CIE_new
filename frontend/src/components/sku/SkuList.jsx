import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { skuApi, extractApiArray } from '../../services/api';

const SkuList = () => {
    const [skus, setSkus] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [search, setSearch] = useState('');
    const [filterTier, setFilterTier] = useState('ALL');
    const [filterStatus, setFilterStatus] = useState('ALL');
    const navigate = useNavigate();

    useEffect(() => {
        let cancelled = false;
        const load = async () => {
            try {
                setLoading(true);
                setError('');
                const res = await skuApi.list();
                if (!cancelled) {
                    setSkus(extractApiArray(res));
                }
            } catch (err) {
                if (!cancelled) setError('Failed to load SKUs.');
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        load();
        return () => { cancelled = true; };
    }, []);

    const filteredSkus = skus.filter(sku => {
        const matchesSearch = (sku.sku_code || '').toLowerCase().includes(search.toLowerCase()) ||
            (sku.title || '').toLowerCase().includes(search.toLowerCase()) ||
            (sku.cluster || sku.primaryCluster?.name || '').toLowerCase().includes(search.toLowerCase());
        const matchesTier = filterTier === 'ALL' || (sku.tier || '').toUpperCase() === filterTier;
        const matchesStatus = filterStatus === 'ALL' || sku.validation_status === filterStatus;
        return matchesSearch && matchesTier && matchesStatus;
    });

    return (
        <div className="dashboard">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
                <h2>SKU Management</h2>
                <button className="btn btn-primary" onClick={() => navigate('/skus/new')}>
                    + New SKU
                </button>
            </div>

            <div className="data-table-container">
                <div className="table-header" style={{ flexWrap: 'wrap', gap: '12px' }}>
                    <div className="table-search">
                        <span>Search</span>
                        <input
                            type="text"
                            placeholder="Search SKUs, titles, clusters..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                        />
                    </div>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <select
                            value={filterTier}
                            onChange={e => setFilterTier(e.target.value)}
                            style={{
                                background: 'var(--bg-input)', border: '1px solid var(--border)',
                                color: 'var(--text-primary)', borderRadius: '8px', padding: '8px 12px',
                                fontSize: '13px', outline: 'none', cursor: 'pointer'
                            }}
                        >
                            <option value="ALL">All Tiers</option>
                            <option value="HERO">Hero</option>
                            <option value="SUPPORT">Support</option>
                            <option value="HARVEST">Harvest</option>
                            <option value="KILL">Kill</option>
                        </select>
                        <select
                            value={filterStatus}
                            onChange={e => setFilterStatus(e.target.value)}
                            style={{
                                background: 'var(--bg-input)', border: '1px solid var(--border)',
                                color: 'var(--text-primary)', borderRadius: '8px', padding: '8px 12px',
                                fontSize: '13px', outline: 'none', cursor: 'pointer'
                            }}
                        >
                            <option value="ALL">All Statuses</option>
                            <option value="VALID">Valid</option>
                            <option value="DEGRADED">Degraded</option>
                            <option value="PENDING">Pending</option>
                            <option value="INVALID">Invalid</option>
                        </select>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>SKU Code</th>
                            <th>Title</th>
                            <th>Cluster</th>
                            <th>Tier</th>
                            <th>Validation</th>
                            <th>Similarity</th>
                            <th>Margin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr>
                                <td colSpan="8" style={{ textAlign: 'center', padding: '32px', color: 'var(--text-muted)' }}>
                                    Loading SKUs...
                                </td>
                            </tr>
                        )}
                        {!loading && error && (
                            <tr>
                                <td colSpan="8" style={{ textAlign: 'center', padding: '32px', color: 'var(--danger)' }}>
                                    {error}
                                </td>
                            </tr>
                        )}
                        {!loading && !error && filteredSkus.map(sku => (
                            <tr key={sku.id}>
                                <td><strong style={{ color: 'var(--primary)' }}>{sku.sku_code}</strong></td>
                                <td>{sku.title}</td>
                                <td style={{ color: 'var(--text-secondary)', fontSize: '13px' }}>
                                    {sku.cluster || sku.primaryCluster?.name || '—'}
                                </td>
                                <td><span className={`badge tier-${(sku.tier || '').toUpperCase()}`}>{(sku.tier || '').toUpperCase()}</span></td>
                                <td><span className={`status-badge ${sku.validation_status}`}>{sku.validation_status}</span></td>
                                <td>
                                    {sku.vector_gate_status === 'pass' ? (
                                        <span style={{
                                            color: '#2E7D32',
                                            fontWeight: 600, fontSize: '13px'
                                        }}>
                                            Good
                                        </span>
                                    ) : sku.vector_gate_status === 'fail' ? (
                                        <span style={{
                                            color: '#E65100',
                                            fontWeight: 600, fontSize: '13px'
                                        }}>
                                            Review
                                        </span>
                                    ) : (
                                        <span style={{ color: 'var(--text-muted)', fontSize: '13px' }}>–</span>
                                    )}
                                </td>
                                <td style={{ fontSize: '13px' }}>{sku.margin_percent != null ? `${sku.margin_percent}%` : '—'}</td>
                                <td>
                                    <button
                                        className="btn btn-secondary btn-sm"
                                        onClick={() => navigate(`/skus/${sku.id}/edit`)}
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {!loading && !error && filteredSkus.length === 0 && (
                            <tr>
                                <td colSpan="8" style={{ textAlign: 'center', padding: '32px', color: 'var(--text-muted)' }}>
                                    No SKUs found matching your filters.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {!loading && !error && (
                <div style={{ marginTop: '16px', fontSize: '13px', color: 'var(--text-muted)' }}>
                    Showing {filteredSkus.length} of {skus.length} SKUs
                </div>
            )}
        </div>
    );
};

export default SkuList;
