import React, { useState, useEffect, useContext } from 'react';
import { useNavigate } from 'react-router-dom';
import THEME from '../theme';
import { bulkOpsApi, skuApi, clusterApi, faqApi } from '../services/api';
import { AppContext } from '../App';

const BULK_OPS = THEME.bulkOps || { gridMinPx: 280, gapPx: 12, iconRem: 1.2, titleRem: 0.8, descRem: 0.65, badgeRem: 0.58, badgePadding: '2px 6px', badgeRadius: 3 };

const STATUS_OPTIONS = [
    { value: 'DRAFT', label: 'Draft' },
    { value: 'PENDING', label: 'Pending' },
    { value: 'VALID', label: 'Valid' },
    { value: 'INVALID', label: 'Invalid' },
    { value: 'DEGRADED', label: 'Degraded' },
];

const BulkOps = () => {
    const { addNotification } = useContext(AppContext);
    const navigate = useNavigate();
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [activeOpId, setActiveOpId] = useState(null);
    const [tierRequests, setTierRequests] = useState([]);
    const [skus, setSkus] = useState([]);
    const [clusters, setClusters] = useState([]);
    const [templates, setTemplates] = useState([]);
    const [selectedSkuIds, setSelectedSkuIds] = useState([]);
    const [selectedClusterId, setSelectedClusterId] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('DRAFT');
    const [selectedTemplateId, setSelectedTemplateId] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        const fetchSummary = async () => {
            try {
                const res = await bulkOpsApi.getSummary();
                const data = res.data?.data ?? res.data;
                setSummary(data);
                setError(null);
            } catch (err) {
                console.error('BulkOps summary:', err);
                setError(err.response?.data?.message || 'Failed to load bulk ops summary');
                setSummary(null);
            } finally {
                setLoading(false);
            }
        };
        fetchSummary();
    }, []);

    const loadTierRequests = async () => {
        try {
            const res = await bulkOpsApi.listTierChangeRequests();
            const data = res.data?.data ?? res.data;
            setTierRequests(data.requests || []);
        } catch (e) {
            addNotification({ type: 'error', message: 'Failed to load tier change requests' });
            setTierRequests([]);
        }
    };

    const loadSkusAndClusters = async () => {
        try {
            const [skuRes, clusterRes] = await Promise.all([skuApi.list({ per_page: 500 }), clusterApi.list()]);
            const skuData = skuRes.data?.data ?? skuRes.data;
            const list = Array.isArray(skuData) ? skuData : skuData?.skus ?? skuData?.data ?? [];
            setSkus(Array.isArray(list) ? list : []);
            const clusterData = clusterRes.data?.data ?? clusterRes.data;
            setClusters(Array.isArray(clusterData) ? clusterData : clusterData?.clusters ?? []);
        } catch (e) {
            addNotification({ type: 'error', message: 'Failed to load SKUs or clusters' });
            setSkus([]);
            setClusters([]);
        }
    };

    const loadTemplates = async () => {
        try {
            const res = await faqApi.getTemplates();
            const data = res.data?.data ?? res.data;
            setTemplates(Array.isArray(data) ? data : data?.templates ?? []);
        } catch (e) {
            addNotification({ type: 'error', message: 'Failed to load FAQ templates' });
            setTemplates([]);
        }
    };

    const handleOpenModal = (op) => {
        setActiveOpId(op.id);
        setSelectedSkuIds([]);
        setSelectedClusterId('');
        setSelectedStatus('DRAFT');
        setSelectedTemplateId('');
        if (op.id === 'tier_reassignment') loadTierRequests();
        if (op.id === 'cluster_assignment' || op.id === 'status_change') loadSkusAndClusters();
        if (op.id === 'faq_template') {
            loadTemplates();
            loadSkusAndClusters();
        }
        if (op.id === 'csv_export') {
            bulkOpsApi.exportCsv()
                .then((res) => {
                    const blob = new Blob([res.data], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `sku-export-${new Date().toISOString().slice(0, 10)}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                    addNotification({ type: 'success', message: 'CSV export downloaded' });
                })
                .catch(() => addNotification({ type: 'error', message: 'CSV export failed' }));
            return;
        }
        if (op.id === 'csv_import') {
            setActiveOpId('csv_import');
        }
    };

    const handleCloseModal = () => {
        setActiveOpId(null);
        if (summary) {
            bulkOpsApi.getSummary().then((res) => {
                const data = res.data?.data ?? res.data;
                setSummary(data);
            }).catch(() => {});
        }
    };

    const toggleSku = (id) => {
        setSelectedSkuIds((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
    };

    const handleClusterSubmit = async () => {
        if (!selectedClusterId || selectedSkuIds.length === 0) {
            addNotification({ type: 'error', message: 'Select at least one SKU and a cluster' });
            return;
        }
        setSubmitting(true);
        try {
            await bulkOpsApi.clusterAssignment({ sku_ids: selectedSkuIds, cluster_id: selectedClusterId });
            addNotification({ type: 'success', message: `Updated ${selectedSkuIds.length} SKU(s) cluster` });
            handleCloseModal();
        } catch (e) {
            addNotification({ type: 'error', message: e.response?.data?.message || 'Cluster assignment failed' });
        } finally {
            setSubmitting(false);
        }
    };

    const handleStatusSubmit = async () => {
        if (selectedSkuIds.length === 0) {
            addNotification({ type: 'error', message: 'Select at least one SKU' });
            return;
        }
        setSubmitting(true);
        try {
            await bulkOpsApi.statusChange({ sku_ids: selectedSkuIds, validation_status: selectedStatus });
            addNotification({ type: 'success', message: `Updated ${selectedSkuIds.length} SKU(s) status` });
            handleCloseModal();
        } catch (e) {
            addNotification({ type: 'error', message: e.response?.data?.message || 'Status change failed' });
        } finally {
            setSubmitting(false);
        }
    };

    const handleFaqSubmit = async () => {
        if (!selectedTemplateId || selectedSkuIds.length === 0) {
            addNotification({ type: 'error', message: 'Select a template and at least one SKU' });
            return;
        }
        setSubmitting(true);
        try {
            await bulkOpsApi.faqApply({ template_id: selectedTemplateId, sku_ids: selectedSkuIds });
            addNotification({ type: 'success', message: `Applied template to ${selectedSkuIds.length} SKU(s)` });
            handleCloseModal();
        } catch (e) {
            addNotification({ type: 'error', message: e.response?.data?.message || 'FAQ apply failed' });
        } finally {
            setSubmitting(false);
        }
    };

    if (loading) {
        return (
            <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-muted)' }}>
                Loading bulk operations…
            </div>
        );
    }
    if (error) {
        return (
            <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)' }}>
                {error}
            </div>
        );
    }

    const operations = summary?.operations ?? [];
    const maxSkus = summary?.max_skus_per_operation ?? 500;

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Bulk Operations</h1>
                <div className="page-subtitle">
                    Admin only — mass updates with preview and confirmation. Max {maxSkus} SKUs per operation.
                </div>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: `repeat(auto-fill, minmax(${BULK_OPS.gridMinPx}px, 1fr))`,
                    gap: BULK_OPS.gapPx,
                }}
            >
                {operations.map((op) => (
                    <div
                        key={op.id}
                        role="button"
                        tabIndex={0}
                        className="card"
                        style={{ cursor: 'pointer', display: 'flex', flexDirection: 'column' }}
                        onClick={() => handleOpenModal(op)}
                        onKeyDown={(e) => e.key === 'Enter' && handleOpenModal(op)}
                    >
                        <div style={{ fontSize: `${BULK_OPS.iconRem}rem`, marginBottom: 8 }}>{op.icon}</div>
                        <div style={{ fontSize: `${BULK_OPS.titleRem}rem`, fontWeight: 700, color: 'var(--text)', marginBottom: 4 }}>
                            {op.label}
                        </div>
                        <div style={{ fontSize: `${BULK_OPS.descRem}rem`, color: 'var(--text-muted)', marginBottom: 12, flex: 1 }}>
                            {op.description}
                        </div>
                        {op.count != null && op.count > 0 && (
                            <span
                                style={{
                                    display: 'inline-block',
                                    alignSelf: 'flex-start',
                                    fontSize: `${BULK_OPS.badgeRem}rem`,
                                    padding: BULK_OPS.badgePadding,
                                    background: 'var(--orange-bg)',
                                    color: 'var(--orange)',
                                    borderRadius: BULK_OPS.badgeRadius,
                                    border: `1px solid ${THEME.amberBorder}`,
                                    fontWeight: 600,
                                }}
                            >
                                {op.count} pending
                            </span>
                        )}
                    </div>
                ))}
            </div>

            {activeOpId === 'tier_reassignment' && (
                <div className="modal-overlay" onClick={handleCloseModal} role="dialog" aria-modal="true">
                    <div className="card" style={{ maxWidth: 560, maxHeight: '80vh', overflow: 'auto' }} onClick={(e) => e.stopPropagation()}>
                        <h3 style={{ marginBottom: 12 }}>Pending tier change requests</h3>
                        {tierRequests.length === 0 ? (
                            <p style={{ color: 'var(--text-muted)' }}>No pending requests.</p>
                        ) : (
                            <ul style={{ listStyle: 'none', padding: 0 }}>
                                {tierRequests.map((r) => (
                                    <li key={r.id} style={{ padding: '6px 0', borderBottom: '1px solid var(--border)' }}>
                                        {r.sku_code} → {r.requested_tier} ({r.status})
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p style={{ marginTop: 12, fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                            Approve individual requests from Tier Management or the SKU edit flow.
                        </p>
                        <button type="button" className="btn btn-secondary" onClick={handleCloseModal} style={{ marginTop: 12 }}>
                            Close
                        </button>
                    </div>
                </div>
            )}

            {activeOpId === 'cluster_assignment' && (
                <div className="modal-overlay" onClick={handleCloseModal} role="dialog" aria-modal="true">
                    <div className="card" style={{ maxWidth: 560, maxHeight: '80vh', overflow: 'auto' }} onClick={(e) => e.stopPropagation()}>
                        <h3 style={{ marginBottom: 12 }}>Bulk cluster assignment</h3>
                        <label style={{ display: 'block', marginBottom: 8 }}>Cluster</label>
                        <select
                            value={selectedClusterId}
                            onChange={(e) => setSelectedClusterId(e.target.value)}
                            style={{ width: '100%', marginBottom: 16, padding: 8 }}
                        >
                            <option value="">Select cluster</option>
                            {clusters.map((c) => (
                                <option key={c.id} value={c.id}>{c.name || c.id}</option>
                            ))}
                        </select>
                        <label style={{ display: 'block', marginBottom: 8 }}>SKUs (max {maxSkus})</label>
                        <div style={{ maxHeight: 200, overflow: 'auto', border: '1px solid var(--border)', padding: 8, borderRadius: 4 }}>
                            {(skus.slice(0, maxSkus)).map((s) => (
                                <label key={s.id} style={{ display: 'block', marginBottom: 4 }}>
                                    <input
                                        type="checkbox"
                                        checked={selectedSkuIds.includes(s.id)}
                                        onChange={() => toggleSku(s.id)}
                                    />
                                    {' '}{s.sku_code || s.id}
                                </label>
                            ))}
                        </div>
                        <div style={{ marginTop: 12, display: 'flex', gap: 8 }}>
                            <button type="button" className="btn btn-primary" onClick={handleClusterSubmit} disabled={submitting}>
                                {submitting ? 'Applying…' : 'Apply'}
                            </button>
                            <button type="button" className="btn btn-secondary" onClick={handleCloseModal}>Cancel</button>
                        </div>
                    </div>
                </div>
            )}

            {activeOpId === 'status_change' && (
                <div className="modal-overlay" onClick={handleCloseModal} role="dialog" aria-modal="true">
                    <div className="card" style={{ maxWidth: 560, maxHeight: '80vh', overflow: 'auto' }} onClick={(e) => e.stopPropagation()}>
                        <h3 style={{ marginBottom: 12 }}>Bulk status change</h3>
                        <label style={{ display: 'block', marginBottom: 8 }}>New status</label>
                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            style={{ width: '100%', marginBottom: 16, padding: 8 }}
                        >
                            {STATUS_OPTIONS.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                        <label style={{ display: 'block', marginBottom: 8 }}>SKUs (max {maxSkus})</label>
                        <div style={{ maxHeight: 200, overflow: 'auto', border: '1px solid var(--border)', padding: 8, borderRadius: 4 }}>
                            {(skus.slice(0, maxSkus)).map((s) => (
                                <label key={s.id} style={{ display: 'block', marginBottom: 4 }}>
                                    <input
                                        type="checkbox"
                                        checked={selectedSkuIds.includes(s.id)}
                                        onChange={() => toggleSku(s.id)}
                                    />
                                    {' '}{s.sku_code || s.id}
                                </label>
                            ))}
                        </div>
                        <div style={{ marginTop: 12, display: 'flex', gap: 8 }}>
                            <button type="button" className="btn btn-primary" onClick={handleStatusSubmit} disabled={submitting}>
                                {submitting ? 'Applying…' : 'Apply'}
                            </button>
                            <button type="button" className="btn btn-secondary" onClick={handleCloseModal}>Cancel</button>
                        </div>
                    </div>
                </div>
            )}

            {activeOpId === 'faq_template' && (
                <div className="modal-overlay" onClick={handleCloseModal} role="dialog" aria-modal="true">
                    <div className="card" style={{ maxWidth: 560, maxHeight: '80vh', overflow: 'auto' }} onClick={(e) => e.stopPropagation()}>
                        <h3 style={{ marginBottom: 12 }}>Apply FAQ template</h3>
                        <label style={{ display: 'block', marginBottom: 8 }}>Template</label>
                        <select
                            value={selectedTemplateId}
                            onChange={(e) => setSelectedTemplateId(e.target.value)}
                            style={{ width: '100%', marginBottom: 16, padding: 8 }}
                        >
                            <option value="">Select template</option>
                            {templates.map((t) => (
                                <option key={t.id} value={t.id}>{t.question || t.product_class || t.id}</option>
                            ))}
                        </select>
                        <label style={{ display: 'block', marginBottom: 8 }}>SKUs (max {maxSkus})</label>
                        <div style={{ maxHeight: 200, overflow: 'auto', border: '1px solid var(--border)', padding: 8, borderRadius: 4 }}>
                            {(skus.slice(0, maxSkus)).map((s) => (
                                <label key={s.id} style={{ display: 'block', marginBottom: 4 }}>
                                    <input
                                        type="checkbox"
                                        checked={selectedSkuIds.includes(s.id)}
                                        onChange={() => toggleSku(s.id)}
                                    />
                                    {' '}{s.sku_code || s.id}
                                </label>
                            ))}
                        </div>
                        <div style={{ marginTop: 12, display: 'flex', gap: 8 }}>
                            <button type="button" className="btn btn-primary" onClick={handleFaqSubmit} disabled={submitting}>
                                {submitting ? 'Applying…' : 'Apply'}
                            </button>
                            <button type="button" className="btn btn-secondary" onClick={handleCloseModal}>Cancel</button>
                        </div>
                    </div>
                </div>
            )}

            {activeOpId === 'csv_import' && (
                <div className="modal-overlay" onClick={handleCloseModal} role="dialog" aria-modal="true">
                    <div className="card" style={{ maxWidth: 480 }} onClick={(e) => e.stopPropagation()}>
                        <h3 style={{ marginBottom: 12 }}>CSV import</h3>
                        <p style={{ color: 'var(--text-muted)', marginBottom: 12 }}>
                            Use Semrush Import for keyword data. For custom SKU data import, contact your admin or use the API.
                        </p>
                        <button type="button" className="btn btn-primary" onClick={() => { handleCloseModal(); navigate('/admin/semrush-import'); }}>
                            Open Semrush Import
                        </button>
                        <button type="button" className="btn btn-secondary" onClick={handleCloseModal} style={{ marginLeft: 8 }}>Close</button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default BulkOps;
