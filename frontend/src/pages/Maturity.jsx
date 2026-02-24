import React, { useState, useEffect, useCallback } from 'react';
import {
    TierBadge,
    ReadinessBar,
    SectionTitle
} from '../components/common/UIComponents';
import { dashboardApi } from '../services/api';

const HEATMAP_CHANNELS = ['own_website', 'google_sge', 'amazon', 'ai_assistants'];

const Maturity = () => {
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchSummary = useCallback(async () => {
        try {
            const res = await dashboardApi.getSummary();
            setSummary(res.data?.data ?? null);
        } catch (e) {
            console.error('Maturity summary failed:', e);
            setError('Failed to load maturity data');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchSummary();
    }, [fetchSummary]);

    const tierSummary = summary?.tier_summary ?? [];
    const categoryHeatmap = summary?.category_heatmap ?? [];

    const TIERS = {
        HERO: { bg: "#FDF6E3", border: "#E8D5A0", color: "#8B6914" },
        SUPPORT: { bg: "#EBF3F9", border: "#B5D0E3", color: "#3D6B8E" },
        HARVEST: { bg: "#FFF8E7", border: "#E8D49A", color: "#9E7C1A" },
        KILL: { bg: "#FDEEEB", border: "#E5B5AD", color: "#A63D2F" },
    };

    if (loading) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>Loading maturity dashboard...</div>;
    if (error) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)' }}>{error}</div>;

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Maturity Dashboard</h1>
                <div className="page-subtitle">Boardroom view — decomposed readiness by category and component</div>
            </div>

            {/* Category breakdown: avg readiness per category from heatmap */}
            {categoryHeatmap.length > 0 && (
                <div className="flex gap-14 mb-20 flex-wrap">
                    {categoryHeatmap.map((row) => {
                        const channelScores = HEATMAP_CHANNELS.map(ch => row[ch] ?? 0).filter(Boolean);
                        const avgPct = channelScores.length ? Math.round(channelScores.reduce((a, b) => a + b, 0) / channelScores.length) : 0;
                        const color = avgPct > 85 ? "var(--green)" : avgPct >= 60 ? "var(--amber)" : "var(--red)";
                        return (
                            <div key={row.category} className="card" style={{ flex: 1, minWidth: 220 }}>
                                <div className="flex justify-between items-center mb-14">
                                    <span style={{ fontSize: "0.85rem", fontWeight: 700, color: "var(--text)" }}>{row.category}</span>
                                    <span style={{ fontSize: "1.4rem", fontWeight: 800, color, fontFamily: "var(--mono)" }}>{avgPct}%</span>
                                </div>
                                {HEATMAP_CHANNELS.map(ch => {
                                    const score = row[ch] ?? 0;
                                    const label = ch.replace(/_/g, ' ');
                                    return (
                                        <div key={ch} className="mb-8">
                                            <div className="flex justify-between mb-4">
                                                <span style={{ fontSize: "0.62rem", color: "var(--text-muted)", textTransform: 'capitalize' }}>{label}</span>
                                                <span style={{ fontSize: "0.62rem", color: "var(--text)", fontFamily: "var(--mono)", fontWeight: 600 }}>{score}%</span>
                                            </div>
                                            <div style={{ height: 4, background: "var(--border)", borderRadius: 2, overflow: "hidden" }}>
                                                <div style={{ width: `${score}%`, height: "100%", background: color, borderRadius: 2, opacity: 0.75 }} />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Tier maturity rates from API */}
            <div className="card">
                <SectionTitle sub="Count and average readiness per tier">Tier Maturity Rates</SectionTitle>
                <div className="flex gap-16 flex-wrap">
                    {tierSummary.map((t) => (
                        <div key={t.tier} style={{
                            flex: 1, minWidth: 160, padding: 14,
                            background: TIERS[t.tier]?.bg ?? 'var(--surface-alt)', border: `1px solid ${TIERS[t.tier]?.border ?? 'var(--border)'}`, borderRadius: 6,
                        }}>
                            <TierBadge tier={t.tier} size="sm" />
                            <div style={{ fontSize: "1.6rem", fontWeight: 800, color: TIERS[t.tier]?.color ?? 'var(--text)', fontFamily: "var(--mono)", marginTop: 8 }}>{t.avg_readiness ?? 0}%</div>
                            <div style={{ fontSize: "0.58rem", color: "var(--text-dim)", marginTop: 4 }}>{t.count} SKUs · avg readiness</div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default Maturity;
