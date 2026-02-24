import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { writerEditApi, skuApi } from '../services/api';

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
  amber: "#E65100",
  amberBg: "#FFFDE7",
  amberBorder: "#FFCC80",
  blue: "#1565C0",
  blueBg: "#E3F2FD",
  blueBorder: "#90CAF9",
};

const TIER_BANNER = {
    hero: {
        text: 'HERO — Top earner. Give it your best work. Guide: ~90 min',
        bg: C.heroBg,
        color: C.hero,
    },
    support: {
        text: "SUPPORT — Solid product. Good content, don't overthink it. Guide: ~45 min",
        bg: C.supportBg,
        color: C.support,
    },
    harvest: {
        text: 'HARVEST — Basic info only. One field to fill. Guide: ~10 min',
        bg: C.harvestBg,
        color: C.harvest,
    },
    kill: {
        text: 'KILL — Being removed from sale. Do nothing.',
        bg: C.killBg,
        color: C.kill,
    },
};

const FIELD_LABELS = {
    title: 'Title',
    description: 'Description',
    answer_block: 'Answer Block',
    best_for: 'Best For',
    not_for: 'Not For',
    expert_authority: 'Expert Authority',
};

const FIELD_TYPES = {
    title: 'input',
    description: 'textarea',
    answer_block: 'textarea',
    best_for: 'textarea',
    not_for: 'textarea',
    expert_authority: 'textarea',
};

const FIELD_RANGES = {
    title: { min: null, max: 250 },
    description: { min: null, max: null }, // TODO: confirm exact range from OpenAPI schema if provided.
    answer_block: { min: 250, max: 300 },
    best_for: { min: null, max: null }, // TODO: confirm exact range from OpenAPI schema if provided.
    not_for: { min: null, max: null }, // TODO: confirm exact range from OpenAPI schema if provided.
    expert_authority: { min: null, max: null }, // TODO: confirm exact range from OpenAPI schema if provided.
};

const FIELDS_BY_TIER = {
    hero: ['title', 'description', 'answer_block', 'best_for', 'not_for', 'expert_authority'],
    support: ['title', 'description', 'answer_block', 'best_for', 'not_for'],
    harvest: ['description'],
    kill: [],
};

const normalizeTier = (tier) => String(tier || '').trim().toLowerCase();
const SUGGESTION_TYPE_COLORS = {
    keyword_opportunity: { icon: '*', color: C.accent },
    ai_visibility_issue: { icon: '*', color: C.red },
    trending_search: { icon: '*', color: C.blue },
    competitor_gap: { icon: '*', color: C.amber },
};

const SUGGESTION_SOURCE_BY_TYPE = {
    keyword_opportunity: 'Semrush & Analytics',
    trending_search: 'Semrush & Analytics',
    ai_visibility_issue: 'Analytics & CIE Audit',
    competitor_gap: 'Competitive Gap Analysis',
};

const normalizeGateKey = (value) => {
    const v = String(value || '').toLowerCase().replace(/\s+/g, '_');
    if (v.includes('vector')) return 'vector_similarity';
    if (v.startsWith('g1')) return 'g1';
    if (v.startsWith('g2')) return 'g2';
    if (v.startsWith('g3')) return 'g3';
    if (v.startsWith('g4')) return 'g4';
    if (v.startsWith('g5')) return 'g5';
    if (v.startsWith('g6')) return 'g6';
    if (v.startsWith('g7')) return 'g7';
    return v;
};

const gateKeysForField = (field) => {
    if (field === 'title') return ['g1', 'g2', 'g3'];
    if (field === 'description') return ['g6', 'vector_similarity'];
    if (field === 'answer_block') return ['g4'];
    if (field === 'best_for' || field === 'not_for') return ['g5'];
    if (field === 'expert_authority') return ['g7'];
    return [];
};

const normalizeGates = (rawGates) => {
    const map = {};
    if (Array.isArray(rawGates)) {
        rawGates.forEach((g) => {
            const key = normalizeGateKey(g?.gate ?? g?.code ?? g?.id);
            const status = g?.passed ? 'pass' : 'fail';
            map[key] = {
                status,
                reason: g?.reason || '',
                metadata: g?.metadata || {},
            };
        });
        return map;
    }
    if (rawGates && typeof rawGates === 'object') {
        Object.entries(rawGates).forEach(([k, v]) => {
            const key = normalizeGateKey(k);
            const status = v?.passed ? 'pass' : 'fail';
            map[key] = {
                status,
                reason: v?.reason || '',
                metadata: v?.metadata || {},
            };
        });
    }
    return map;
};

const pickList = (...values) => {
    for (const value of values) {
        if (Array.isArray(value) && value.length > 0) return value.join(', ');
        if (typeof value === 'string' && value.trim()) return value.trim();
    }
    return '';
};

const normalizeSuggestions = (raw) => {
    if (!Array.isArray(raw)) return [];
    return raw
        .slice(0, 8)
        .map((item, idx) => ({
            id: item?.id || `${idx}`,
            type: String(item?.type || '').toLowerCase().replace(/\s+/g, '_'),
            title: item?.title || 'Suggestion',
            body: item?.body || item?.message || '',
            source:
                item?.source_label ||
                SUGGESTION_SOURCE_BY_TYPE[String(item?.type || '').toLowerCase().replace(/\s+/g, '_')] ||
                'Semrush & Analytics',
        }));
};

const normalizeFaqSuggestions = (raw) => {
    if (!Array.isArray(raw)) return [];
    return raw.slice(0, 6).map((item, idx) => {
        if (typeof item === 'string') {
            return {
                id: `faq-${idx}`,
                question: item,
                answer: '',
            };
        }
        return {
            id: String(item.id ?? `faq-${idx}`),
            question: item.question || item.q || item.heading || 'Suggested FAQ',
            answer: item.answer || item.a || item.body || '',
        };
    });
};

const buildGateSuggestions = (gates, values) => {
    const labelMap = {
        g1: 'Title pattern',
        g2: 'Main search intent',
        g3: 'Supporting intents',
        g4: 'Answer Block length',
        g5: 'Technical details',
        g6: 'Commercial info',
        g7: 'Expert authority',
        vector_similarity: 'Category focus drift',
    };
    const items = [];
    Object.entries(gates || {}).forEach(([key, gate]) => {
        if (!gate || gate.status !== 'fail') return;
        const hint = gateHintText(key, gate, values);
        if (!hint) return;
        items.push({
            id: `gate-${key}`,
            title: labelMap[key] || 'Content check',
            body: hint,
        });
    });
    return items.slice(0, 6);
};

const gateHintText = (gateKey, gate, values) => {
    const meta = gate?.metadata || {};
    const terms = pickList(meta.terms, meta.missing_terms, meta.key_terms) || '[terms from gate data]';
    const missingElements = pickList(meta.missing_elements, meta.elements, meta.missing) || '[missing elements from gate data]';
    const categoryTerms = pickList(meta.category_terms, meta.terms) || '[category terms]';
    const min = meta.min ?? FIELD_RANGES.answer_block.min ?? '[min]';
    const max = meta.max ?? FIELD_RANGES.answer_block.max ?? '[max]';
    const currentLen = meta.current_length ?? String(values?.answer_block || '').length ?? '[X]';

    if (gateKey === 'g1') return 'Title format needs fixing. Follow: [Key Feature] + [Product Type] + [Differentiator].';
    if (gateKey === 'g2') return `Main search intent missing. Add ${terms} to match what customers search for.`;
    if (gateKey === 'g3') return `Supporting intent phrases missing. Add related use cases: ${terms}.`;
    if (gateKey === 'g4') return `Too short (${currentLen} chars). Needs ${min}-${max}. Add ${missingElements}.`;
    if (gateKey === 'g5') return 'Technical details incomplete. Add certifications, specs, or standards that prove quality.';
    if (gateKey === 'g6') return 'Missing commercial info. Add pricing context, warranty, or delivery details as needed.';
    if (gateKey === 'g7') return 'Authority section needs expert credentials. Add industry standards, testing results, or certifications.';
    if (gateKey === 'vector_similarity') return `Your content has drifted from the category focus. Rewrite to include more ${categoryTerms}.`;
    return '';
};

const fieldStateAndHint = (field, gates, values) => {
    const keys = gateKeysForField(field);
    const related = keys.map((k) => ({ key: k, gate: gates[k] })).filter((x) => x.gate);
    if (related.length === 0) return { state: 'neutral', hint: '' };

    const hasFail = related.some((r) => r.gate.status === 'fail');
    const primary = related.find((r) => r.gate.status === 'fail') || null;
    const hint = primary ? gateHintText(primary.key, primary.gate, values) : '';

    if (hasFail) return { state: 'fail', hint };
    return { state: 'pass', hint: '' };
};

const borderColorForState = (state) => {
    if (state === 'pass') return C.green;
    if (state === 'fail') return C.red;
    return C.border;
};

const hintColorForState = () => C.red;

const counterText = (field, value) => {
    const len = String(value || '').length;
    const range = FIELD_RANGES[field] || { min: null, max: null };
    if (range.min && range.max) return `${len} / ${range.min}-${range.max}`;
    if (range.max) return `${len} / max ${range.max}`;
    return `${len} / —`;
};

const WriterEdit = () => {
    const { skuId } = useParams();
    const navigate = useNavigate();

    const [sku, setSku] = useState(null);
    const [tier, setTier] = useState('');
    const [values, setValues] = useState({
        title: '',
        description: '',
        answer_block: '',
        best_for: '',
        not_for: '',
        expert_authority: '',
    });
    const [gates, setGates] = useState({});
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [validateBusy, setValidateBusy] = useState(false);
    const [publishBusy, setPublishBusy] = useState(false);
    const [publishError, setPublishError] = useState('');

    const [suggestionsOpen, setSuggestionsOpen] = useState(true);
    const [suggestions, setSuggestions] = useState([]);
    const [faqSuggestions, setFaqSuggestions] = useState([]);
    const [faqLoading, setFaqLoading] = useState(false);
    const [faqError, setFaqError] = useState('');

    const [gateSuggestionDismissedIds, setGateSuggestionDismissedIds] = useState([]);
    const [faqDismissedIds, setFaqDismissedIds] = useState([]);
    const [hoveredId, setHoveredId] = useState(null);

    const gateSuggestions = useMemo(
        () => buildGateSuggestions(gates, values).filter((s) => !gateSuggestionDismissedIds.includes(s.id)),
        [gates, values, gateSuggestionDismissedIds]
    );

    const requiredFields = useMemo(() => FIELDS_BY_TIER[tier] || [], [tier]);

    useEffect(() => {
        let cancelled = false;
        const load = async () => {
            try {
                setLoading(true);
                setLoadError('');
                const res = await writerEditApi.get(skuId);
                if (cancelled) return;
                const payload = res?.data?.data ?? res?.data ?? {};
                const item = payload?.sku ?? payload;
                const resolvedTier = normalizeTier(item?.tier);

                setSku(item);
                setTier(resolvedTier);
                setValues({
                    title: item?.title || '',
                    description: item?.description || item?.long_description || '',
                    answer_block: item?.answer_block || item?.short_description || '',
                    best_for: item?.best_for || '',
                    not_for: item?.not_for || '',
                    expert_authority: item?.expert_authority || item?.expert_authority_name || '',
                });
                const rawSuggestions = normalizeSuggestions(payload?.suggestions || payload?.ai_suggestions || []);
                try {
                    const storageKey = `cie_suggestions_dismiss_${skuId}`;
                    const dismissedRaw = window.localStorage.getItem(storageKey);
                    const dismissedIds = dismissedRaw ? JSON.parse(dismissedRaw) : [];
                    const filtered = rawSuggestions.filter((s) => !dismissedIds.includes(s.id));
                    setSuggestions(filtered);
                } catch {
                    setSuggestions(rawSuggestions);
                }
            } catch (e) {
                if (!cancelled) {
                    setLoadError('Failed to load SKU.');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        load();

        return () => {
            cancelled = true;
        };
    }, [skuId]);

    useEffect(() => {
        if (!sku || tier === 'kill') return undefined;
        let cancelled = false;
        const loadFaq = async () => {
            try {
                setFaqLoading(true);
                setFaqError('');
                const res = await skuApi.faqSuggestions(skuId);
                if (cancelled) return;
                const payload = res?.data?.data ?? res?.data ?? {};
                const blocks = payload?.faq_blocks || payload?.blocks || payload || [];

                const normalized = normalizeFaqSuggestions(blocks);
                try {
                    const storageKey = `cie_faq_dismiss_${skuId}`;
                    const dismissedRaw = window.localStorage.getItem(storageKey);
                    const dismissedIds = dismissedRaw ? JSON.parse(dismissedRaw) : [];
                    setFaqDismissedIds(dismissedIds);
                    const filtered = normalized.filter((b) => !dismissedIds.includes(b.id));
                    setFaqSuggestions(filtered);
                } catch {
                    setFaqSuggestions(normalized);
                }
            } catch (e) {
                if (!cancelled) {
                    setFaqError('Failed to load FAQ suggestions.');
                    setFaqSuggestions([]);
                }
            } finally {
                if (!cancelled) setFaqLoading(false);
            }
        };
        loadFaq();
        return () => {
            cancelled = true;
        };
    }, [sku, skuId, tier]);

    useEffect(() => {
        if (!sku || tier === 'kill') return undefined;
        let cancelled = false;
        const timer = setTimeout(async () => {
            try {
                setValidateBusy(true);
                const body = {
                    sku_id: skuId,
                    tier: tier.toUpperCase(),
                    title: values.title,
                    description: values.description,
                    answer_block: values.answer_block,
                    best_for: values.best_for,
                    not_for: values.not_for,
                    expert_authority: values.expert_authority,
                };
                const res = await writerEditApi.validate(skuId, body);
                if (cancelled) return;
                const gatePayload = res?.data?.data?.gates ?? res?.data?.gates ?? [];
                setGates(normalizeGates(gatePayload));
            } catch (e) {
                if (!cancelled) setGates({});
            } finally {
                if (!cancelled) setValidateBusy(false);
            }
        }, 350);
        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [sku, skuId, tier, values]);

    const completedCount = requiredFields.filter((f) => fieldStateAndHint(f, gates, values).state === 'pass').length;
    const totalRequired = requiredFields.length;
    const allRequiredPass = totalRequired > 0 && completedCount === totalRequired;
    const progressPct = totalRequired > 0 ? Math.round((completedCount / totalRequired) * 100) : 0;

    const handleChange = (field, nextValue) => {
        setValues((prev) => ({ ...prev, [field]: nextValue }));
    };

    const handlePublish = async () => {
        setPublishError('');
        try {
            setPublishBusy(true);
            await writerEditApi.publish(skuId);
            navigate('/writer/queue', { state: { published: true } });
        } catch (e) {
            if (e?.response?.status === 400) {
                setPublishError('Publish failed. Please resolve highlighted issues and try again.');
            } else {
                setPublishError('Publish failed. Please try again.');
            }
        } finally {
            setPublishBusy(false);
        }
    };

    const dismissSuggestion = (id) => {
        setSuggestions((prev) => {
            const next = prev.filter((x) => x.id !== id);
            try {
                const storageKey = `cie_suggestions_dismiss_${skuId}`;
                const dismissedRaw = window.localStorage.getItem(storageKey);
                const dismissedIds = dismissedRaw ? JSON.parse(dismissedRaw) : [];
                if (!dismissedIds.includes(id)) {
                    const updated = [...dismissedIds, id];
                    window.localStorage.setItem(storageKey, JSON.stringify(updated));
                }
            } catch {
                // ignore storage issues
            }
            return next;
        });
    };

    const dismissFaqSuggestion = (id) => {
        setFaqSuggestions((prev) => {
            const next = prev.filter((x) => x.id !== id);
            try {
                const storageKey = `cie_faq_dismiss_${skuId}`;
                const dismissedRaw = window.localStorage.getItem(storageKey);
                const dismissedIds = dismissedRaw ? JSON.parse(dismissedRaw) : [];
                if (!dismissedIds.includes(id)) {
                    const updated = [...dismissedIds, id];
                    window.localStorage.setItem(storageKey, JSON.stringify(updated));
                    setFaqDismissedIds(updated);
                }
            } catch {
                // ignore storage issues
            }
            return next;
        });
    };

    const dismissGateSuggestion = (id) => {
        setGateSuggestionDismissedIds((prev) => {
            const next = prev.includes(id) ? prev : [...prev, id];
            try {
                const storageKey = `cie_gate_dismiss_${skuId}`;
                window.localStorage.setItem(storageKey, JSON.stringify(next));
            } catch {
                // ignore storage issues
            }
            return next;
        });
    };

    if (loading) {
        return <div style={{ padding: 30, textAlign: 'center', color: C.textMid }}>Loading product...</div>;
    }

    if (loadError || !sku) {
        return <div style={{ padding: 30, textAlign: 'center', color: C.red }}>{loadError || 'SKU not found.'}</div>;
    }

    const banner = TIER_BANNER[tier] || TIER_BANNER.support;

    return (
        <div>
            <div className="card" style={{ marginBottom: 12, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div>
                    <h1 className="page-title" style={{ marginBottom: 2 }}>Edit Product</h1>
                    <div className="page-subtitle">{sku?.title || 'Untitled'} · {sku?.sku_code || skuId}</div>
                </div>
                <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => navigate('/writer/queue')}
                    onMouseEnter={() => setHoveredId('back')}
                    onMouseLeave={() => setHoveredId(null)}
                    style={{
                        background: hoveredId === 'back' ? C.muted : undefined,
                        borderColor: hoveredId === 'back' ? C.accentBorder : undefined,
                    }}
                >
                    Back to Queue
                </button>
            </div>

            <div
                style={{
                    marginBottom: 12,
                    background: banner.bg,
                    border: `1px solid ${tier === 'kill' ? C.killBorder : C.border}`,
                    borderRadius: 6,
                    padding: '10px 12px',
                    color: banner.color,
                    fontSize: '0.78rem',
                    fontWeight: 700,
                }}
            >
                {banner.text}
            </div>

            <div className="card" style={{ marginBottom: 12 }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
                    <div style={{ fontSize: '0.72rem', color: C.textMid }}>Progress</div>
                    <div style={{ fontSize: '0.72rem', color: C.text }}>{completedCount} of {totalRequired} fields complete</div>
                </div>
                <div style={{ height: 7, borderRadius: 999, background: C.border, overflow: 'hidden' }}>
                    <div style={{ width: `${progressPct}%`, height: '100%', background: allRequiredPass ? C.green : C.accent }} />
                </div>
            </div>

            <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                <div style={{ flex: 7, minWidth: 0 }}>
                    {tier === 'kill' ? (
                        <div className="card" style={{ border: `1px solid ${C.killBorder || C.border}` }}>
                            <div style={{ fontSize: '0.8rem', fontWeight: 700, color: C.kill, marginBottom: 6 }}>Locked</div>
                            <div style={{ color: C.textMid, fontSize: '0.78rem' }}>
                                This product is scheduled for removal and cannot be edited.
                            </div>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                            {requiredFields.map((field) => {
                                const state = fieldStateAndHint(field, gates, values);
                                const showHint = state.state === 'fail';
                                const isInput = FIELD_TYPES[field] === 'input';
                                return (
                                    <div
                                        key={field}
                                        style={{
                                            background: C.surface,
                                            border: `1px solid ${borderColorForState(state.state)}`,
                                            borderLeft: `4px solid ${borderColorForState(state.state)}`,
                                            borderRadius: 6,
                                            padding: 12,
                                        }}
                                    >
                                        <div style={{ fontSize: '0.72rem', fontWeight: 700, color: C.text, marginBottom: 6 }}>
                                            {FIELD_LABELS[field]}
                                        </div>
                                        {isInput ? (
                                            <input
                                                className="field-input"
                                                value={values[field] || ''}
                                                onChange={(e) => handleChange(field, e.target.value)}
                                                style={{ background: C.bg, borderColor: C.border }}
                                            />
                                        ) : (
                                            <textarea
                                                className="field-textarea"
                                                rows={field === 'answer_block' ? 4 : 5}
                                                value={values[field] || ''}
                                                onChange={(e) => handleChange(field, e.target.value)}
                                                style={{ background: C.bg, borderColor: C.border }}
                                            />
                                        )}
                                        <div style={{ marginTop: 5, fontSize: '0.65rem', color: C.textMid }}>
                                            {counterText(field, values[field])}
                                        </div>
                                        {showHint && state.hint && (
                                            <div style={{ marginTop: 6, fontSize: '0.68rem', color: hintColorForState(state.state) }}>
                                                💡 {state.hint}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {publishError && (
                        <div style={{ marginTop: 10, color: C.red, fontSize: '0.72rem' }}>
                            {publishError}
                        </div>
                    )}

                    {tier !== 'kill' && allRequiredPass && (
                        <div style={{ marginTop: 12 }}>
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={handlePublish}
                                disabled={publishBusy}
                                onMouseEnter={() => setHoveredId('publish')}
                                onMouseLeave={() => setHoveredId(null)}
                                style={{
                                    background: hoveredId === 'publish' ? C.accentLight : undefined,
                                    borderColor: hoveredId === 'publish' ? C.accentBorder : undefined,
                                    color: hoveredId === 'publish' ? C.text : undefined,
                                }}
                            >
                                {publishBusy ? 'Publishing...' : 'Publish'}
                            </button>
                        </div>
                    )}
                </div>

                <aside style={{ flex: 3, minWidth: 260 }}>
                    <div className="card">
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: suggestionsOpen ? 10 : 0 }}>
                            <div style={{ fontSize: '0.76rem', fontWeight: 700, color: C.text }}>AI Suggestions</div>
                            <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={() => setSuggestionsOpen((v) => !v)}
                                onMouseEnter={() => setHoveredId('toggle-suggestions')}
                                onMouseLeave={() => setHoveredId(null)}
                                style={{
                                    background: hoveredId === 'toggle-suggestions' ? C.muted : undefined,
                                    borderColor: hoveredId === 'toggle-suggestions' ? C.accentBorder : undefined,
                                }}
                            >
                                {suggestionsOpen ? 'Collapse' : 'Expand'}
                            </button>
                        </div>

                        {suggestionsOpen && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                <div
                                    style={{
                                        borderRadius: 6,
                                        border: `1px solid ${C.border}`,
                                        padding: 8,
                                        background: C.surface,
                                    }}
                                >
                                    <div style={{ fontSize: '0.7rem', fontWeight: 700, color: C.text, marginBottom: 4 }}>
                                        Semrush & Analytics
                                    </div>
                                    {suggestions.length === 0 ? (
                                        <div style={{ color: C.textMid, fontSize: '0.7rem' }}>
                                            No keyword or traffic suggestions right now.
                                        </div>
                                    ) : (
                                        suggestions.slice(0, 4).map((s) => (
                                            <div key={s.id} style={{ padding: '6px 0', borderBottom: `1px solid ${C.border}` }}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                                                    <div style={{ fontSize: '0.7rem', color: C.text, fontWeight: 600 }}>
                                                        <span
                                                            style={{
                                                                color:
                                                                    (SUGGESTION_TYPE_COLORS[s.type] || SUGGESTION_TYPE_COLORS.keyword_opportunity).color,
                                                                marginRight: 6,
                                                            }}
                                                        >
                                                            {(SUGGESTION_TYPE_COLORS[s.type] || SUGGESTION_TYPE_COLORS.keyword_opportunity).icon}
                                                        </span>
                                                        {s.title}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary btn-sm"
                                                        onClick={() => dismissSuggestion(s.id)}
                                                        onMouseEnter={() => setHoveredId(`dismiss-suggestion-${s.id}`)}
                                                        onMouseLeave={() => setHoveredId(null)}
                                                        style={{
                                                            background: hoveredId === `dismiss-suggestion-${s.id}` ? C.muted : undefined,
                                                            borderColor: hoveredId === `dismiss-suggestion-${s.id}` ? C.accentBorder : undefined,
                                                        }}
                                                    >
                                                        Dismiss
                                                    </button>
                                                </div>
                                                <div style={{ fontSize: '0.66rem', color: C.textMid, marginTop: 3 }}>{s.body}</div>
                                                <div style={{ fontSize: '0.6rem', color: C.textLight, marginTop: 2 }}>
                                                    Source: {s.source}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>

                                <div
                                    style={{
                                        borderRadius: 6,
                                        border: `1px solid ${C.border}`,
                                        padding: 8,
                                        background: C.surface,
                                    }}
                                >
                                    <div style={{ fontSize: '0.7rem', fontWeight: 700, color: C.text, marginBottom: 4 }}>
                                        FAQ blocks from Best For / Not For
                                    </div>
                                    {faqLoading ? (
                                        <div style={{ color: C.textMid, fontSize: '0.7rem' }}>Loading FAQ suggestions…</div>
                                    ) : faqError ? (
                                        <div style={{ color: C.red, fontSize: '0.7rem' }}>{faqError}</div>
                                    ) : faqSuggestions.length === 0 ? (
                                        <div style={{ color: C.textMid, fontSize: '0.7rem' }}>
                                            No FAQ suggestions yet. Add Best For / Not For content first.
                                        </div>
                                    ) : (
                                        faqSuggestions.map((f) => (
                                            <div key={f.id} style={{ padding: '6px 0', borderBottom: `1px solid ${C.border}` }}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                                                    <div style={{ fontSize: '0.7rem', color: C.text, fontWeight: 600 }}>
                                                        Q: {f.question}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary btn-sm"
                                                        onClick={() => dismissFaqSuggestion(f.id)}
                                                        onMouseEnter={() => setHoveredId(`dismiss-faq-${f.id}`)}
                                                        onMouseLeave={() => setHoveredId(null)}
                                                        style={{
                                                            background: hoveredId === `dismiss-faq-${f.id}` ? C.muted : undefined,
                                                            borderColor: hoveredId === `dismiss-faq-${f.id}` ? C.accentBorder : undefined,
                                                        }}
                                                    >
                                                        Dismiss
                                                    </button>
                                                </div>
                                                {f.answer && (
                                                    <div style={{ fontSize: '0.66rem', color: C.textMid, marginTop: 3 }}>
                                                        A: {f.answer}
                                                    </div>
                                                )}
                                                <div style={{ fontSize: '0.6rem', color: C.textLight, marginTop: 2 }}>
                                                    Source: CIE FAQ Engine
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>

                                <div
                                    style={{
                                        borderRadius: 6,
                                        border: `1px solid ${C.border}`,
                                        padding: 8,
                                        background: C.surface,
                                    }}
                                >
                                    <div style={{ fontSize: '0.7rem', fontWeight: 700, color: C.text, marginBottom: 4 }}>
                                        Validation gate hints
                                    </div>
                                    {gateSuggestions.length === 0 ? (
                                        <div style={{ color: C.textMid, fontSize: '0.7rem' }}>
                                            No failing gates. You’re aligned with CIE rules.
                                        </div>
                                    ) : (
                                        gateSuggestions.map((g) => (
                                            <div key={g.id} style={{ padding: '6px 0', borderBottom: `1px solid ${C.border}` }}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                                                    <div style={{ fontSize: '0.7rem', color: C.text, fontWeight: 600 }}>{g.title}</div>
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary btn-sm"
                                                        onClick={() => dismissGateSuggestion(g.id)}
                                                        onMouseEnter={() => setHoveredId(`dismiss-gate-${g.id}`)}
                                                        onMouseLeave={() => setHoveredId(null)}
                                                        style={{
                                                            background: hoveredId === `dismiss-gate-${g.id}` ? C.muted : undefined,
                                                            borderColor: hoveredId === `dismiss-gate-${g.id}` ? C.accentBorder : undefined,
                                                        }}
                                                    >
                                                        Dismiss
                                                    </button>
                                                </div>
                                                <div style={{ fontSize: '0.66rem', color: C.textMid, marginTop: 3 }}>{g.body}</div>
                                                <div style={{ fontSize: '0.6rem', color: C.textLight, marginTop: 2 }}>
                                                    Source: CIE Validation Gates
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>

                                <div
                                    style={{
                                        borderRadius: 6,
                                        border: `1px solid ${C.border}`,
                                        padding: 8,
                                        background: C.bg,
                                    }}
                                >
                                    <div style={{ fontSize: '0.7rem', fontWeight: 700, color: C.text, marginBottom: 4 }}>
                                        How this panel works
                                    </div>
                                    <div style={{ fontSize: '0.66rem', color: C.textMid }}>
                                        Suggestions come from Semrush & Analytics, CIE FAQ engine, and validation gates. Dismissing a
                                        suggestion hides it for this product in your browser.
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {(validateBusy || publishBusy) && (
                        <div style={{ marginTop: 8, fontSize: '0.68rem', color: C.textMid }}>
                            {validateBusy ? 'Checking content...' : 'Publishing...'}
                        </div>
                    )}
                </aside>
            </div>
        </div>
    );
};

export default WriterEdit;
