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
export const GateChip = ({ id, pass, compact }) => (
    <span className={`gate-chip ${pass ? 'pass' : 'fail'} ${compact ? 'compact' : ''}`}>
        <span className="check">{pass ? '✓' : '✗'}</span>
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
export const ReadinessBar = ({ value, width = 80 }) => (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <div style={{ width, height: 6, background: 'var(--border)', borderRadius: 3, overflow: 'hidden' }}>
            <div style={{
                width: `${value}%`, height: '100%', borderRadius: 3,
                background: value >= 80 ? 'var(--green)' : value >= 60 ? 'var(--amber)' : 'var(--red)',
                transition: 'width 0.6s ease',
            }} />
        </div>
        <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)', fontFamily: 'var(--mono)', minWidth: 28 }}>{value}%</span>
    </div>
);

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
export const ChannelBadge = ({ channel }) => {
    const map = { Amazon: THEME.hero, eBay: THEME.accent, Shopify: THEME.support, Website: THEME.textMid };
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
    { id: 'G2', label: 'Title', desc: 'Intent-led title format' },
    { id: 'G3', label: 'Intents', desc: 'Primary + secondary intents' },
    { id: 'G4', label: 'Answer Block', desc: '250-300 char answer' },
    { id: 'G5', label: 'Best/Not-For', desc: 'Use case guidance' },
    { id: 'G6', label: 'Description', desc: 'Full product description' },
    { id: 'tier_fields', label: 'Tier Fields', desc: 'Tier-gated content' },
    { id: 'G7', label: 'Authority', desc: 'Expert authority block' },
    { id: 'VEC', label: 'Vector', desc: 'Description must align with product cluster intent.' },
];
