// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.2 — Reviewer Dashboard Screen
//         (Review Dashboard: maturity overview, AI audit summary, weekly KPI score)
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §4.1 — Route Map /review/dashboard
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §4.2 — Screen Map, REVIEWER view

import React from 'react';
import {
    StatCard,
    SectionTitle,
    DonutChart,
    TierBadge,
    GateChip,
    ReadinessBar,
    GATES,
    getGatesForTier
} from '../components/common/UIComponents';
import { skuApi, dashboardApi, configApi } from '../services/api';
import THEME from '../theme';

// SOURCE: CLAUDE.md Section 4 — DECISION-001 (channels: shopify + gmc only)
const CHANNEL_LABELS = {
    shopify: 'Shopify',
    gmc: 'Google Merchant Center',
};
const HEATMAP_CHANNELS = ['shopify', 'gmc'];

const heatmapColor = (score, greenMin, amberMin) => {
    if (score > greenMin) return THEME.green;
    if (score >= amberMin) return THEME.amber;
    return THEME.red;
};

const ALL_CATEGORIES = ['cables', 'lampshades', 'bulbs', 'pendants', 'floor_lamps', 'ceiling_lights', 'accessories'];

const formatCategory = (cat) => {
    if (!cat) return '—';
    return cat.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const Dashboard = () => {
    const [skus, setSkus] = React.useState([]);
    const [summary, setSummary] = React.useState(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [thresholds, setThresholds] = React.useState(null);

    // Filter states
    const [searchTerm, setSearchTerm] = React.useState('');
    const [tierFilter, setTierFilter] = React.useState('All Tiers');
    const [categoryFilter, setCategoryFilter] = React.useState('All Categories');

    React.useEffect(() => {
        configApi.get().then(res => {
            const raw = res.data?.data ?? res.data ?? {};
            setThresholds(raw);
        }).catch(e => {
            console.error('Failed to load business rules for dashboard:', e);
        });
    }, []);

    const fetchSummary = React.useCallback(async () => {
        try {
            const res = await dashboardApi.getSummary();
            setSummary(res.data?.data ?? null);
        } catch (e) {
            console.error('Dashboard summary failed:', e);
        }
    }, []);

    // Debounce search and fetch SKUs + summary
    React.useEffect(() => {
        let cancelled = false;
        const run = async () => {
            try {
                setLoading(true);
                setError(null);
                const params = {};
                if (searchTerm) params.search = searchTerm;
                if (tierFilter !== 'All Tiers') params.tier = tierFilter.toUpperCase();
                if (categoryFilter !== 'All Categories') params.category = categoryFilter;

                const [skuRes] = await Promise.all([
                    skuApi.list(params),
                    fetchSummary(),
                ]);
                if (!cancelled) {
                    const skuData = skuRes.data?.data ?? [];
                    setSkus(skuData);
                }
            } catch (err) {
                if (!cancelled) {
                    console.error('Failed to fetch dashboard:', err);
                    setError('Failed to load dashboard data');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        const timer = setTimeout(run, 300);
        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [searchTerm, tierFilter, categoryFilter, fetchSummary]);

    if (loading && skus.length === 0 && !summary) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)', height: '100%' }}>Loading portfolio health...</div>;
    if (error) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)', height: '100%' }}>{error}</div>;

    const heroCount = skus.filter(s => (s.tier || '').toUpperCase() === "HERO").length;
    const supportCount = skus.filter(s => (s.tier || '').toUpperCase() === "SUPPORT").length;
    const harvestCount = skus.filter(s => (s.tier || '').toUpperCase() === "HARVEST").length;
    const killCount = skus.filter(s => (s.tier || '').toUpperCase() === "KILL").length;
    const avgReadiness = skus.length > 0 ? Math.round(skus.reduce((a, s) => a + (s.readiness_score || 0), 0) / skus.length) : 0;
    const greenMin = thresholds?.readiness?.hero_primary_channel_min ?? null;
    const amberMin = thresholds?.readiness?.support_primary_channel_min ?? null;
    const tierSummary = summary?.tier_summary ?? [];
    const categoryHeatmap = summary?.category_heatmap ?? [];
    const decayMonitor = summary?.decay_monitor ?? [];
    const effortAllocation = summary?.effort_allocation ?? { by_tier: [], hero_pct: 0, hero_alert: false };
    const rollbackCandidates = summary?.rollback_candidates ?? { sku_ids: [], count: 0 };

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Portfolio Overview</h1>
                <div className="page-subtitle">Real-time portfolio health across all categories</div>
            </div>

            <div className="flex gap-12 mb-20 flex-wrap">
                <StatCard label="Total SKUs" value={skus.length.toString()} sub="Connected to API" />
                <StatCard label="Avg Readiness" value={`${avgReadiness}%`} color={thresholds?.readiness?.hero_all_channels_min == null ? 'var(--text-muted)' : (avgReadiness >= thresholds.readiness.hero_all_channels_min ? 'var(--green)' : 'var(--orange)')} />
                <StatCard 
                    label="AI Citation Rate" 
                    value={`${Math.round(skus.length > 0 ? skus.reduce((a, s) => a + (s.ai_citation_rate || 0), 0) / skus.length : 0)}%`}
                    color="var(--accent)" 
                />
                <StatCard label="Pending Validation" value={`${skus.filter(s => s.validation_status === 'PENDING').length}`} sub={`${skus.filter(s => s.validation_status === 'FAILED').length} failed`} color="var(--orange)" />
                {rollbackCandidates.count > 0 && (
                    <StatCard label="Rollback candidates" value={String(rollbackCandidates.count)} sub="D+30 position worse than baseline" color="var(--amber)" />
                )}
            </div>

            {/* Tier Summary: card per tier — count, avg readiness, avg margin */}
            {tierSummary.length > 0 && (
                <div className="mb-20">
                    <SectionTitle sub="Count, avg readiness, avg margin per tier">Tier Summary</SectionTitle>
                    <div className="flex gap-12 flex-wrap">
                        {tierSummary.map((t) => (
                            <div key={t.tier} className="card" style={{ flex: 1, minWidth: 160 }}>
                                <TierBadge tier={t.tier} size="xs" />
                                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--text)', fontFamily: 'var(--mono)', marginTop: 8 }}>{t.count}</div>
                                <div style={{ fontSize: '0.65rem', color: 'var(--text-dim)' }}>SKUs</div>
                                <div style={{ fontSize: '0.85rem', marginTop: 6 }}>Avg readiness: <strong>{t.avg_readiness ?? 0}%</strong></div>
                                <div style={{ fontSize: '0.85rem' }}>Avg margin: <strong>{t.avg_margin != null ? `${t.avg_margin}%` : '—'}</strong></div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Category Heatmap: category × channel; thresholds from BusinessRules (green/amber/red) */}
            {categoryHeatmap.length > 0 && (
                <div className="card mb-20">
                    <SectionTitle sub={`Avg readiness per category × channel${greenMin != null && amberMin != null ? ` (green >${greenMin}, yellow ${amberMin}–${greenMin}, red <${amberMin})` : ' (loading…)'}`}>Category Heatmap</SectionTitle>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.75rem' }}>
                            <thead>
                                <tr>
                                    <th style={{ textAlign: 'left', padding: '8px 10px', borderBottom: '1px solid var(--border)' }}>Category</th>
                                    {HEATMAP_CHANNELS.map(ch => (
                                        <th key={ch} style={{ padding: '8px 10px', borderBottom: '1px solid var(--border)' }}>{CHANNEL_LABELS[ch] || ch}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {categoryHeatmap.map((row) => (
                                    <tr key={row.category}>
                                        <td style={{ padding: '8px 10px', borderBottom: '1px solid var(--border-light)', fontWeight: 600 }}>{row.category}</td>
                                        {HEATMAP_CHANNELS.map(ch => {
                                            const score = row[ch] ?? 0;
                                            const bg = greenMin !== null && amberMin !== null
                                                ? (score > greenMin ? 'var(--green-bg)' : score >= amberMin ? 'var(--amber-bg)' : 'var(--red-bg)')
                                                : 'transparent';
                                            const color = greenMin !== null && amberMin !== null
                                                ? heatmapColor(score, greenMin, amberMin)
                                                : 'var(--text-muted)';
                                            return (
                                                <td key={ch} style={{ padding: '8px 10px', borderBottom: '1px solid var(--border-light)', background: bg, color, fontWeight: 600, fontFamily: 'var(--mono)' }}>
                                                    {score}%
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <div className="flex gap-14 mb-20 flex-wrap">
                <div className="card" style={{ flex: 1, minWidth: 280 }}>
                    <SectionTitle sub="SKU count by commercial tier">Tier Distribution</SectionTitle>
                    <div className="flex gap-16 items-center">
                        <DonutChart size={110} strokeWidth={14} segments={[
                            { value: skus.length > 0 ? (heroCount / skus.length) * 100 : 0, color: THEME.hero },
                            { value: skus.length > 0 ? (supportCount / skus.length) * 100 : 0, color: THEME.support },
                            { value: skus.length > 0 ? (harvestCount / skus.length) * 100 : 0, color: THEME.harvest },
                            { value: skus.length > 0 ? (killCount / skus.length) * 100 : 0, color: THEME.kill },
                        ]} />
                        <div className="flex flex-col gap-8" style={{ flex: 1 }}>
                            {[
                                { tier: "HERO", count: heroCount, pct: skus.length > 0 ? `${Math.round((heroCount / skus.length) * 100)}%` : "0%" },
                                { tier: "SUPPORT", count: supportCount, pct: skus.length > 0 ? `${Math.round((supportCount / skus.length) * 100)}%` : "0%" },
                                { tier: "HARVEST", count: harvestCount, pct: skus.length > 0 ? `${Math.round((harvestCount / skus.length) * 100)}%` : "0%" },
                                { tier: "KILL", count: killCount, pct: skus.length > 0 ? `${Math.round((killCount / skus.length) * 100)}%` : "0%" },
                            ].map(r => (
                                <div key={r.tier} className="flex items-center justify-between">
                                    <div className="flex items-center gap-8">
                                        <TierBadge tier={r.tier} size="xs" />
                                        <span style={{ fontSize: '0.75rem', color: 'var(--text)' }}>{r.count} SKUs</span>
                                    </div>
                                    <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)', fontFamily: 'var(--mono)' }}>{r.pct}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="card" style={{ flex: 1, minWidth: 280 }}>
                    <SectionTitle sub="% of content hours by tier (this week)">Effort Allocation</SectionTitle>
                    {effortAllocation.by_tier && effortAllocation.by_tier.length > 0 ? (
                        <>
                            <div className="flex gap-16 items-center">
                                <DonutChart size={110} strokeWidth={14} segments={effortAllocation.by_tier.map(t => ({
                                    value: t.pct || 0,
                                    color: THEME[t.tier?.toLowerCase()] || THEME.accent,
                                }))} />
                                <div className="flex flex-col gap-6" style={{ flex: 1 }}>
                                    {effortAllocation.by_tier.map(t => (
                                        <div key={t.tier} className="flex items-center justify-between">
                                            <TierBadge tier={t.tier} size="xs" />
                                            <span style={{ fontFamily: 'var(--mono)' }}>{t.pct}%</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            {effortAllocation.hero_alert && (
                                <div style={{ marginTop: 12, padding: 10, background: 'var(--red-bg)', border: '1px solid var(--red)', borderRadius: 6, fontSize: '0.75rem', color: 'var(--red)', fontWeight: 600 }}>
                                    Alert: Hero content hours &lt; 60% — target &gt;60%
                                </div>
                            )}
                        </>
                    ) : (
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-dim)' }}>No effort data this week.</div>
                    )}
                </div>
            </div>

            {/* Decay Monitor: Hero SKUs with consecutive zero scores, decay stage */}
            {decayMonitor.length > 0 && (
                <div className="card mb-20">
                    <SectionTitle sub="Hero SKUs with consecutive zero citation weeks">Decay Monitor</SectionTitle>
                    <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                        {decayMonitor.map((d) => (
                            <li key={d.sku_id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid var(--border-light)' }}>
                                <div>
                                    <span className="mono" style={{ fontWeight: 600 }}>{d.sku_code}</span>
                                    <span style={{ marginLeft: 8, color: 'var(--text-muted)' }}>{d.title}</span>
                                    <span style={{ marginLeft: 8, fontSize: '0.65rem', padding: '2px 6px', background: 'var(--red-bg)', color: 'var(--red)', borderRadius: 3 }}>{d.consecutive_zero_weeks}w zero</span>
                                </div>
                                <span style={{ fontSize: '0.7rem', fontWeight: 600, textTransform: 'uppercase' }}>{d.decay_status || '—'}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="data-table">
                <div className="table-top">
                    <SectionTitle sub="Click any row to open SKU editor">All SKUs</SectionTitle>
                    <div className="flex gap-8">
                        <input
                            className="search-input"
                            placeholder="Search SKUs..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        <select
                            className="filter-select"
                            value={tierFilter}
                            onChange={(e) => setTierFilter(e.target.value)}
                        >
                            <option>All Tiers</option>
                            <option>Hero</option>
                            <option>Support</option>
                            <option>Harvest</option>
                            <option>Kill</option>
                        </select>
                        <select
                            className="filter-select"
                            value={categoryFilter}
                            onChange={(e) => setCategoryFilter(e.target.value)}
                        >
                            <option>All Categories</option>
                            {ALL_CATEGORIES.map(cat => (
                                <option key={cat} value={cat}>{formatCategory(cat)}</option>
                            ))}
                        </select>
                        <button className="btn btn-secondary" style={{ padding: '4px 10px', fontSize: '0.7rem' }}>Export benefits.csv</button>
                    </div>
                </div>
                <div style={{ overflowX: 'auto' }}>
                    <table>
                        <thead>
                            <tr>
                                <th>SKU ID</th>
                                <th>Product Name</th>
                                <th>Tier</th>
                                <th>Category</th>
                                <th>Gates</th>
                                <th>Readiness</th>
                                <th>Citation</th>
                                <th>Decay</th>
                                <th>Last audit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {skus.map(sku => (
                                <tr key={sku.id}>
                                    <td className="mono">{sku.sku_code}</td>
                                    <td>{sku.title}</td>
                                    <td><TierBadge tier={sku.tier || 'SUPPORT'} size="xs" /></td>
                                    <td>{formatCategory(sku.primaryCluster?.category)}</td>
                                    <td>
                                        <div className="flex gap-4 flex-wrap">
                                            {getGatesForTier(sku.tier).map(g => (
                                                <GateChip 
                                                    key={g.id} 
                                                    id={g.id} 
                                                    pass={sku.gates?.[g.id]?.passed || false} 
                                                    compact 
                                                />
                                            ))}
                                        </div>
                                    </td>
                                    <td><ReadinessBar value={sku.readiness_score || 0} greenThreshold={thresholds?.scoring?.chs_gold_threshold} amberThreshold={thresholds?.scoring?.chs_silver_threshold} /></td>
                                    <td className="mono">
                                        <span style={{ color: (thresholds?.decay?.hero_citation_red_threshold != null
                                            ? ((sku.ai_citation_rate || 0) >= thresholds.decay.hero_citation_red_threshold ? 'var(--orange)' : 'var(--red)')
                                            : 'var(--text-muted)'), fontWeight: 600 }}>
                                            {sku.ai_citation_rate ?? 0}%
                                        </span>
                                    </td>
                                    <td>
                                        <span style={{ fontSize: '0.7rem', textTransform: 'uppercase', fontWeight: 600 }}>
                                            {sku.decay_status && sku.decay_status !== 'none' ? sku.decay_status : '—'}
                                        </span>
                                    </td>
                                    <td className="mono">{sku.last_validated_at ? new Date(sku.last_validated_at).toLocaleDateString() : (sku.updated_at ? new Date(sku.updated_at).toLocaleDateString() : '—')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
