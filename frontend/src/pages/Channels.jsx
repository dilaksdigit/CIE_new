import React, { useContext } from 'react';
import {
    StatCard,
    SectionTitle,
    TrafficLight
} from '../components/common/UIComponents';
import { configApi, dashboardApi } from '../services/api';
import { AppContext } from '../App';
import { canModifyConfig } from '../lib/rbac';
import THEME from '../theme';

// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 3 — six AI readiness components (0–100 each)
const AI_READINESS_COMPONENTS = [
    ['answer_block', 'Answer Block'],
    ['faq_coverage', 'FAQ Coverage'],
    ['safety_depth', 'Safety & Compliance Depth'],
    ['cross_sku_comparison', 'Cross-SKU Comparison'],
    ['structured_data', 'Structured Data'],
    ['citation_score', 'AI Citation Score'],
];

const Channels = () => {
    const { user } = useContext(AppContext);
    const canReadConfig = canModifyConfig(user);
    const [thresholds, setThresholds] = React.useState(null);
    const lastConfigRef = React.useRef(null);
    const [channelStats, setChannelStats] = React.useState([]);

    React.useEffect(() => {
        if (!canReadConfig) return;
        configApi.get().then(res => {
            const raw = res.data?.data ?? res.data ?? {};
            lastConfigRef.current = raw;
            setThresholds(raw);
        }).catch(() => {});
    }, [canReadConfig]);

    React.useEffect(() => {
        dashboardApi.getChannelStats()
            .then(res => {
                const raw = res.data?.data ?? res.data ?? [];
                const list = Array.isArray(raw) ? raw : [];
                setChannelStats(list);
            })
            .catch(() => setChannelStats([]))
            .finally(() => {});
    }, []);

    const config = thresholds ?? lastConfigRef.current;
    const heroMin = config?.readiness?.hero_primary_channel_min;
    const supportMin = config?.readiness?.support_primary_channel_min;

    const CHANNEL_ORDER = ['Shopify', 'Google Merchant Center'];
    const displayStats = channelStats.length > 0
        ? channelStats
        : CHANNEL_ORDER.map(ch => ({ ch, score: 0, compete: 0, skip: 0, ai_readiness: {} }));

    const portfolioAiReadiness = React.useMemo(() => {
        const withBreakdown = displayStats.filter((ch) => ch.ai_readiness && typeof ch.ai_readiness === 'object');
        if (withBreakdown.length === 0) return null;
        const acc = {};
        AI_READINESS_COMPONENTS.forEach(([key]) => {
            acc[key] = 0;
        });
        withBreakdown.forEach((ch) => {
            AI_READINESS_COMPONENTS.forEach(([key]) => {
                acc[key] += Number(ch.ai_readiness[key] ?? 0);
            });
        });
        const n = withBreakdown.length;
        return Object.fromEntries(
            AI_READINESS_COMPONENTS.map(([key]) => [key, Math.round(acc[key] / n)])
        );
    }, [displayStats]);

    const rules = [
        { rule: heroMin != null ? `Hero SKUs must score ≥${heroMin}% to be included in channel feeds` : 'Hero SKUs must meet minimum readiness to be included in channel feeds', status: "ENFORCED" },
        { rule: supportMin != null ? `Support SKUs must score ≥${supportMin}% for Shopify and Google Merchant Center` : 'Support SKUs must meet minimum readiness for Shopify and GMC', status: "ENFORCED" },
        { rule: "Harvest SKUs are excluded from paid channels (Shopping)", status: "ENFORCED" },
        { rule: "Kill SKUs are excluded from ALL channel feeds", status: "ENFORCED" },
        { rule: "Google Merchant Center feed regenerates nightly at 02:00 UTC", status: "SCHEDULED" },
    ];

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Channel Governor</h1>
                <div className="page-subtitle">Portfolio-level channel readiness — COMPETE/SKIP decisions</div>
            </div>

            <div className="flex gap-14 mb-18 flex-wrap">
                {displayStats.map(ch => (
                    <div key={ch.ch} className="card" style={{ flex: 1, minWidth: 200 }}>
                        <div style={{ fontSize: "0.75rem", fontWeight: 700, color: "var(--text)", marginBottom: 12 }}>{ch.ch}</div>
                        <div style={{ fontSize: "1.8rem", fontWeight: 800, color: "var(--accent)", fontFamily: "var(--mono)" }}>{ch.score}%</div>
                        <div style={{ fontSize: "0.58rem", color: "var(--text-dim)", marginTop: 4 }}>avg readiness</div>
                        <div className="flex gap-8" style={{ marginTop: 12 }}>
                            <span style={{ fontSize: "0.58rem", padding: "2px 6px", background: "var(--green-bg)", color: "var(--green)", borderRadius: 3, border: `1px solid ${THEME.greenBorder}` }}>COMPETE: {ch.compete}</span>
                            <span style={{ fontSize: "0.58rem", padding: "2px 6px", background: "var(--red-bg)", color: "var(--red)", borderRadius: 3, border: `1px solid ${THEME.redBorder}` }}>SKIP: {ch.skip}</span>
                        </div>
                    </div>
                ))}
            </div>

            {portfolioAiReadiness && (
                <div className="card mb-18">
                    <SectionTitle sub="Portfolio average across active channels (0–100 per component)">
                        AI Readiness Breakdown
                    </SectionTitle>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                        {AI_READINESS_COMPONENTS.map(([key, label]) => {
                            const value = portfolioAiReadiness[key] ?? 0;
                            const tone =
                                value >= 70 ? THEME.green : value >= 40 ? THEME.amber : THEME.red;
                            const bg =
                                value >= 70 ? THEME.greenBg : value >= 40 ? THEME.amberBg : THEME.redBg;
                            return (
                                <div
                                    key={key}
                                    style={{
                                        padding: 12,
                                        background: 'var(--surface-alt)',
                                        borderRadius: 4,
                                        border: `1px solid ${THEME.border}`,
                                        fontSize: '0.7rem',
                                    }}
                                >
                                    <div style={{ color: 'var(--text-muted)', marginBottom: 6 }}>{label}</div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <div
                                            style={{
                                                flex: 1,
                                                height: 6,
                                                background: THEME.border,
                                                borderRadius: 999,
                                                overflow: 'hidden',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    width: `${Math.min(100, Math.max(0, value))}%`,
                                                    height: '100%',
                                                    background: tone,
                                                }}
                                            />
                                        </div>
                                        <span
                                            style={{
                                                fontFamily: 'var(--mono)',
                                                fontWeight: 700,
                                                color: tone,
                                                minWidth: 36,
                                                textAlign: 'right',
                                            }}
                                        >
                                            {value}%
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            <div className="card">
                <SectionTitle sub={heroMin != null && supportMin != null ? `Hero ≥${heroMin} to compete, Support ≥${supportMin}, Harvest/Kill excluded` : 'Channel eligibility thresholds from Business Rules'}>Channel Eligibility Rules</SectionTitle>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                    {rules.map((r, i) => (
                        <div key={i} className="flex justify-between items-start gap-8" style={{
                            padding: 12, background: "var(--surface-alt)", borderRadius: 4,
                            border: "1px solid var(--border)", fontSize: "0.7rem", color: "var(--text-muted)",
                        }}>
                            <span>{r.rule}</span>
                            <span style={{
                                flexShrink: 0, padding: "2px 6px", borderRadius: 3, fontSize: "0.55rem", fontWeight: 700,
                                background: r.status === "ENFORCED" ? "var(--green-bg)" : "var(--orange-bg)",
                                color: r.status === "ENFORCED" ? "var(--green)" : "var(--orange)",
                                border: `1px solid ${r.status === "ENFORCED" ? THEME.greenBorder : THEME.amberBorder}`,
                            }}>{r.status}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default Channels;
