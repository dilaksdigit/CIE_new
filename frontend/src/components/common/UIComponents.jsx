// SOURCE: CLAUDE.md Section 8 (no emojis in production UI); CIE_v232_Developer_Amendment_Pack Section 8 check #7
// SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 6
import React from 'react';

// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §1.5
// SOURCE: CIE_v232_Writer_View.jsx C object
import THEME from '../../theme';

// ─── TIER BADGE ─────────────────────────────────────
export const TierBadge = ({ tier, size = 'sm' }) => (
    <span className={`tier-badge ${tier} ${size}`}>
        {tier.toUpperCase()}
    </span>
);

// ─── GATE CHIP ──────────────────────────────────────
// label = plain English field name (no gate codes in UI per CLAUDE.md §6 / UI restructure §6)
export const GateChip = ({ id, pass, compact, label }) => (
    <span
        className={`gate-chip ${pass ? 'pass' : 'fail'} ${compact ? 'compact' : ''}`}
        data-field-label={label || undefined}
    >
        {label ? (
            <span className="gate-chip-label" style={{ marginRight: 6, fontWeight: 600, fontSize: compact ? '0.65rem' : '0.7rem' }}>
                {label}
            </span>
        ) : null}
        <span className="check">{pass ? 'Pass' : 'Fail'}</span>
    </span>
);

// ─── TRAFFIC LIGHT ──────────────────────────────────
export const TrafficLight = ({ value }) => {
    const cls = value === 'RED' ? 'red' : value === 'GREEN' ? 'green' : 'amber';
    return <span className={`traffic-light ${cls}`}>{value}</span>;
};

// ─── ROLE BADGE ─────────────────────────────────────
// Phase 0 Check 0.3/0.4: display labels per spec — CONTENT_EDITOR/PRODUCT_SPECIALIST → Content Writer; CONTENT_LEAD/SEO_GOVERNOR → KPI Reviewer; ADMIN → Admin
const roleLabel = (r) => {
    if (!r) return '';
    const s = String(r).trim().toLowerCase().replace(/-/g, '_');
    if (s === 'content_editor' || s === 'product_specialist') return 'Content Writer';
    if (s === 'content_lead' || s === 'seo_governor') return 'KPI Reviewer';
    if (s === 'admin') return 'Admin';
    return String(r).trim().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};
export { roleLabel };
export const RoleBadge = ({ role }) => {
    const normalized = role ? String(role).toLowerCase().replace(/\s+/g, '_').replace(/-/g, '_') : '';
    return (
        <span className={`role-badge ${normalized}`} title={role}>
            {roleLabel(role)}
        </span>
    );
};

// ─── READINESS BAR ──────────────────────────────────
export const ReadinessBar = ({ value, width = 80, greenThreshold, amberThreshold }) => {
    const hasThresholds = greenThreshold != null && amberThreshold != null;
    const barColor = hasThresholds
        ? (value >= greenThreshold ? 'var(--green)' : value >= amberThreshold ? 'var(--amber)' : 'var(--red)')
        : 'var(--text-muted)';
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <div style={{ width, height: 6, background: 'var(--border)', borderRadius: 3, overflow: 'hidden' }}>
                <div style={{
                    width: `${value}%`, height: '100%', borderRadius: 3,
                    background: barColor,
                    transition: 'width 0.6s ease',
                }} />
            </div>
            <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)', fontFamily: 'var(--mono)', minWidth: 28 }}>{value}%</span>
        </div>
    );
};

// ─── STAT CARD ──────────────────────────────────────
export const StatCard = ({ label, value, sub, color, icon }) => (
    <div className="stat-card">
        <div className="stat-label">{label}</div>
        <div className="stat-value" style={{ color: color || 'var(--text)' }}>
            {icon && <span style={{ marginRight: 6, fontSize: '1rem' }}>{icon}</span>}{value}
        </div>
        {sub && <div className="stat-sub">{sub}</div>}
    </div>
);

// ─── SECTION TITLE ──────────────────────────────────
export const SectionTitle = ({ children, sub }) => (
    <div style={{ marginBottom: 14 }}>
        <h2 className="section-title">{children}</h2>
        {sub && <div className="section-sub">{sub}</div>}
    </div>
);

// ─── DONUT CHART ────────────────────────────────────
export const DonutChart = ({ segments, size = 100, strokeWidth = 12 }) => {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;
    return (
        <svg width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
            <circle cx={size / 2} cy={size / 2} r={radius} fill="none" stroke="var(--border)" strokeWidth={strokeWidth} />
            {segments.map((seg, i) => {
                const dash = (seg.value / 100) * circumference;
                const o = offset;
                offset += dash;
                return (
                    <circle key={i} cx={size / 2} cy={size / 2} r={radius} fill="none"
                        stroke={seg.color} strokeWidth={strokeWidth}
                        strokeDasharray={`${dash} ${circumference - dash}`}
                        strokeDashoffset={-o} strokeLinecap="round" />
                );
            })}
        </svg>
    );
};

// ─── TREND LINE ─────────────────────────────────────
export const TrendLine = ({ data, width = 300, height = 60, color = 'var(--accent)' }) => {
    const values = Array.isArray(data) ? data.map((v) => Number(v)).filter((v) => Number.isFinite(v)) : [];

    // Nothing to plot
    if (values.length === 0) {
        return <svg width={width} height={height} style={{ display: 'block' }} />;
    }

    const max = Math.max(...values);
    const rawMin = Math.min(...values);
    const min = rawMin * 0.8;
    const span = max - min || 1; // avoid division by zero when max === min

    const denom = (values.length - 1) || 1; // avoid division by zero when only one point

    const points = values.map((v, i) => {
        const x = (i / denom) * width;
        const y = height - ((v - min) / span) * height;
        return `${x},${y}`;
    }).join(' ');

    return (
        <svg width={width} height={height} style={{ display: 'block' }}>
            <polyline points={points} fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            {values.map((v, i) => {
                const x = (i / denom) * width;
                const y = height - ((v - min) / span) * height;
                return <circle key={i} cx={x} cy={y} r={3} fill={color} />;
            })}
        </svg>
    );
};

// ─── MINI BAR CHART ─────────────────────────────────
export const MiniBarChart = ({ data, width = 240, height = 80 }) => {
    const max = Math.max(...data.map(d => d.value));
    const barW = Math.floor((width - (data.length - 1) * 4) / data.length);
    return (
        <svg width={width} height={height + 20} style={{ display: 'block' }}>
            {data.map((d, i) => {
                const barH = (d.value / max) * height;
                return (
                    <g key={i}>
                        <rect x={i * (barW + 4)} y={height - barH} width={barW} height={barH}
                            fill={d.color || 'var(--accent)'} rx={2} opacity={0.8} />
                        <text x={i * (barW + 4) + barW / 2} y={height + 14}
                            textAnchor="middle" fill="var(--text-dim)" fontSize="9" fontFamily="var(--mono)">
                            {d.label}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
};

// ─── CHANNEL BADGE ──────────────────────────────────
// SOURCE: CLAUDE.md Section 4 (DECISION-001) — Amazon deferred. Channels: shopify, gmc only.
export const ChannelBadge = ({ channel }) => {
    const map = { eBay: THEME.accent, Shopify: THEME.support, Website: THEME.textMid };
    const bg = map[channel] || 'var(--text-muted)';
    return (
        <span style={{
            display: 'inline-flex', padding: '2px 9px', borderRadius: 3,
            background: bg, color: THEME.surface, fontSize: '0.6rem', fontWeight: 700,
            letterSpacing: '0.04em', textTransform: 'uppercase',
        }}>{channel}</span>
    );
};

export const GATES = [
    { id: 'G1', label: 'Cluster ID', desc: 'Semantic cluster assigned' },
    // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §6, ENF§2.1 — G2 = Primary Intent
    { id: 'G2', label: 'Primary Intent', desc: 'Valid primary intent from taxonomy' },
    { id: 'G3', label: 'Intents', desc: 'Primary + secondary intents' },
    // SOURCE: UI§6 — no hard-coded thresholds in UI labels
    { id: 'G4', label: 'Answer Block', desc: 'Answer block length and intent keyword' },
    { id: 'G5', label: 'Best/Not-For', desc: 'Use case guidance' },
    { id: 'G6', label: 'Tier / Commercial', desc: 'Tier tag and commercial policy' },
    { id: 'tier_fields', label: 'Tier Fields', desc: 'Tier-gated content' },
    { id: 'G7', label: 'Authority', desc: 'Expert authority block' },
    { id: 'VEC', label: 'Vector', desc: 'Description must align with product cluster intent.' },
];

// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3
// SOURCE: ENF§2.2 — Harvest: G1 REQUIRED, G2 REQUIRED (Spec only), G6 REQUIRED
// Kill = no gates active. Hero/Support = all gates.
export const getGatesForTier = (tier) => {
    if (!tier || tier.toLowerCase() === 'kill') return [];
    if (tier.toLowerCase() === 'harvest') {
        return GATES.filter(g =>
            ['G1', 'G2', 'G6', 'tier_fields'].includes(g.id)
        );
    }
    return GATES;
};
