import React, { useState, useEffect, useCallback } from 'react';
import {
    StatCard,
    TrendLine,
    ReadinessBar,
    SectionTitle
} from '../components/common/UIComponents';
import { auditApi, auditResultApi } from '../services/api';
import useStore from '../store';
import { canRunAIAudit } from '../lib/rbac';

const COLORS = {
  bg: "#FAFAF8",
  surface: "#FFFFFF",
  muted: "#F5F4F1",
  border: "#E5E3DE",
  text: "#2D2B28",
  textMid: "#6B6860",
  textLight: "#9B978F",
  accent: "#5B7A3A",
  accentLight: "#EEF2E8",
  accentBorder: "#C5D4B0",
  hero: "#8B6914",
  heroBg: "#FDF6E3",
  heroBorder: "#E8D5A0",
  support: "#3D6B8E",
  supportBg: "#EBF3F9",
  supportBorder: "#B5D0E3",
  harvest: "#9E7C1A",
  harvestBg: "#FFF8E7",
  harvestBorder: "#E8D49A",
  kill: "#A63D2F",
  killBg: "#FDEEEB",
  killBorder: "#E5B5AD",
  green: "#2E7D32",
  greenBg: "#E8F5E9",
  greenBorder: "#A5D6A7",
  red: "#C62828",
  redBg: "#FFEBEE",
  redBorder: "#EF9A9A",
  amber: "#E65100",
  amberBg: "#FFFDE7",
  amberBorder: "#FFCC80",
  blue: "#1565C0",
  blueBg: "#E3F2FD",
  blueBorder: "#90CAF9",
};

const AiAudit = () => {
    const [auditScores, setAuditScores] = useState([]);
    const [decayAlerts, setDecayAlerts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { user, addNotification } = useStore();
    const canRunAudit = canRunAIAudit(user);

    const fetchDecayAlerts = useCallback(async () => {
        try {
            const res = await auditResultApi.getDecayAlerts();
            const list = res.data?.data ?? [];
            setDecayAlerts(Array.isArray(list) ? list : []);
        } catch (e) {
            console.error('Decay alerts failed:', e);
            setDecayAlerts([]);
        }
    }, []);

    useEffect(() => {
        let cancelled = false;
        const fetchAuditData = async () => {
            try {
                setLoading(true);
                setError(null);
                await fetchDecayAlerts();
                const weeklyRes = await auditResultApi.getWeeklyScores().catch(() => ({ data: {} }));
                const weekly = weeklyRes.data?.data;
                if (!cancelled && weekly && Array.isArray(weekly)) {
                    setAuditScores(weekly);
                } else if (!cancelled) {
                    setAuditScores([]);
                }
            } catch (err) {
                if (!cancelled) {
                    console.error('Failed to fetch audit data:', err);
                    setError('Failed to load audit data');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        fetchAuditData();
        return () => { cancelled = true; };
    }, [fetchDecayAlerts]);

    return (
        <div>
            <div className="mb-20" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 12 }}>
                <div>
                    <h1 className="page-title">AI Audit Dashboard</h1>
                    <div className="page-subtitle">Weekly citation audit — 20 golden queries × 4 AI engines × 4 categories</div>
                </div>
                {canRunAudit && (
                    <button className="btn btn-primary" onClick={() => addNotification({ type: 'info', message: 'Audit run queued (AI Ops / Admin only)' })}>
                        Run AI Audit
                    </button>
                )}
            </div>

            {loading && <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>Loading audit data...</div>}
            {error && <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)' }}>{error}</div>}
            
            {!loading && !error && (
            <div>
                <div className="flex gap-12 mb-18">
                    <StatCard label="Decay Alerts" value={String(decayAlerts.length)} sub="Hero SKUs with consecutive zero" color={decayAlerts.length > 0 ? "var(--red)" : "var(--text-muted)"} />
                    <StatCard label="Citation trend" value={auditScores.length > 0 ? `${auditScores.length} weeks` : "—"} sub="From audit runs" />
                </div>

            <div className="card mb-18">
                <SectionTitle sub="Citation rate trend per category (when weekly audit data exists)">Citation Trends</SectionTitle>
                {auditScores.length > 0 ? (
                <div className="flex gap-20 flex-wrap">
                    <div style={{ flex: 1, minWidth: 300 }}>
                        {[
                            { cat: "Cables", data: auditScores.map(s => s.cables), color: COLORS.hero },
                            { cat: "Pendants", data: auditScores.map(s => s.pendants), color: COLORS.harvest },
                            { cat: "Bulbs", data: auditScores.map(s => s.bulbs), color: COLORS.accent },
                            { cat: "Lampshades", data: auditScores.map(s => s.lampshades), color: COLORS.support },
                        ].filter(line => line.data.length > 0).map(line => (
                            <div key={line.cat} className="mb-12">
                                <div className="flex items-center gap-8 mb-4">
                                    <div style={{ width: 10, height: 3, borderRadius: 2, background: line.color }} />
                                    <span style={{ fontSize: "0.65rem", color: "var(--text-muted)" }}>{line.cat}</span>
                                    <span style={{ fontSize: "0.7rem", color: line.color, fontFamily: "var(--mono)", fontWeight: 700 }}>{line.data[line.data.length - 1]}%</span>
                                </div>
                                <TrendLine data={line.data} width={380} height={30} color={line.color} />
                            </div>
                        ))}
                    </div>
                </div>
                ) : (
                    <div style={{ padding: 16, color: 'var(--text-dim)', fontSize: '0.85rem' }}>No weekly citation trend data yet. Run AI audits to populate.</div>
                )}
            </div>

            <div className="card">
                <SectionTitle sub="Hero SKUs with consecutive zero citation weeks — decay stage">Decay Alerts</SectionTitle>
                {decayAlerts.length === 0 ? (
                    <div style={{ padding: 16, color: 'var(--text-dim)', fontSize: '0.85rem' }}>No decay alerts.</div>
                ) : (
                    decayAlerts.map((alert) => (
                        <div key={alert.sku_id || alert.sku_code} className="flex items-center justify-between" style={{ padding: "12px 0", borderBottom: '1px solid var(--border-light)' }}>
                            <div>
                                <div className="flex items-center gap-8">
                                    <span style={{ fontSize: "0.55rem", padding: "2px 6px", background: COLORS.redBg, border: `1px solid ${COLORS.redBorder}`, borderRadius: 3, color: COLORS.red, fontWeight: 700 }}>WEEK {alert.consecutive_zero_weeks ?? alert.weeks ?? 0}</span>
                                    <span style={{ fontSize: "0.80rem", fontWeight: 600, color: "var(--text)", fontFamily: "var(--mono)" }}>{alert.sku_code ?? alert.sku}</span>
                                    <span style={{ fontSize: "0.7rem", color: "var(--text-muted)" }}>{alert.title ?? alert.name}</span>
                                </div>
                            </div>
                            <span style={{
                                fontSize: "0.58rem", padding: "2px 8px", borderRadius: 3, fontWeight: 700, textTransform: 'uppercase',
                                background: COLORS.amberBg, color: COLORS.amber, border: `1px solid ${COLORS.amberBorder}`,
                            }}>{alert.decay_status ?? alert.status ?? '—'}</span>
                        </div>
                    ))
                )}
            </div>
            </div>
            )}
        </div>
    );
};

export default AiAudit;
