import React, { useContext, useEffect, useState } from 'react';
import THEME from '../theme';
import { AppContext } from '../App';
import { canModifyConfig } from '../lib/rbac';
import { shopifyApi } from '../services/api';

const STATUS_COLORS = {
    active: { bg: '#dcfce7', text: '#166534', border: '#86efac' },
    draft: { bg: '#fef9c3', text: '#854d0e', border: '#fde047' },
    archived: { bg: '#fee2e2', text: '#991b1b', border: '#fca5a5' },
};

const StatusBadge = ({ status }) => {
    const s = (status || '').toLowerCase();
    const colors = STATUS_COLORS[s] || { bg: THEME.muted, text: THEME.textMid, border: THEME.border };
    return (
        <span style={{
            display: 'inline-block', padding: '2px 8px', borderRadius: 4, fontSize: 11,
            fontWeight: 600, textTransform: 'uppercase', letterSpacing: 0.5,
            background: colors.bg, color: colors.text, border: `1px solid ${colors.border}`,
        }}>
            {status || '—'}
        </span>
    );
};

const stripHtml = (html) => {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return (tmp.textContent || tmp.innerText || '').trim();
};

const ShopifyPull = () => {
    const { user, addNotification } = useContext(AppContext);
    const isAdmin = canModifyConfig(user);

    const [configured, setConfigured] = useState(null);
    const [statusLoading, setStatusLoading] = useState(true);
    const [listLimit, setListLimit] = useState('250');
    const [doSyncOnList, setDoSyncOnList] = useState(false);
    const [listLoading, setListLoading] = useState(false);
    const [syncLoading, setSyncLoading] = useState(false);
    const [lastResult, setLastResult] = useState(null);
    const [error, setError] = useState('');
    const [expandedRow, setExpandedRow] = useState(null);

    useEffect(() => {
        const loadStatus = async () => {
            setStatusLoading(true);
            setError('');
            try {
                const res = await shopifyApi.status();
                const data = res.data ?? {};
                setConfigured(!!data.configured);
            } catch (e) {
                setConfigured(false);
                setError('Failed to load Shopify status.');
            } finally {
                setStatusLoading(false);
            }
        };
        loadStatus();
    }, []);

    const handleListProducts = async () => {
        if (!isAdmin || !configured) return;
        setListLoading(true);
        setError('');
        setLastResult(null);
        setExpandedRow(null);
        try {
            const limit = listLimit.trim() === '' ? undefined : Math.max(1, parseInt(listLimit, 10) || 250);
            const params = {};
            if (limit != null) params.limit = limit;
            if (doSyncOnList) params.sync = 'true';
            const res = await shopifyApi.getProducts(params);
            const data = res.data ?? {};
            setLastResult({
                type: 'list',
                total_fetched: data.total_fetched ?? 0,
                products: data.products ?? [],
                sync: data.sync ?? null,
                status_breakdown: data.status_breakdown ?? null,
                error: data.error ?? null,
            });
            if (data.error) setError(data.error);
            else addNotification({ type: 'success', message: `Fetched ${data.total_fetched ?? 0} products.` });
        } catch (e) {
            setError(e.response?.data?.message || e.message || 'List request failed.');
        } finally {
            setListLoading(false);
        }
    };

    const handleSyncAll = async () => {
        if (!isAdmin || !configured) return;
        setSyncLoading(true);
        setError('');
        setLastResult(null);
        setExpandedRow(null);
        try {
            const res = await shopifyApi.sync();
            const data = res.data ?? {};
            setLastResult({
                type: 'sync',
                total_fetched: data.total_fetched ?? 0,
                sync: data.sync ?? null,
                status_breakdown: data.status_breakdown ?? null,
                error: data.error ?? null,
            });
            if (data.error) setError(data.error);
            else {
                const sync = data.sync ?? {};
                addNotification({
                    type: 'success',
                    message: `Sync complete: ${sync.updated ?? 0} updated, ${sync.skipped ?? 0} skipped.`,
                });
            }
        } catch (e) {
            setError(e.response?.data?.message || e.message || 'Sync request failed.');
        } finally {
            setSyncLoading(false);
        }
    };

    if (statusLoading) {
        return (
            <div className="page-container" style={{ padding: 24 }}>
                <p style={{ color: THEME.textMid }}>Loading Shopify status…</p>
            </div>
        );
    }

    if (!configured) {
        return (
            <div className="page-container" style={{ padding: 24 }}>
                <h1 style={{ color: THEME.text, marginBottom: 8 }}>Shopify product pull</h1>
                <p style={{ color: THEME.textMid }}>
                    Shopify pull is not configured. Set SHOPIFY_STORE_DOMAIN and SHOPIFY_ADMIN_ACCESS_TOKEN in the backend .env.
                    Set SHOPIFY_PULL_ENABLED=false to disable.
                </p>
            </div>
        );
    }

    const result = lastResult;
    const products = (result?.products ?? []) || [];
    const syncResult = result?.sync;
    const statusBreakdown = result?.status_breakdown;

    return (
        <div className="page-container" style={{ padding: 24, maxWidth: 1200 }}>
            <h1 style={{ color: THEME.text, marginBottom: 8 }}>Shopify product pull</h1>
            <p style={{ color: THEME.textMid, marginBottom: 24 }}>
                Pull products from Shopify and sync full catalogue data to CIE SKUs (match by variant SKU). Admin only.
            </p>

            {error && (
                <div style={{ marginBottom: 16, padding: 12, background: THEME.killBg, border: `1px solid ${THEME.killBorder}`, borderRadius: 8, color: THEME.kill }}>
                    {error}
                </div>
            )}

            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, alignItems: 'flex-end', marginBottom: 24 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <label style={{ color: THEME.text, fontSize: 14 }}>Limit</label>
                    <input
                        type="number"
                        min={1}
                        max={10000}
                        value={listLimit}
                        onChange={(e) => setListLimit(e.target.value)}
                        style={{ width: 80, padding: '6px 8px', border: `1px solid ${THEME.border}`, borderRadius: 6 }}
                    />
                </div>
                <label style={{ display: 'flex', alignItems: 'center', gap: 6, color: THEME.text, fontSize: 14 }}>
                    <input
                        type="checkbox"
                        checked={doSyncOnList}
                        onChange={(e) => setDoSyncOnList(e.target.checked)}
                    />
                    Sync to SKUs after list
                </label>
                <button
                    onClick={handleListProducts}
                    disabled={!isAdmin || listLoading}
                    style={{
                        padding: '8px 16px',
                        background: THEME.accent,
                        color: '#fff',
                        border: 'none',
                        borderRadius: 8,
                        cursor: isAdmin && !listLoading ? 'pointer' : 'not-allowed',
                    }}
                >
                    {listLoading ? 'Loading…' : 'List products'}
                </button>
                <button
                    onClick={handleSyncAll}
                    disabled={!isAdmin || syncLoading}
                    style={{
                        padding: '8px 16px',
                        background: THEME.support,
                        color: '#fff',
                        border: 'none',
                        borderRadius: 8,
                        cursor: isAdmin && !syncLoading ? 'pointer' : 'not-allowed',
                    }}
                >
                    {syncLoading ? 'Syncing…' : 'Sync all'}
                </button>
            </div>

            {result && (
                <div style={{ marginTop: 24 }}>
                    <h2 style={{ color: THEME.text, fontSize: 16, marginBottom: 12 }}>Result</h2>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, marginBottom: 16 }}>
                        <div style={{ padding: '10px 16px', background: THEME.muted, borderRadius: 8, minWidth: 100 }}>
                            <div style={{ fontSize: 11, color: THEME.textMid, textTransform: 'uppercase', letterSpacing: 0.5 }}>Fetched</div>
                            <div style={{ fontSize: 20, fontWeight: 700, color: THEME.text }}>{result.total_fetched ?? 0}</div>
                        </div>
                        {syncResult != null && (
                            <>
                                <div style={{ padding: '10px 16px', background: '#dcfce7', borderRadius: 8, minWidth: 100 }}>
                                    <div style={{ fontSize: 11, color: '#166534', textTransform: 'uppercase', letterSpacing: 0.5 }}>Updated</div>
                                    <div style={{ fontSize: 20, fontWeight: 700, color: '#166534' }}>{syncResult.updated ?? 0}</div>
                                </div>
                                <div style={{ padding: '10px 16px', background: '#fef9c3', borderRadius: 8, minWidth: 100 }}>
                                    <div style={{ fontSize: 11, color: '#854d0e', textTransform: 'uppercase', letterSpacing: 0.5 }}>Skipped</div>
                                    <div style={{ fontSize: 20, fontWeight: 700, color: '#854d0e' }}>{syncResult.skipped ?? 0}</div>
                                </div>
                            </>
                        )}
                        {statusBreakdown && (
                            <>
                                <div style={{ padding: '10px 16px', background: STATUS_COLORS.active.bg, borderRadius: 8, minWidth: 80 }}>
                                    <div style={{ fontSize: 11, color: STATUS_COLORS.active.text, textTransform: 'uppercase', letterSpacing: 0.5 }}>Active</div>
                                    <div style={{ fontSize: 20, fontWeight: 700, color: STATUS_COLORS.active.text }}>{statusBreakdown.active ?? 0}</div>
                                </div>
                                <div style={{ padding: '10px 16px', background: STATUS_COLORS.draft.bg, borderRadius: 8, minWidth: 80 }}>
                                    <div style={{ fontSize: 11, color: STATUS_COLORS.draft.text, textTransform: 'uppercase', letterSpacing: 0.5 }}>Draft</div>
                                    <div style={{ fontSize: 20, fontWeight: 700, color: STATUS_COLORS.draft.text }}>{statusBreakdown.draft ?? 0}</div>
                                </div>
                                <div style={{ padding: '10px 16px', background: STATUS_COLORS.archived.bg, borderRadius: 8, minWidth: 80 }}>
                                    <div style={{ fontSize: 11, color: STATUS_COLORS.archived.text, textTransform: 'uppercase', letterSpacing: 0.5 }}>Archived</div>
                                    <div style={{ fontSize: 20, fontWeight: 700, color: STATUS_COLORS.archived.text }}>{statusBreakdown.archived ?? 0}</div>
                                </div>
                            </>
                        )}
                    </div>

                    {Array.isArray(syncResult?.errors) && syncResult.errors.length > 0 && (
                        <ul style={{ color: THEME.kill, fontSize: 13, marginTop: 8, marginBottom: 16 }}>
                            {(syncResult.errors.slice(0, 10)).map((err, i) => (
                                <li key={i}>{err}</li>
                            ))}
                            {syncResult.errors.length > 10 && <li>… and {syncResult.errors.length - 10} more</li>}
                        </ul>
                    )}

                    {products.length > 0 && (
                        <div style={{ overflowX: 'auto', marginTop: 8, border: `1px solid ${THEME.border}`, borderRadius: 8 }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                                <thead>
                                    <tr style={{ background: THEME.muted }}>
                                        {['', 'Title', 'Handle', 'Status', 'Price', 'Type', 'SKUs', 'Content'].map((h) => (
                                            <th key={h} style={{ padding: '8px 10px', textAlign: 'left', borderBottom: `1px solid ${THEME.border}`, whiteSpace: 'nowrap', color: THEME.text }}>
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.slice(0, 50).map((p) => {
                                        const isExpanded = expandedRow === p.id;
                                        const bodyPreview = stripHtml(p.body_html);
                                        const variants = p.variants ?? [];
                                        const firstPrice = variants[0]?.price;
                                        const compareAt = variants[0]?.compare_at_price;
                                        return (
                                            <React.Fragment key={p.id}>
                                                <tr
                                                    onClick={() => setExpandedRow(isExpanded ? null : p.id)}
                                                    style={{ borderBottom: `1px solid ${THEME.border}`, cursor: 'pointer', background: isExpanded ? THEME.muted : 'transparent' }}
                                                >
                                                    <td style={{ padding: '6px 10px', width: 48 }}>
                                                        {p.image_url ? (
                                                            <img src={p.image_url} alt="" style={{ width: 36, height: 36, objectFit: 'cover', borderRadius: 4, border: `1px solid ${THEME.border}` }} />
                                                        ) : (
                                                            <div style={{ width: 36, height: 36, borderRadius: 4, background: THEME.muted, border: `1px solid ${THEME.border}` }} />
                                                        )}
                                                    </td>
                                                    <td style={{ padding: '6px 10px', color: THEME.text, fontWeight: 500 }}>{p.title ?? '—'}</td>
                                                    <td style={{ padding: '6px 10px', color: THEME.textMid, fontSize: 12 }}>{p.handle ?? '—'}</td>
                                                    <td style={{ padding: '6px 10px' }}><StatusBadge status={p.status} /></td>
                                                    <td style={{ padding: '6px 10px', color: THEME.text, whiteSpace: 'nowrap' }}>
                                                        {firstPrice != null ? `£${firstPrice}` : '—'}
                                                        {compareAt != null && <span style={{ color: THEME.textLight, textDecoration: 'line-through', marginLeft: 4, fontSize: 11 }}>£{compareAt}</span>}
                                                    </td>
                                                    <td style={{ padding: '6px 10px', color: THEME.textMid, fontSize: 12 }}>{p.product_type || '—'}</td>
                                                    <td style={{ padding: '6px 10px', color: THEME.textMid, fontSize: 12 }}>
                                                        {variants.map((v) => v.sku || v.id).filter(Boolean).join(', ') || '—'}
                                                    </td>
                                                    <td style={{ padding: '6px 10px', color: THEME.textMid, fontSize: 12, maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                                        {bodyPreview ? (bodyPreview.length > 60 ? bodyPreview.slice(0, 60) + '…' : bodyPreview) : <span style={{ color: THEME.textLight }}>No description</span>}
                                                    </td>
                                                </tr>
                                                {isExpanded && (
                                                    <tr style={{ background: THEME.muted }}>
                                                        <td colSpan={8} style={{ padding: '12px 16px' }}>
                                                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, fontSize: 13 }}>
                                                                <div>
                                                                    <div style={{ marginBottom: 8 }}>
                                                                        <strong style={{ color: THEME.text }}>Shopify ID:</strong>{' '}
                                                                        <span style={{ color: THEME.textMid }}>{p.id}</span>
                                                                    </div>
                                                                    {p.vendor && (
                                                                        <div style={{ marginBottom: 8 }}>
                                                                            <strong style={{ color: THEME.text }}>Vendor:</strong>{' '}
                                                                            <span style={{ color: THEME.textMid }}>{p.vendor}</span>
                                                                        </div>
                                                                    )}
                                                                    {p.tags && (
                                                                        <div style={{ marginBottom: 8 }}>
                                                                            <strong style={{ color: THEME.text }}>Tags:</strong>{' '}
                                                                            <span style={{ color: THEME.textMid }}>{p.tags}</span>
                                                                        </div>
                                                                    )}
                                                                    <div style={{ marginBottom: 8 }}>
                                                                        <strong style={{ color: THEME.text }}>Variants ({variants.length}):</strong>
                                                                        <div style={{ marginTop: 4, display: 'flex', flexDirection: 'column', gap: 2 }}>
                                                                            {variants.map((v, i) => (
                                                                                <span key={i} style={{ color: THEME.textMid, fontSize: 12 }}>
                                                                                    {v.sku || '(no sku)'} — {v.title || 'Default'} — £{v.price ?? '?'}
                                                                                    {v.compare_at_price != null && <span style={{ textDecoration: 'line-through', marginLeft: 4, color: THEME.textLight }}>£{v.compare_at_price}</span>}
                                                                                </span>
                                                                            ))}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <strong style={{ color: THEME.text }}>Description:</strong>
                                                                    <div style={{ marginTop: 4, color: THEME.textMid, fontSize: 12, maxHeight: 200, overflow: 'auto', padding: 8, background: '#fff', borderRadius: 4, border: `1px solid ${THEME.border}` }}>
                                                                        {bodyPreview || <em style={{ color: THEME.textLight }}>No description</em>}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )}
                                            </React.Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                            {products.length > 50 && (
                                <p style={{ padding: 8, color: THEME.textLight, fontSize: 12 }}>Showing first 50 of {products.length} products.</p>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default ShopifyPull;
