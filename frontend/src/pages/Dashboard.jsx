import React from 'react';
import {
    StatCard,
    SectionTitle,
    DonutChart,
    TierBadge,
    GateChip,
    ReadinessBar,
    GATES
} from '../components/common/UIComponents';
import { skuApi, dashboardApi } from '../services/api';

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
  amber: "#F57F17",
  amberBg: "#FFFDE7",
  amberBorder: "#FFCC80",
  blue: "#1565C0",
  blueBg: "#E3F2FD",
  blueBorder: "#90CAF9",
};

const CHANNEL_LABELS = {
    own_website: 'Own Website',
    google_sge: 'Google SGE',
    amazon: 'Amazon',
    ai_assistants: 'AI Assistants',
};

const HEATMAP_CHANNELS = ['own_website', 'google_sge', 'amazon', 'ai_assistants'];

const heatmapColor = (score) => {
    if (score > 85) return COLORS.green;
    if (score >= 60) return COLORS.amber;
    return COLORS.red;
};

const Dashboard = () => {
    const [skus, setSkus] = React.useState([]);
    const [summary, setSummary] = React.useState(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);

    // Filter states
    const [searchTerm, setSearchTerm] = React.useState('');
    const [tierFilter, setTierFilter] = React.useState('All Tiers');
    const [categoryFilter, setCategoryFilter] = React.useState('All Categories');

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

    const heroCount = skus.filter(s => s.tier === "HERO").length;
    const supportCount = skus.filter(s => s.tier === "SUPPORT").length;
    const harvestCount = skus.filter(s => s.tier === "HARVEST").length;
    const killCount = skus.filter(s => s.tier === "KILL").length;
    const avgReadiness = skus.length > 0 ? Math.round(skus.reduce((a, s) => a + (s.readiness_score || 0), 0) / skus.length) : 0;
    const tierSummary = summary?.tier_summary ?? [];
    const categoryHeatmap = summary?.category_heatmap ?? [];
    const decayMonitor = summary?.decay_monitor ?? [];
    const effortAllocation = summary?.effort_allocation ?? { by_tier: [], hero_pct: 0, hero_alert: false };

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Portfolio Overview</h1>
                <div className="page-subtitle">Real-time portfolio health across all categories</div>
            </div>

            <div className="flex gap-12 mb-20 flex-wrap">
                <StatCard label="Total SKUs" value={skus.length.toString()} sub="Connected to API" />
                <StatCard label="Avg Readiness" value={`${avgReadiness}%`} color={avgReadiness >= 70 ? 'var(--green)' : 'var(--orange)'} />
                <StatCard 
                    label="AI Citation Rate" 
                    value={`${Math.round(skus.length > 0 ? skus.reduce((a, s) => a + (s.ai_citation_rate || 0), 0) / skus.length : 0)}%`}
                    color="var(--accent)" 
                />
                <StatCard label="Pending Validation" value={`${skus.filter(s => s.validation_status === 'PENDING').length}`} sub={`${skus.filter(s => s.validation_status === 'FAILED').length} failed`} color="var(--orange)" />
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

            {/* Category Heatmap: category × channel, green >85, yellow 60–85, red <60 */}
            {categoryHeatmap.length > 0 && (
                <div className="card mb-20">
                    <SectionTitle sub="Avg readiness per category × channel (green >85, yellow 60–85, red <60)">Category Heatmap</SectionTitle>
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
                                            const bg = score > 85 ? 'var(--green-bg)' : score >= 60 ? 'var(--amber-bg)' : 'var(--red-bg)';
                                            const color = heatmapColor(score);
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
                            { value: skus.length > 0 ? (heroCount / skus.length) * 100 : 0, color: COLORS.hero },
                            { value: skus.length > 0 ? (supportCount / skus.length) * 100 : 0, color: COLORS.support },
                            { value: skus.length > 0 ? (harvestCount / skus.length) * 100 : 0, color: COLORS.harvest },
                            { value: skus.length > 0 ? (killCount / skus.length) * 100 : 0, color: COLORS.kill },
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
                                    color: COLORS[t.tier?.toLowerCase()] || COLORS.accent,
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
                            <option>Cables</option>
                            <option>Lampshades</option>
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
                                    <td>{sku.primaryCluster?.name || sku.primaryCluster?.category || '—'}</td>
                                    <td>
                                        <div className="flex gap-4 flex-wrap">
                                            {GATES.map(g => (
                                                <GateChip 
                                                    key={g.id} 
                                                    id={g.id} 
                                                    pass={sku.gates?.[g.id]?.passed || false} 
                                                    compact 
                                                />
                                            ))}
                                        </div>
                                    </td>
                                    <td><ReadinessBar value={sku.readiness_score || 0} /></td>
                                    <td className="mono">
                                        <span style={{ color: (sku.ai_citation_rate || 0) >= 50 ? 'var(--green)' : (sku.ai_citation_rate || 0) >= 25 ? 'var(--orange)' : 'var(--red)', fontWeight: 600 }}>
                                            {sku.ai_citation_rate ?? 0}%
                                        </span>
                                    </td>
                                    <td>
                                        <span style={{ fontSize: '0.7rem', textTransform: 'uppercase', fontWeight: 600 }}>
                                            {sku.decay_status && sku.decay_status !== 'none' ? sku.decay_status : '—'}
                                        </span>
                                    </td>
                                    <td className="mono">{sku.score_citation != null ? `${sku.score_citation}%` : (sku.ai_citation_rate != null ? `${sku.ai_citation_rate}%` : '—')}</td>
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
