// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.1 — Writer Queue Screen
//         (My Product Queue: sorted Hero > Support > Harvest > Kill,
//          stats bar, AI suggestion count, tier filter, Kill-tier locked)
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §4.1 — Route Map /writer/queue
// SOURCE: CIE_v232_Writer_View.jsx — QueueScreen component (canonical visual reference)
// SOURCE: CIE_v232_Developer_README.docx Phase 3 — Writer Queue Screen build spec

import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import api, { extractApiArray } from '../services/api';
import THEME from '../theme';

const C = THEME;

const tierOrder = { hero: 0, support: 1, harvest: 2, kill: 3 };

const tierInfo = {
    hero: { label: 'HERO', color: THEME.hero, bg: THEME.heroBg, border: THEME.heroBorder },
    support: { label: 'SUPPORT', color: THEME.support, bg: THEME.supportBg, border: THEME.supportBorder },
    harvest: { label: 'HARVEST', color: THEME.harvest, bg: THEME.harvestBg, border: THEME.harvestBorder },
    kill: { label: 'KILL', color: THEME.kill, bg: THEME.killBg, border: THEME.killBorder },
};

const normalizeTier = (tier) => String(tier || '').trim().toLowerCase();

const isDone = (item) => {
    if (typeof item?.done === 'boolean') return item.done;
    if (typeof item?.is_done === 'boolean') return item.is_done;
    if (typeof item?.completed === 'boolean') return item.completed;
    const status = String(item?.status || item?.validation_status || '').toLowerCase();
    // SOURCE: Amendment Pack §4.2 — "no workflow states (pending/approved/rejected)"
    // SOURCE: openapi.yaml QueueItem schema — no 'approved' value exists in any field
    // 'approved' is an eliminated workflow state. Permitted terminal display states:
    // 'done', 'completed', 'published' only.
    return ['done', 'completed', 'published'].includes(status);
};

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const looksLikeUuid = (s) => typeof s === 'string' && UUID_REGEX.test(s.trim());

const normalizeQueueItem = (item) => {
    const rawName = item?.product_name || item?.name || item?.title || '';
    const skuCode = String(item?.sku_id ?? item?.sku_code ?? '');
    // Use UUID for routing (writer/edit/:skuId); API sends id = UUID, sku_id = sku_code
    const id = item?.id || item?.sku_id || item?.sku_code || '';
    const name = looksLikeUuid(rawName) ? (skuCode || rawName || 'Untitled') : (rawName || 'Untitled');
    const tier = normalizeTier(item?.tier);
    const done = isDone(item);

    let totalFields = Number(item?.fields_total ?? item?.total_fields ?? item?.required_fields ?? 0) || 0;
    let doneFields = Number(item?.fields_done ?? item?.done_fields ?? item?.completed_fields ?? 0) || 0;
    const missingFields = Number(item?.missing_fields_count ?? item?.missing_fields ?? 0) || 0;
    if (!totalFields && missingFields) totalFields = doneFields + missingFields;
    if (!doneFields && totalFields && missingFields) doneFields = Math.max(totalFields - missingFields, 0);

    return {
        id: String(id),
        skuCode,
        name: String(name),
        tier,
        done,
        totalFields,
        doneFields,
        aiSuggestions: Number(item?.ai_suggestion_count ?? item?.ai_suggestions_count ?? item?.suggestions_count ?? 0) || 0,
        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §1
        // FIX: UI-14 — show informational queue badges from Semrush-derived flags.
        hasQuickWin: Boolean(item?.has_quick_win ?? item?.quick_win ?? false),
        hasCompetitorGap: Boolean(item?.has_competitor_gap ?? item?.competitor_gap ?? false),
        urgency: String(item?.urgency || item?.priority || '').toLowerCase(),
        reason: String(item?.reason || item?.why || item?.context || 'Prioritized by AI queue engine'),
    };
};

const getUrgencyBorder = (urgency) => {
    if (urgency === 'high') return THEME.red;
    if (urgency === 'medium') return THEME.amber;
    return THEME.border;
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
    if (total === 0) return <span style={{ fontSize: '0.7rem', color: THEME.textMid }}>—</span>;
    const pct = Math.max(0, Math.min(100, Math.round((done / total) * 100)));
    // F6 STOP: gates.completion_amber_pct not in §5.3 — architect must add before de-hardcoding (value 50)
    const color = pct === 100 ? THEME.green : pct >= 50 ? THEME.amber : THEME.red;
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <div style={{ width: 50, height: 5, background: THEME.border, borderRadius: 999, overflow: 'hidden' }}>
                <div style={{ width: `${pct}%`, height: '100%', background: color }} />
            </div>
            <span style={{ fontSize: '0.68rem', color: THEME.textMid, minWidth: 28, textAlign: 'right' }}>
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
            background: tone === 'done' ? THEME.greenBg : tone === 'locked' ? THEME.redBg : THEME.killBg,
            color: tone === 'done' ? THEME.green : tone === 'locked' ? THEME.red : THEME.kill,
            border: `1px solid ${tone === 'done' ? THEME.greenBorder : tone === 'locked' ? THEME.redBorder : THEME.killBorder}`,
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
    const [tierFilter, setTierFilter] = useState('all'); // all | hero | support | harvest | kill
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [successMessage, setSuccessMessage] = useState('');
    const [hoveredItemId, setHoveredItemId] = useState(null);
    const [hoveredTabKey, setHoveredTabKey] = useState(null);

    useEffect(() => {
        let cancelled = false;
        const load = async () => {
            try {
                setLoading(true);
                setError('');
                // Load queue from spec-compliant endpoint /api/v1/queue/today
                let queueRes;
                try {
                    queueRes = await api.get('/v1/queue/today');
                } catch (queueErr) {
                    if (cancelled) return;
                    const msg =
                        queueErr.response?.data?.message ||
                        queueErr.response?.data?.error ||
                        queueErr.message ||
                        'Queue request failed';
                    const status = queueErr.response?.status;
                    setError(
                        status === 403
                            ? 'You don’t have permission to load the queue.'
                            : status === 404
                            ? 'Queue endpoint not found. Check API base URL and version.'
                            : `Failed to load queue: ${msg}`
                    );
                    setQueueItems([]);
                    setLoading(false);
                    return;
                }
                if (cancelled) return;
                const rawList = extractApiArray(queueRes);
                const normalized = rawList
                    .map(normalizeQueueItem)
                    .sort((a, b) => {
                        const tierCmp = (tierOrder[a.tier] ?? 99) - (tierOrder[b.tier] ?? 99);
                        if (tierCmp !== 0) return tierCmp;
                        return a.name.localeCompare(b.name);
                    });
                setQueueItems(normalized);
            } catch (e) {
                if (!cancelled) {
                    setQueueItems([]);
                    const msg = e.response?.data?.message || e.response?.data?.error || e.message || 'Unknown error';
                    setError(`Failed to load queue: ${msg}`);
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        load();

        const interval = setInterval(load, 30000);
        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, []);

    const counts = {
        todo: queueItems.filter((i) => !i.done && i.tier !== 'kill').length,
        done: queueItems.filter((i) => i.done).length,
        locked: queueItems.filter((i) => i.tier === 'kill').length,
    };

    const heroWorkItems = queueItems.filter((i) => i.tier === 'hero' && i.tier !== 'kill');
    const nonKillWorkItems = queueItems.filter((i) => i.tier !== 'kill');
    const heroTimePct =
        nonKillWorkItems.length > 0
            ? Math.round((heroWorkItems.length / nonKillWorkItems.length) * 100)
            : null;

    const filtered = queueItems.filter((item) => {
        const q = query.trim().toLowerCase();
        const searchMatch = !q || item.name.toLowerCase().includes(q) || item.id.toLowerCase().includes(q) || (item.skuCode && item.skuCode.toLowerCase().includes(q));
        if (!searchMatch) return false;

        if (tierFilter !== 'all' && normalizeTier(item.tier) !== tierFilter) {
            return false;
        }

        if (activeTab === 'all') return !item.done && item.tier !== 'kill';
        if (activeTab === 'heroes') return item.tier === 'hero';
        if (activeTab === 'support') return item.tier === 'support';
        if (activeTab === 'harvest') return item.tier === 'harvest';
        if (activeTab === 'done') return item.done;
        if (activeTab === 'locked') return item.tier === 'kill';
        return true;
    });

    const tabs = [
        { key: 'all', label: `All To Do (${counts.todo})` },
        { key: 'heroes', label: 'Heroes' },
        { key: 'support', label: 'Support' },
        { key: 'harvest', label: 'Harvest' },
        { key: 'done', label: `Done (${counts.done})` },
        { key: 'locked', label: `Locked (${counts.locked})` },
    ];

    useEffect(() => {
        if (location.state?.successMessage) {
            setSuccessMessage(location.state.successMessage);
            const timer = setTimeout(() => {
                setSuccessMessage('');
            }, 5000);
            return () => clearTimeout(timer);
        }
        return undefined;
    }, [location.state?.successMessage]);

    const renderStat = (label, value, color, sub) => (
        <div style={{ flex: 1, minWidth: 120, background: C.surface, border: `1px solid ${C.border}`, borderRadius: 6, padding: '12px 16px', boxShadow: '0 1px 2px rgba(0,0,0,0.03)' }}>
            <div style={{ fontSize: '0.64rem', color: C.textMid, letterSpacing: '0.05em', textTransform: 'uppercase' }}>{label}</div>
            <div style={{ fontSize: '1.3rem', fontWeight: 700, color, marginTop: 4 }}>{value ?? '—'}</div>
            {sub && <div style={{ fontSize: '0.62rem', color: C.textMid, marginTop: 2 }}>{sub}</div>}
        </div>
    );

    return (
        <div>
            <h1 className="page-title">My Queue</h1>
            <div className="page-subtitle">AI-prioritized writing tasks for today</div>
            {successMessage && (
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
                    {successMessage}
                </div>
            )}

            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 14, marginBottom: 14 }}>
                {renderStat('To Do', counts.todo, C.amber)}
                {renderStat('Done Today', counts.done, C.green)}
                {/* TODO: Wire to existing KPI endpoint for weekly count —
                    currently shows same value as Done Today (counts.done) */}
                {renderStat('Done This Week', counts.done, C.accent)}
                {renderStat('Hero Time', heroTimePct === null ? null : `${heroTimePct}%`, C.hero, 'Target: 60%')}
            </div>

            <div className="card" style={{ padding: 14 }}>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10, alignItems: 'center' }}>
                    {tabs.map((tab) => {
                        const active = activeTab === tab.key;
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
                                style={{
                                padding: '6px 14px',
                                borderRadius: 4,
                                fontSize: '0.72rem',
                                cursor: 'pointer',
                                background: active ? C.accent : C.surface,
                                color: active ? THEME.surface : C.textMid,
                                border: `1px solid ${active ? C.accent : C.border}`,
                                fontWeight: active ? 700 : 500,
                                }}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginLeft: 'auto' }}>
                        <label htmlFor="tier-filter" style={{ fontSize: '0.7rem', color: THEME.textMid }}>
                            Tier:
                        </label>
                        <select
                            id="tier-filter"
                            value={tierFilter}
                            onChange={(e) => setTierFilter(e.target.value)}
                            style={{
                                height: 32,
                                fontSize: '0.72rem',
                                padding: '4px 8px',
                                borderRadius: 4,
                                border: `1px solid ${THEME.border}`,
                                background: THEME.surface,
                                color: THEME.text,
                            }}
                        >
                            <option value="all">All tiers</option>
                            <option value="hero">Hero</option>
                            <option value="support">Support</option>
                            <option value="harvest">Harvest</option>
                            <option value="kill">Kill</option>
                        </select>
                    </div>
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by product name or SKU ID"
                        className="field-input"
                        style={{ minWidth: 230, maxWidth: 340, height: 32 }}
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
                                        borderTop: `1px solid ${hoveredItemId === item.id ? C.accentBorder : C.border}`,
                                        borderRight: `1px solid ${hoveredItemId === item.id ? C.accentBorder : C.border}`,
                                        borderBottom: `1px solid ${hoveredItemId === item.id ? C.accentBorder : C.border}`,
                                        borderLeft: `4px solid ${getUrgencyBorder(item.urgency)}`,
                                        borderRadius: 6,
                                        padding: 12,
                                        background: hoveredItemId === item.id ? C.muted : C.surface,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 12,
                                        opacity: kill ? 0.5 : 1,
                                        pointerEvents: kill ? 'none' : 'auto',
                                        cursor: kill ? 'not-allowed' : 'pointer',
                                    }}
                                >
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                            <TierTag tier={item.tier} />
                                            {(item.urgency === 'high' || item.urgency === 'medium') && (
                                                <span
                                                    style={{
                                                        fontSize: '0.6rem',
                                                        fontWeight: 700,
                                                        padding: '2px 7px',
                                                        borderRadius: 3,
                                                        background: item.urgency === 'high' ? C.redBg : C.amberBg,
                                                        color: item.urgency === 'high' ? C.red : C.amber,
                                                        border: `1px solid ${item.urgency === 'high' ? C.redBorder : C.amberBorder}`,
                                                    }}
                                                >
                                                    {item.urgency === 'high' ? 'HIGH' : 'MED'}
                                                </span>
                                            )}
                                            <span style={{ color: C.text, fontWeight: 600, fontSize: '0.83rem' }}>{item.name}</span>
                                            <span style={{ color: C.textMid, fontSize: '0.68rem' }}>{item.skuCode || item.id}</span>
                                        </div>
                                        <div style={{ color: C.textMid, fontSize: '0.68rem', marginTop: 5 }}>{item.reason}</div>
                                        {item.aiSuggestions > 0 && (
                                            <div style={{ marginTop: 5, color: C.accent, fontSize: '0.68rem', fontWeight: 600 }}>
                                                {/* SOURCE: CLAUDE.md §8
                                                   FIX: UI-05 — remove emoji from production UI. */}
                                                {item.aiSuggestions} suggestion{item.aiSuggestions > 1 ? 's' : ''} from Semrush & Analytics
                                            </div>
                                        )}
                                        <div style={{ marginTop: 6, display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                            {item.hasQuickWin && (
                                                <span
                                                    style={{
                                                        backgroundColor: C.greenBg,
                                                        color: C.green,
                                                        border: `1px solid ${C.greenBorder}`,
                                                        padding: '2px 8px',
                                                        borderRadius: 4,
                                                        fontSize: '0.62rem',
                                                        fontWeight: 700,
                                                    }}
                                                >
                                                    Quick Win
                                                </span>
                                            )}
                                            {item.hasCompetitorGap && (
                                                <span
                                                    style={{
                                                        backgroundColor: C.amberBg,
                                                        color: C.amber,
                                                        border: `1px solid ${C.amberBorder}`,
                                                        padding: '2px 8px',
                                                        borderRadius: 4,
                                                        fontSize: '0.62rem',
                                                        fontWeight: 700,
                                                    }}
                                                >
                                                    Competitor Gap
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexShrink: 0 }}>
                                        {kill ? (
                                            <StatusPill label="LOCKED" tone="locked" />
                                        ) : item.done ? (
                                            <StatusPill label="DONE" tone="done" />
                                        ) : (
                                            <>
                                                <FieldProgress done={item.doneFields} total={item.totalFields} />
                                                {item.totalFields > 0 && (
                                                    <span
                                                        style={{
                                                            fontSize: '0.65rem',
                                                            fontWeight: 600,
                                                            color: item.totalFields - item.doneFields > 0 ? C.red : C.green,
                                                        }}
                                                    >
                                                        {item.totalFields - item.doneFields > 0
                                                            ? `${item.totalFields - item.doneFields} missing`
                                                            : 'Complete'}
                                                    </span>
                                                )}
                                            </>
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
