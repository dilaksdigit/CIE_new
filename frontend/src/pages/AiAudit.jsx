import React, { useState, useEffect, useCallback, useContext } from 'react';
import {
    StatCard,
    TrendLine,
    SectionTitle
} from '../components/common/UIComponents';
import { auditResultApi } from '../services/api';
import { AppContext } from '../App';
import { canRunAIAudit } from '../lib/rbac';
import THEME from '../theme';

const ENGINE_KEYS = ['chatgpt', 'gemini', 'perplexity', 'google_sge'];

/** Week 3+ decay rows: brief completion / overdue from dashboard decay-alerts payload. */
function decayBriefBadge(alert) {
    const ds = String(alert.decay_status || alert.status || '').toLowerCase();
    if (!['auto_brief', 'escalated'].includes(ds)) {
        return null;
    }
    const bs = String(alert.brief_status || '').toLowerCase();
    const deadline = alert.brief_deadline;
    const completedAt = alert.brief_completed_at;
    const done = bs === 'completed' || !!completedAt;
    const dateDone = (completedAt || '').slice(0, 10);
    if (done) {
        return {
            text: dateDone ? `Brief completed ${dateDone}` : 'Brief completed',
            bg: THEME.greenBg,
            border: THEME.greenBorder,
            color: THEME.green,
        };
    }
    let overdue = bs === 'overdue';
    if (deadline && !done) {
        const t = new Date(`${deadline}T12:00:00Z`);
        if (!Number.isNaN(t.getTime()) && t < new Date()) {
            overdue = true;
        }
    }
    const dueBit = deadline ? ` — due ${deadline}` : '';
    if (overdue) {
        return {
            text: `Brief OVERDUE${dueBit}`,
            bg: THEME.redBg,
            border: THEME.redBorder,
            color: THEME.red,
        };
    }
    return {
        text: deadline ? `Brief due ${deadline}` : 'Brief pending',
        bg: THEME.amberBg,
        border: THEME.amberBorder,
        color: THEME.amber,
    };
}

const AiAudit = () => {
    const [auditScores, setAuditScores] = useState([]);
    const [decayAlerts, setDecayAlerts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { user, addNotification } = useContext(AppContext);
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
                const weekly = weeklyRes.data?.scores;
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

    const sortedScores = [...auditScores].sort((a, b) =>
        String(a.week_start_date || '').localeCompare(String(b.week_start_date || ''))
    );
    const byWeek = {};
    sortedScores.forEach((s) => {
        const w = s.week_start_date;
        if (!w) return;
        if (!byWeek[w]) byWeek[w] = { sumAvg: 0, n: 0, zeros: 0 };
        byWeek[w].sumAvg += Number(s.avg_score ?? 0);
        byWeek[w].n += 1;
        byWeek[w].zeros += Number(s.questions_at_zero ?? 0);
    });
    const weekKeys = Object.keys(byWeek).sort();
    const avgCitationSeries = weekKeys.map((w) =>
        byWeek[w].n ? byWeek[w].sumAvg / byWeek[w].n : 0
    );
    const engineLineData = ENGINE_KEYS.map((eng) => ({
        eng,
        label: eng.replace('_', ' '),
        data: weekKeys.map((w) => {
            const entries = sortedScores.filter((s) => s.week_start_date === w);
            if (!entries.length) return 0;
            const vals = entries
                .map((s) => Number(s.engine_scores?.[eng] ?? 0))
                .filter((x) => !Number.isNaN(x));
            return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : 0;
        }),
        color: eng === 'chatgpt' ? THEME.accent : eng === 'gemini' ? '#3D6B8E' : eng === 'perplexity' ? '#8B6914' : '#6B6B6B',
    }));
    const latestWeek = weekKeys.length ? weekKeys[weekKeys.length - 1] : null;
    const latestZeros = latestWeek ? byWeek[latestWeek].zeros : 0;

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
                    <StatCard label="Questions at zero (latest week)" value={latestWeek ? String(latestZeros) : "—"} sub={latestWeek ? `Week of ${latestWeek}` : "No audit aggregates yet"} color={latestZeros > 0 ? "var(--amber)" : "var(--text-muted)"} />
                </div>

            <div className="card mb-18">
                <SectionTitle sub="Avg citation score (0–3) and per-engine averages from ai_audit_results">Citation Trends</SectionTitle>
                {weekKeys.length > 0 ? (
                <div className="flex gap-20 flex-wrap">
                    <div style={{ flex: 1, minWidth: 300 }}>
                        <div className="mb-12">
                            <div className="flex items-center gap-8 mb-4">
                                <div style={{ width: 10, height: 3, borderRadius: 2, background: THEME.accent }} />
                                <span style={{ fontSize: "0.65rem", color: "var(--text-muted)" }}>Avg Citation Score (0–3)</span>
                                <span style={{ fontSize: "0.7rem", color: THEME.accent, fontFamily: "var(--mono)", fontWeight: 700 }}>
                                    {avgCitationSeries.length ? avgCitationSeries[avgCitationSeries.length - 1].toFixed(2) : '—'}
                                </span>
                            </div>
                            <TrendLine data={avgCitationSeries} width={380} height={30} color={THEME.accent} />
                        </div>
                        {engineLineData.map((line) => (
                            <div key={line.eng} className="mb-12">
                                <div className="flex items-center gap-8 mb-4">
                                    <div style={{ width: 10, height: 3, borderRadius: 2, background: line.color }} />
                                    <span style={{ fontSize: "0.65rem", color: "var(--text-muted)" }}>{line.label}</span>
                                    <span style={{ fontSize: "0.7rem", color: line.color, fontFamily: "var(--mono)", fontWeight: 700 }}>
                                        {line.data.length ? line.data[line.data.length - 1].toFixed(2) : '—'}
                                    </span>
                                </div>
                                <TrendLine data={line.data} width={380} height={30} color={line.color} />
                            </div>
                        ))}
                    </div>
                </div>
                ) : (
                    <div style={{ padding: 16, color: 'var(--text-dim)', fontSize: '0.85rem' }}>No weekly AI audit aggregates yet. Run the weekly citation audit job to populate ai_audit_results.</div>
                )}
            </div>

            <div className="card">
                <SectionTitle sub="Hero SKUs with consecutive zero citation weeks — decay stage">Decay Alerts</SectionTitle>
                {decayAlerts.length === 0 ? (
                    <div style={{ padding: 16, color: 'var(--text-dim)', fontSize: '0.85rem' }}>No decay alerts.</div>
                ) : (
                    decayAlerts.map((alert) => {
                        const brief = decayBriefBadge(alert);
                        return (
                        <div key={alert.sku_id || alert.sku_code} className="flex items-center justify-between" style={{ padding: "12px 0", borderBottom: '1px solid var(--border-light)' }}>
                            <div>
                                <div className="flex items-center gap-8">
                                    <span style={{ fontSize: "0.55rem", padding: "2px 6px", background: THEME.redBg, border: `1px solid ${THEME.redBorder}`, borderRadius: 3, color: THEME.red, fontWeight: 700 }}>WEEK {alert.consecutive_zero_weeks ?? alert.weeks ?? 0}</span>
                                    <span style={{ fontSize: "0.80rem", fontWeight: 600, color: "var(--text)", fontFamily: "var(--mono)" }}>{alert.sku_code ?? alert.sku}</span>
                                    <span style={{ fontSize: "0.7rem", color: "var(--text-muted)" }}>{alert.title ?? alert.name}</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-8" style={{ flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                                {brief && (
                                    <span style={{
                                        fontSize: "0.58rem", padding: "2px 8px", borderRadius: 3, fontWeight: 700,
                                        background: brief.bg, color: brief.color, border: `1px solid ${brief.border}`,
                                    }}>{brief.text}</span>
                                )}
                                <span style={{
                                    fontSize: "0.58rem", padding: "2px 8px", borderRadius: 3, fontWeight: 700, textTransform: 'uppercase',
                                    background: THEME.amberBg, color: THEME.amber, border: `1px solid ${THEME.amberBorder}`,
                                }}>{alert.decay_status ?? alert.status ?? '—'}</span>
                            </div>
                        </div>
                        );
                    })
                )}
            </div>
            </div>
            )}
        </div>
    );
};

export default AiAudit;
