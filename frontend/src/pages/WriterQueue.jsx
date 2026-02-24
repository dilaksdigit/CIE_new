import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { dashboardApi, queueApi } from '../services/api';

const C = {
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

const tierOrder = { hero: 0, support: 1, harvest: 2, kill: 3 };

const tierInfo = {
    hero: { label: 'HERO', color: C.hero, bg: C.heroBg, border: C.heroBorder },
    support: { label: 'SUPPORT', color: C.support, bg: C.supportBg, border: C.supportBorder },
    harvest: { label: 'HARVEST', color: C.harvest, bg: C.harvestBg, border: C.harvestBorder },
    kill: { label: 'KILL', color: C.kill, bg: C.killBg, border: C.killBorder },
};

const normalizeTier = (tier) => String(tier || '').trim().toLowerCase();

const isDone = (item) => {
    if (typeof item?.done === 'boolean') return item.done;
    if (typeof item?.is_done === 'boolean') return item.is_done;
    if (typeof item?.completed === 'boolean') return item.completed;
    const status = String(item?.status || item?.validation_status || '').toLowerCase();
    return ['done', 'completed', 'approved', 'published'].includes(status);
};

const normalizeQueueItem = (item) => {
    const name = item?.name || item?.title || 'Untitled';
    const id = item?.id || item?.sku_id || item?.sku_code || '';
    const tier = normalizeTier(item?.tier);
    const done = isDone(item);

    let totalFields = Number(item?.fields_total ?? item?.total_fields ?? item?.required_fields ?? 0) || 0;
    let doneFields = Number(item?.fields_done ?? item?.done_fields ?? item?.completed_fields ?? 0) || 0;
    const missingFields = Number(item?.missing_fields_count ?? item?.missing_fields ?? 0) || 0;
    if (!totalFields && missingFields) totalFields = doneFields + missingFields;
    if (!doneFields && totalFields && missingFields) doneFields = Math.max(totalFields - missingFields, 0);

    return {
        id: String(id),
        name: String(name),
        tier,
        done,
        totalFields,
        doneFields,
        aiSuggestions: Number(item?.ai_suggestion_count ?? item?.ai_suggestions_count ?? item?.suggestions_count ?? 0) || 0,
        urgency: String(item?.urgency || item?.priority || '').toLowerCase(),
        reason: String(item?.reason || item?.why || item?.context || 'Prioritized by AI queue engine'),
    };
};

const getUrgencyBorder = (urgency) => {
    if (urgency === 'high') return C.red;
    if (urgency === 'medium') return C.amber;
    return C.border;
};

const TierTag = ({ tier }) => {
    const t = tierInfo[tier] || tierInfo.support;
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                padding: '2px 8px',
                borderRadius: 3,
                border: `1px solid ${t.border}`,
                background: t.bg,
                color: t.color,
                fontSize: '0.6rem',
                fontWeight: 700,
                letterSpacing: '0.04em',
                lineHeight: 1.1,
            }}
        >
            {t.label}
        </span>
    );
};

const FieldProgress = ({ done, total }) => {
    if (total === 0) return <span style={{ fontSize: '0.7rem', color: C.textMid }}>—</span>;
    const pct = Math.max(0, Math.min(100, Math.round((done / total) * 100)));
    const color = pct === 100 ? C.green : pct >= 50 ? C.amber : C.red;
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <div style={{ width: 50, height: 5, background: C.border, borderRadius: 999, overflow: 'hidden' }}>
                <div style={{ width: `${pct}%`, height: '100%', background: color }} />
            </div>
            <span style={{ fontSize: '0.68rem', color: C.textMid, minWidth: 28, textAlign: 'right' }}>
                {done}/{total}
            </span>
        </div>
    );
};

const StatusPill = ({ label, tone }) => (
    <span
        style={{
            display: 'inline-block',
            borderRadius: 3,
            fontSize: '0.58rem',
            padding: '2px 6px',
            fontWeight: 700,
            letterSpacing: '0.04em',
            background: tone === 'done' ? C.greenBg : C.killBg,
            color: tone === 'done' ? C.green : C.kill,
            border: `1px solid ${tone === 'done' ? C.greenBorder : C.killBorder}`,
        }}
    >
        {label}
    </span>
);

const emptyMessageForTab = (tab) => {
    if (tab === 'done') return 'Your completed products will show here.';
    if (tab === 'locked') return 'Products scheduled for removal.';
    return 'Nothing here. Nice work!';
};

const WriterQueue = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const [queueItems, setQueueItems] = useState([]);
    const [activeTab, setActiveTab] = useState('all');
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [kpis, setKpis] = useState({
        doneToday: null,
        doneWeek: null,
        heroTimePct: null,
    });
    const [hoveredItemId, setHoveredItemId] = useState(null);
    const [hoveredTabKey, setHoveredTabKey] = useState(null);

    useEffect(() => {
        let cancelled = false;
        const load = async () => {
            try {
                setLoading(true);
                setError('');
                const [queueRes, summaryRes] = await Promise.all([
                    queueApi.today(),
                    dashboardApi.getSummary(),
                ]);
                if (cancelled) return;
                const rawList = queueRes?.data?.data || [];
                const normalized = (Array.isArray(rawList) ? rawList : [])
                    .map(normalizeQueueItem)
                    .sort((a, b) => {
                        const tierCmp = (tierOrder[a.tier] ?? 99) - (tierOrder[b.tier] ?? 99);
                        if (tierCmp !== 0) return tierCmp;
                        return a.name.localeCompare(b.name);
                    });
                setQueueItems(normalized);
                const summary = summaryRes?.data?.data || {};
                const effort = summary?.effort_allocation || {};
                setKpis({
                    doneToday: summary?.done_today ?? null,
                    doneWeek: summary?.done_this_week ?? null,
                    heroTimePct: effort?.hero_pct ?? summary?.hero_time_pct ?? null,
                });
            } catch (e) {
                if (!cancelled) {
                    setQueueItems([]);
                    setError('Failed to load queue.');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        load();
        return () => {
            cancelled = true;
        };
    }, []);

    const counts = {
        todo: queueItems.filter((i) => !i.done && i.tier !== 'kill').length,
        done: queueItems.filter((i) => i.done).length,
        locked: queueItems.filter((i) => i.tier === 'kill').length,
    };

    const filtered = queueItems.filter((item) => {
        if (activeTab === 'all' && (item.done || item.tier === 'kill')) return false;
        if (activeTab === 'heroes' && !(item.tier === 'hero' && !item.done)) return false;
        if (activeTab === 'support' && !(item.tier === 'support' && !item.done)) return false;
        if (activeTab === 'harvest' && !(item.tier === 'harvest' && !item.done)) return false;
        if (activeTab === 'done' && !item.done) return false;
        if (activeTab === 'locked' && item.tier !== 'kill') return false;
        const q = query.trim().toLowerCase();
        if (!q) return true;
        return item.name.toLowerCase().includes(q) || item.id.toLowerCase().includes(q);
    });

    const tabs = [
        { key: 'all', label: `All To Do (${counts.todo})` },
        { key: 'heroes', label: 'Heroes' },
        { key: 'support', label: 'Support' },
        { key: 'harvest', label: 'Harvest' },
        { key: 'done', label: `Done (${counts.done})` },
        { key: 'locked', label: `Locked (${counts.locked})` },
    ];

    const renderStat = (label, value, color, sub) => (
        <div style={{ flex: 1, minWidth: 150, background: C.surface, border: `1px solid ${C.border}`, borderRadius: 6, padding: '12px 16px' }}>
            <div style={{ fontSize: '0.64rem', color: C.textMid, letterSpacing: '0.05em', textTransform: 'uppercase' }}>{label}</div>
            <div style={{ fontSize: '1.3rem', fontWeight: 700, color, marginTop: 4 }}>{value ?? '—'}</div>
            {sub && <div style={{ fontSize: '0.62rem', color: C.textMid, marginTop: 2 }}>{sub}</div>}
        </div>
    );

    return (
        <div>
            <h1 className="page-title">My Queue</h1>
            <div className="page-subtitle">AI-prioritized writing tasks for today</div>
            {location.state?.published && (
                <div
                    style={{
                        marginTop: 10,
                        marginBottom: 10,
                        background: C.greenBg,
                        border: `1px solid ${C.greenBorder}`,
                        color: C.green,
                        borderRadius: 6,
                        padding: '8px 10px',
                        fontSize: '0.75rem',
                        fontWeight: 600,
                    }}
                >
                    Product published successfully.
                </div>
            )}

            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 14, marginBottom: 14 }}>
                {renderStat('To Do', counts.todo, C.amber)}
                {renderStat('Done Today', kpis.doneToday, C.green)}
                {renderStat('Done This Week', kpis.doneWeek, C.accent)}
                {renderStat('Hero Time %', kpis.heroTimePct === null ? null : `${kpis.heroTimePct}%`, C.hero, 'Target: 60%')}
            </div>

            <div className="card" style={{ padding: 14 }}>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                    {tabs.map((tab) => {
                        const active = activeTab === tab.key;
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
                                onMouseEnter={() => setHoveredTabKey(tab.key)}
                                onMouseLeave={() => setHoveredTabKey(null)}
                                style={{
                                    border: `1px solid ${active ? C.accent : hoveredTabKey === tab.key ? C.accentBorder : C.border}`,
                                    background: active ? C.accent : hoveredTabKey === tab.key ? C.muted : C.surface,
                                    color: active ? C.surface : C.textMid,
                                    borderRadius: 6,
                                    padding: '6px 10px',
                                    fontSize: '0.72rem',
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                }}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by product name or SKU ID"
                        className="field-input"
                        style={{ marginLeft: 'auto', minWidth: 230, maxWidth: 340, height: 32 }}
                    />
                </div>

                {loading && <div style={{ padding: 20, textAlign: 'center', color: C.textMid }}>Loading queue...</div>}
                {!loading && error && <div style={{ padding: 20, textAlign: 'center', color: C.red }}>{error}</div>}

                {!loading && !error && filtered.length === 0 && (
                    <div style={{ padding: 22, textAlign: 'center', color: C.textMid, border: `1px dashed ${C.border}`, borderRadius: 6 }}>
                        {emptyMessageForTab(activeTab)}
                    </div>
                )}

                {!loading && !error && filtered.length > 0 && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {filtered.map((item) => {
                            const kill = item.tier === 'kill';
                            const clickable = item.tier !== 'kill';
                            return (
                                <div
                                    key={item.id}
                                    title={kill ? 'Scheduled for removal' : ''}
                                    onClick={() => clickable && navigate(`/writer/edit/${item.id}`)}
                                    onMouseEnter={() => clickable && setHoveredItemId(item.id)}
                                    onMouseLeave={() => setHoveredItemId(null)}
                                    style={{
                                        border: `1px solid ${hoveredItemId === item.id ? C.accentBorder : C.border}`,
                                        borderLeft: `4px solid ${getUrgencyBorder(item.urgency)}`,
                                        borderRadius: 6,
                                        padding: 12,
                                        background: hoveredItemId === item.id ? C.muted : C.surface,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 12,
                                        opacity: kill ? 0.5 : 1,
                                        cursor: kill ? 'default' : 'pointer',
                                    }}
                                >
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                            <TierTag tier={item.tier} />
                                            <span style={{ color: C.text, fontWeight: 600, fontSize: '0.83rem' }}>{item.name}</span>
                                            <span style={{ color: C.textMid, fontSize: '0.68rem' }}>{item.id}</span>
                                        </div>
                                        <div style={{ color: C.textMid, fontSize: '0.68rem', marginTop: 5 }}>{item.reason}</div>
                                        {item.aiSuggestions > 0 && (
                                            <div style={{ marginTop: 5, color: C.accent, fontSize: '0.68rem', fontWeight: 600 }}>
                                                💡 {item.aiSuggestions} suggestion{item.aiSuggestions > 1 ? 's' : ''} from Semrush & Analytics
                                            </div>
                                        )}
                                    </div>

                                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexShrink: 0 }}>
                                        {kill ? (
                                            <StatusPill label="LOCKED" tone="locked" />
                                        ) : item.done ? (
                                            <StatusPill label="DONE" tone="done" />
                                        ) : (
                                            <FieldProgress done={item.doneFields} total={item.totalFields} />
                                        )}
                                        <span style={{ color: C.textMid, fontSize: '0.95rem' }}>→</span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
};

export default WriterQueue;
