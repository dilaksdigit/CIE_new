import React, { useContext, useState, useRef } from 'react';
import THEME from '../theme';
import { AppContext } from '../App';
import { canModifyConfig } from '../lib/rbac';
import { erpSyncApi } from '../services/api';

const REQUIRED_COLUMNS = ['sku_id', 'contribution_margin_pct', 'cppc', 'velocity_90d', 'return_rate_pct'];

const ErpSync = () => {
    const { user, addNotification } = useContext(AppContext);
    const isAdmin = canModifyConfig(user);
    const fileRef = useRef(null);

    const [syncLoading, setSyncLoading] = useState(false);
    const [lastResult, setLastResult] = useState(null);
    const [error, setError] = useState('');
    const [csvData, setCsvData] = useState(null);
    const [fileName, setFileName] = useState('');

    const parseCsv = (text) => {
        const lines = text.trim().split('\n');
        if (lines.length < 2) return null;
        const headers = lines[0].split(',').map(h => h.trim());
        const missing = REQUIRED_COLUMNS.filter(c => !headers.includes(c));
        if (missing.length > 0) return { error: `Missing columns: ${missing.join(', ')}` };

        const rows = [];
        for (let i = 1; i < lines.length; i++) {
            const vals = lines[i].split(',').map(v => v.trim());
            if (vals.length < headers.length) continue;
            const row = {};
            headers.forEach((h, idx) => { row[h] = vals[idx]; });

            const skuId = (row.sku_id || '').trim();
            if (!skuId) continue;

            const margin = parseFloat(row.contribution_margin_pct);
            const cppc = parseFloat(row.cppc);
            const velocity = parseInt(row.velocity_90d, 10);
            const returnRate = parseFloat(row.return_rate_pct);

            if ([margin, cppc, velocity, returnRate].some(v => isNaN(v))) continue;

            rows.push({ sku_id: skuId, contribution_margin_pct: margin, cppc, velocity_90d: velocity, return_rate_pct: returnRate });
        }
        return { rows };
    };

    const handleFileChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setFileName(file.name);
        setError('');
        setCsvData(null);

        const reader = new FileReader();
        reader.onload = (ev) => {
            const result = parseCsv(ev.target.result);
            if (!result) { setError('CSV is empty or invalid.'); return; }
            if (result.error) { setError(result.error); return; }
            setCsvData(result.rows);
        };
        reader.readAsText(file);
    };

    const handleSync = async () => {
        if (!isAdmin || !csvData || csvData.length === 0) return;
        setSyncLoading(true);
        setError('');
        setLastResult(null);
        try {
            const payload = {
                sync_date: new Date().toISOString().split('T')[0],
                skus: csvData,
            };
            const res = await erpSyncApi.sync(payload);
            const data = res.data?.data ?? res.data ?? {};
            setLastResult(data);
            const tierChanges = data.tier_changes ?? 0;
            const processed = data.skus_processed ?? 0;
            const autoPromos = data.auto_promotions ?? 0;
            addNotification({
                type: 'success',
                message: `ERP sync complete: ${processed} processed, ${tierChanges} tier changes, ${autoPromos} auto-promotions.`,
            });
        } catch (e) {
            const msg = e.response?.data?.message || e.response?.data?.error || e.message || 'ERP sync failed.';
            setError(msg);
        } finally {
            setSyncLoading(false);
        }
    };

    return (
        <div className="page-container" style={{ padding: 24, maxWidth: 900 }}>
            <h1 style={{ color: THEME.text, marginBottom: 8 }}>ERP Sync</h1>
            <p style={{ color: THEME.textMid, marginBottom: 24 }}>
                Upload a CSV of ERP commercial data to trigger tier recalculation. Admin only.
                Automated sync runs on the 1st of every month at 02:00 UTC.
            </p>

            {!isAdmin && (
                <div style={{ padding: 12, background: THEME.killBg, border: `1px solid ${THEME.killBorder}`, borderRadius: 8, color: THEME.kill, marginBottom: 16 }}>
                    Only admin users can trigger ERP sync.
                </div>
            )}

            {error && (
                <div style={{ padding: 12, background: THEME.killBg, border: `1px solid ${THEME.killBorder}`, borderRadius: 8, color: THEME.kill, marginBottom: 16 }}>
                    {error}
                </div>
            )}

            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, alignItems: 'flex-end', marginBottom: 24 }}>
                <div>
                    <label style={{ display: 'block', color: THEME.text, fontSize: 13, marginBottom: 4 }}>
                        ERP CSV file
                    </label>
                    <input
                        ref={fileRef}
                        type="file"
                        accept=".csv"
                        onChange={handleFileChange}
                        disabled={!isAdmin}
                        style={{ fontSize: 13 }}
                    />
                    {fileName && csvData && (
                        <span style={{ marginLeft: 8, color: THEME.textMid, fontSize: 12 }}>
                            {csvData.length} rows parsed
                        </span>
                    )}
                </div>
                <button
                    onClick={handleSync}
                    disabled={!isAdmin || syncLoading || !csvData || csvData.length === 0}
                    style={{
                        padding: '8px 20px',
                        background: isAdmin && csvData?.length > 0 ? THEME.accent : THEME.border,
                        color: '#fff',
                        border: 'none',
                        borderRadius: 8,
                        cursor: isAdmin && csvData?.length > 0 && !syncLoading ? 'pointer' : 'not-allowed',
                        fontWeight: 600,
                    }}
                >
                    {syncLoading ? 'Syncing…' : 'Run ERP Sync'}
                </button>
            </div>

            <div style={{ padding: 12, background: THEME.muted, borderRadius: 8, fontSize: 12, color: THEME.textMid, marginBottom: 24 }}>
                <strong>Required CSV columns:</strong>{' '}
                {REQUIRED_COLUMNS.map((c, i) => (
                    <code key={c} style={{ background: '#fff', padding: '1px 4px', borderRadius: 3, marginRight: 4 }}>{c}</code>
                ))}
            </div>

            {lastResult && (
                <div style={{ marginTop: 8 }}>
                    <h2 style={{ color: THEME.text, fontSize: 16, marginBottom: 12 }}>Sync Result</h2>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, marginBottom: 16 }}>
                        <div style={{ padding: '10px 16px', background: THEME.muted, borderRadius: 8, minWidth: 100 }}>
                            <div style={{ fontSize: 11, color: THEME.textMid, textTransform: 'uppercase', letterSpacing: 0.5 }}>Processed</div>
                            <div style={{ fontSize: 20, fontWeight: 700, color: THEME.text }}>{lastResult.skus_processed ?? 0}</div>
                        </div>
                        <div style={{ padding: '10px 16px', background: '#dbeafe', borderRadius: 8, minWidth: 100 }}>
                            <div style={{ fontSize: 11, color: '#1e40af', textTransform: 'uppercase', letterSpacing: 0.5 }}>Tier Changes</div>
                            <div style={{ fontSize: 20, fontWeight: 700, color: '#1e40af' }}>{lastResult.tier_changes ?? 0}</div>
                        </div>
                        <div style={{ padding: '10px 16px', background: '#dcfce7', borderRadius: 8, minWidth: 100 }}>
                            <div style={{ fontSize: 11, color: '#166534', textTransform: 'uppercase', letterSpacing: 0.5 }}>Auto-Promotions</div>
                            <div style={{ fontSize: 20, fontWeight: 700, color: '#166534' }}>{lastResult.auto_promotions ?? 0}</div>
                        </div>
                        {lastResult.percentiles && (
                            <div style={{ padding: '10px 16px', background: '#fef9c3', borderRadius: 8, minWidth: 140 }}>
                                <div style={{ fontSize: 11, color: '#854d0e', textTransform: 'uppercase', letterSpacing: 0.5 }}>Percentiles</div>
                                <div style={{ fontSize: 12, color: '#854d0e', marginTop: 2 }}>
                                    p80: {lastResult.percentiles.p80?.toFixed(4)} · p30: {lastResult.percentiles.p30?.toFixed(4)} · p10: {lastResult.percentiles.p10?.toFixed(4)}
                                </div>
                            </div>
                        )}
                    </div>

                    {Array.isArray(lastResult.errors) && lastResult.errors.length > 0 && (
                        <div style={{ marginTop: 8 }}>
                            <h3 style={{ color: THEME.kill, fontSize: 14, marginBottom: 8 }}>Errors ({lastResult.errors.length})</h3>
                            <ul style={{ color: THEME.kill, fontSize: 13, maxHeight: 200, overflow: 'auto' }}>
                                {lastResult.errors.slice(0, 20).map((err, i) => (
                                    <li key={i}>{typeof err === 'string' ? err : (err.error || JSON.stringify(err))}</li>
                                ))}
                                {lastResult.errors.length > 20 && <li>… and {lastResult.errors.length - 20} more</li>}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default ErpSync;
