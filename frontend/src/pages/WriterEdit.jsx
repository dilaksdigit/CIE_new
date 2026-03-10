// SOURCE: CIE_v232_Hardening_Addendum.pdf §6.2 / §6.3
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §§4.1, 4.2, 5; Trap 2 | CIE_v232_UI_Restructure_Instructions.docx §2.1 | CIE_v232_Semrush_CSV_Import_Spec.docx §§1, 3.2, 3.3 | CIE_v232_Writer_View.jsx
import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api, { writerEditApi } from '../services/api';
import THEME from '../theme';
import TierLockBanner from '../components/sku/TierLockBanner';
import { HiddenFieldSlot } from '../components/sku/HiddenFieldSlot';
import { TIER_FIELD_MAP, TIER_BANNER_COPY, TIER_TOOLTIPS, KILL_FIELD_TOOLTIP } from '../lib/tierFieldMap';

const C = THEME;

// SOURCE: CIE_v232_Hardening_Addendum.pdf §6.1, §6.3 — banner background hex values (not theme tokens)
const TIER_BANNER_BG = {
    hero:    '#E8F5E9',
    support: '#FFF8E1',
    harvest: '#FFF3E0',
    kill:    '#FFEBEE',
};

const FIELD_LABELS = {
    title: 'Title',
    description: 'Description',
    specification: 'Basic Specification',
    answer_block: 'Answer Block',
    best_for: 'Best For',
    not_for: 'Not For',
    expert_authority: 'Expert Authority',
    secondary_intents_3_9: 'Secondary Intents (3–9)',
    faq_tab: 'FAQ',
    wikidata_uri: 'Wikidata URI',
};

const FIELD_TYPES = {
    title: 'input',
    description: 'textarea',
    specification: 'textarea',
    answer_block: 'textarea',
    best_for: 'textarea',
    not_for: 'textarea',
    expert_authority: 'textarea',
};

const FIELD_RANGES = {
    title: { min: 1, max: 250 },
    description: { min: 50, max: null },
    specification: { min: 50, max: null },
    answer_block: { min: 250, max: 300 },
    best_for: { min: 1, max: null },
    not_for: { min: 1, max: null },
    expert_authority: { min: 1, max: null },
};

const normalizeTier = (tier) => String(tier || '').trim().toLowerCase();
const SUGGESTION_CARD_TYPE_META = {
    // SOURCE: CIE_v232_Writer_View.jsx — SuggestionCard icon/label map
    keyword:    { icon: '🔍', label: 'Keyword Opportunity', iconColor: '#5B7A3A' },   // olive green
    citation:   { icon: '🤖', label: 'AI Visibility Issue', iconColor: '#C62828' },   // red
    trend:      { icon: '📈', label: 'Trending Search', iconColor: '#1565C0' },       // blue
    competitor: { icon: '⚔️', label: 'Competitor Gap', iconColor: '#E65100' },       // amber
};

const PRIORITY_META = {
    // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.1 priority mapping
    high:   { badgeText: 'HIGH', color: THEME.red,   bg: THEME.redBg },
    medium: { badgeText: 'MED',  color: THEME.amber, bg: THEME.amberBg },
    low:    { badgeText: 'LOW',  color: THEME.amber, bg: THEME.amberBg },
};

const PRIORITY_ORDER = ['high', 'medium', 'low'];

const ALLOWED_SUGGESTION_TYPES = ['keyword', 'citation', 'trend', 'competitor'];

const resolveSuggestionType = (item) => {
    const rawType = String(item?.type || '').toLowerCase().replace(/\s+/g, '_');
    if (ALLOWED_SUGGESTION_TYPES.includes(rawType)) return rawType;

    const source = String(item?.source_label || item?.source || '').toLowerCase();
    const title = String(item?.title || '').toLowerCase();
    const text = String(item?.explanation || item?.body || item?.message || '').toLowerCase();

    if (source.includes('semrush')) return 'keyword';
    if (source.includes('ai audit') || title.includes('ai visibility') || text.includes('ai visibility')) return 'citation';
    if (source.includes('analytics') || title.includes('trend') || text.includes('trend')) return 'trend';
    if (source.includes('competitor') || title.includes('competitor') || text.includes('competitor')) return 'competitor';

    // Fallback: unknown/legacy types are dropped by normalisation
    // SOURCE: README_First_CIE_v232_Developer_README.docx §5; CIE_v232_UI_Restructure_Instructions.docx §2.1
    return null;
};

const SUGGESTION_SOURCE_BY_TYPE = {
    // SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §4.1; README_First_CIE_v232_Developer_README.docx §5
    keyword:    'Semrush',
    citation:   'AI Audit',
    trend:      'Google Analytics',
    competitor: 'Semrush + AI Audit',
};

// FIX 5 — Canonical source by type only; never use item.source_label (Amendment Pack v2 §4.1)
const CANONICAL_SOURCE = {
    keyword:    'Semrush',
    citation:   'AI Audit',
    trend:      'Google Analytics',
    competitor: 'Semrush + AI Audit',
};

/** FIX 1 — Structured card content per type. Extract specific API fields; never pass raw explanation/body. */
const transformCardContent = (item, type) => {
    if (type === 'keyword') {
        const keyword = item?.keyword || item?.title || '';
        const searchVolume = item?.search_volume;
        const volText = searchVolume != null && searchVolume !== '' ? String(searchVolume) : 'Search volume unavailable';
        const trendRaw = typeof item?.trend === 'string' ? item.trend : (Array.isArray(item?.trend) ? item.trend.join(',') : '');
        const parts = trendRaw ? String(trendRaw).split(',').map((s) => s.trim()).filter(Boolean) : [];
        const first = parts.length ? Number(parts[0]) : null;
        const last = parts.length ? Number(parts[parts.length - 1]) : null;
        let trendLabel = '→ Stable';
        if (first != null && last != null && !Number.isNaN(first) && !Number.isNaN(last)) {
            if (last > first) trendLabel = '↑ Rising';
            else if (last < first) trendLabel = '↓ Falling';
        }
        const instruction = item?.instruction || (keyword ? `Add '${keyword}' to your description. Monthly searches: ${volText}.` : `Monthly searches: ${volText}.`);
        return {
            title: item?.title || 'Keyword Opportunity',
            explanation: `Search volume: ${volText}. Trend: ${trendLabel}. ${instruction}`,
        };
    }
    if (type === 'citation') {
        const engine = item?.engine?.trim() || 'Engine not specified';
        const dropReason = item?.drop_reason?.trim() || 'Reason not provided';
        const fixInstruction = item?.fix_instruction?.trim() || '';
        const title = item?.title || 'AI Visibility Issue';
        const explanation = [engine, dropReason, fixInstruction].filter(Boolean).join(' — ') || 'No details available.';
        return { title, explanation };
    }
    if (type === 'trend') {
        const pct = item?.traffic_change_pct;
        const trafficText = pct != null && pct !== '' ? (Number(pct) >= 0 ? `+${Number(pct)}% traffic increase` : `${Number(pct)}% traffic drop`) : 'Traffic data unavailable';
        const action = item?.action?.trim() || '';
        const title = item?.title || 'Trending Search';
        const explanation = action ? `${trafficText}. ${action}` : trafficText;
        return { title, explanation };
    }
    if (type === 'competitor') {
        const competitorAction = item?.competitor_action?.trim();
        const ourGap = item?.our_gap?.trim();
        const gapText = (competitorAction || ourGap) ? [competitorAction, ourGap].filter(Boolean).join('. We are missing: ') : 'Gap data unavailable';
        const title = item?.title || 'Competitor Gap';
        return { title, explanation: gapText };
    }
    return {
        title: item?.title || item?.suggestion_title || '',
        explanation: item?.explanation || item?.body || item?.message || '',
    };
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
    if (field === 'description' || field === 'specification') return ['g6', 'vector_similarity'];
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
            const rawStatus = (g && (g.status || g.state)) || (g?.passed === true ? 'pass' : g?.passed === false ? 'fail' : null);
            const status =
                rawStatus === 'pass'
                    ? 'pass'
                    : rawStatus === 'warning' || rawStatus === 'pending'
                    ? 'warning'
                    : 'fail';
            map[key] = {
                status,
                reason: g?.reason || '',
                metadata: g?.metadata || {},
                user_message: g?.user_message || g?.metadata?.user_message || '',
            };
        });
        return map;
    }
    if (rawGates && typeof rawGates === 'object') {
        Object.entries(rawGates).forEach(([k, v]) => {
            const key = normalizeGateKey(k);
            const rawStatus = (v && (v.status || v.state)) || (v?.passed === true ? 'pass' : v?.passed === false ? 'fail' : null);
            const status =
                rawStatus === 'pass'
                    ? 'pass'
                    : rawStatus === 'warning' || rawStatus === 'pending'
                    ? 'warning'
                    : 'fail';
            map[key] = {
                status,
                reason: v?.reason || '',
                metadata: v?.metadata || {},
                user_message: v?.user_message || v?.metadata?.user_message || '',
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
    // SOURCE: README_First_CIE_v232_Developer_README.docx §5 — AI Suggestions Panel normalisation
    return raw
        .map((item, idx) => {
            const type = resolveSuggestionType(item);
            if (type === null) return null;
            const transformed = transformCardContent(item, type);
            const rawPriority = String(item?.priority || '').toLowerCase();
            const priority = PRIORITY_ORDER.includes(rawPriority) ? rawPriority : 'medium';
            return {
                id: item?.id || `${idx}`,
                type,
                title: transformed.title,
                explanation: transformed.explanation,
                priority,
                source: CANONICAL_SOURCE[type],
                dateRange: item?.date_range || item?.dateRange || item?.window || '',
            };
        })
        .filter((s) => s !== null && s.type !== null)
        .filter(Boolean)
        .slice(0, 8);
};

const gateHintText = (gateKey, gate, values) => {
    const meta = gate?.metadata || {};
    const missingTerms = gate?.missing_terms ?? meta.missing_terms ?? meta.terms ?? meta.key_terms;
    const termsStr = Array.isArray(missingTerms) ? missingTerms.join(', ') : (typeof missingTerms === 'string' ? missingTerms : '');
    const missingElements = gate?.missing_elements ?? meta.missing_elements ?? meta.elements ?? meta.missing;
    const elementsArr = Array.isArray(missingElements) ? missingElements : (typeof missingElements === 'string' ? [missingElements] : []);
    const categoryTerms = gate?.category_terms ?? meta.category_terms ?? meta.terms;
    const categoryStr = Array.isArray(categoryTerms) ? categoryTerms.join(', ') : (typeof categoryTerms === 'string' ? categoryTerms : '');
    const minChars = gate?.min_chars ?? meta.min_chars ?? meta.min ?? FIELD_RANGES.answer_block?.min;
    const maxChars = gate?.max_chars ?? meta.max_chars ?? meta.max ?? FIELD_RANGES.answer_block?.max;
    const currentChars = gate?.current_chars ?? meta.current_chars ?? meta.current_length ?? (values?.answer_block != null ? String(values.answer_block).length : null);

    if (gateKey === 'g1') {
        if (elementsArr.length >= 3) {
            const [keyFeature, productType, differentiator] = elementsArr;
            return `Title format needs fixing. Follow: ${keyFeature} + ${productType} + ${differentiator}.`;
        }
        return 'Title format needs fixing. Check your title includes a key feature, product type, and a differentiator.';
    }
    if (gateKey === 'g2') {
        if (termsStr) return `Main search intent missing. Add ${termsStr} to match what customers search for.`;
        return 'Main search intent missing. Review your primary keywords.';
    }
    if (gateKey === 'g3') {
        if (termsStr) return `Supporting intent phrases missing. Add related use cases: ${termsStr}.`;
        return 'Supporting intent phrases missing. Add related use cases.';
    }
    if (gateKey === 'g4') {
        const parts = [];
        if (currentChars != null && currentChars !== '') parts.push(`Currently ${currentChars} chars`);
        if (minChars != null && maxChars != null) parts.push(`needs ${minChars}-${maxChars}`);
        else if (minChars != null) parts.push(`needs at least ${minChars}`);
        else if (maxChars != null) parts.push(`needs up to ${maxChars}`);
        if (elementsArr.length) parts.push(`Add: ${elementsArr.join(', ')}`);
        if (parts.length) return `Too short. ${parts.join('. ')}.`;
        return 'Answer block is too short. Add more detail to meet the required length.';
    }
    if (gateKey === 'g5') return 'Technical details incomplete. Add certifications, specs, or standards that prove quality.';
    if (gateKey === 'g6') return 'Missing commercial info. Add pricing context, warranty, or delivery details as needed.';
    if (gateKey === 'g7') return 'Authority section needs expert credentials. Add industry standards, testing results, or certifications.';
    if (gateKey === 'vector_similarity') {
        if (categoryStr) return `Your content has drifted from the category focus. Rewrite to include more ${categoryStr}.`;
        return 'Your content has drifted from the category focus. Review the category keywords.';
    }
    return '';
};

const fieldStateAndHint = (field, gates, values) => {
    const keys = gateKeysForField(field);
    const related = keys.map((k) => ({ key: k, gate: gates[k] })).filter((x) => x.gate);
    if (related.length === 0) return { state: 'neutral', hint: '' };

    const hasFail = related.some((r) => r.gate.status === 'fail');
    const hasWarning = related.some((r) => r.gate.status === 'warning');

    if (hasFail) {
        const primaryFail = related.find((r) => r.gate.status === 'fail') || null;
        // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §6
        // Prefer API user_message (plain English, no gate codes). Fall back
        // to gateHintText() only when the API response omits user_message.
        const hint = primaryFail
            ? (primaryFail.gate.user_message || gateHintText(primaryFail.key, primaryFail.gate, values))
            : '';
        return { state: 'fail', hint };
    }

    if (hasWarning) {
        const primaryWarning = related.find((r) => r.gate.status === 'warning') || null;
        const hint = primaryWarning
            ? (primaryWarning.gate.user_message || gateHintText(primaryWarning.key, primaryWarning.gate, values))
            : '';
        return { state: 'warning', hint };
    }

    return { state: 'pass', hint: '' };
};

/** Client-side completion: field has enough content to count as "complete" for progress. */
const isFieldComplete = (field, values) => {
    const v = String((values && values[field]) || '').trim();
    const range = FIELD_RANGES[field];
    if (!range) return false;
    const len = v.length;
    if (range.min != null && len < range.min) return false;
    if (range.max != null && len > range.max) return false;
    return true;
};

const borderColorForState = (state) => {
    if (state === 'pass') return THEME.green;
    if (state === 'warning') return THEME.amber;
    if (state === 'fail') return THEME.red;
    return THEME.border;
};

const hintColorForState = (state) => (state === 'warning' ? THEME.amber : THEME.red);

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
        specification: '',
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
    const [hoveredId, setHoveredId] = useState(null);
    const [hasSemrushData, setHasSemrushData] = useState(false);

    const isReadonly = TIER_FIELD_MAP[tier]?.readonly === true;
    const requiredFields = useMemo(
        () => (TIER_FIELD_MAP[tier]?.enabled || []).filter((f) => FIELD_LABELS[f]),
        [tier]
    );
    const gatedRequiredFields = useMemo(
        () => requiredFields.filter((f) => gateKeysForField(f).length > 0),
        [requiredFields]
    );

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
                    specification: item?.specification || item?.description || item?.long_description || '',
                    answer_block: item?.answer_block || item?.short_description || '',
                    best_for: item?.best_for || '',
                    not_for: item?.not_for || '',
                    expert_authority: item?.expert_authority || item?.expert_authority_name || '',
                });

                // SOURCE: README_First_CIE_v232_Developer_README.docx §5
                // SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 2 RIGHT PANEL
                // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §1 §3.2 §3.3
                // SOURCE: openapi.yaml (AI Audit paths only) — Semrush/GA read endpoints not exposed to CMS layer (Trap 2)
                const category =
                    item?.category ||
                    item?.primaryCluster?.category ||
                    null;

                // Start from any backend-provided suggestions (e.g. AI audit)
                const baseRawSuggestions =
                    payload?.suggestions ||
                    payload?.ai_suggestions ||
                    item?.ai_suggestions ||
                    item?.suggestions ||
                    [];

                const suggestionItems = Array.isArray(baseRawSuggestions)
                    ? [...baseRawSuggestions]
                    : [];

                // Semrush keyword / competitor gap cards: derive from semrush_imports already attached to SKU payload by CMS.
                // FRONTEND-ONLY FIX (Trap 2): do NOT call non-existent /v1/sku/{skuId}/semrush engine endpoints.
                const semrushList = Array.isArray(payload?.semrush_imports) ? payload.semrush_imports : [];
                const semrushHasData = semrushList.length > 0;

                if (semrushHasData) {
                    // Card Type 1 — Keyword Opportunity: sort by search_volume DESC
                    const keywordRows = [...semrushList].filter((row) => row && row.keyword).sort((a, b) => {
                        const av = Number(a.search_volume || 0);
                        const bv = Number(b.search_volume || 0);
                        return bv - av;
                    });
                    keywordRows.forEach((row, idx) => {
                        suggestionItems.push({
                            id: `semrush-keyword-${skuId}-${idx}`,
                            type: 'keyword',
                            keyword: row.keyword,
                            search_volume: row.search_volume,
                            trend: row.trend,
                            instruction: row.instruction,
                            title: row.title || (row.keyword ? `Keyword: ${row.keyword}` : 'Keyword Opportunity'),
                            date_range: row.date_range || row.window || '',
                            priority: row.priority || 'medium',
                        });
                    });

                    // Card Type 4 — Competitor Gap: prev_position > position, sorted by improvement DESC
                    const competitorRows = [...semrushList].filter((row) => {
                        if (!row) return false;
                        const pos = Number(row.position);
                        const prev = Number(row.prev_position);
                        return !Number.isNaN(pos) && !Number.isNaN(prev) && prev > pos;
                    }).sort((a, b) => {
                        const aDelta = Number(a.prev_position || 0) - Number(a.position || 0);
                        const bDelta = Number(b.prev_position || 0) - Number(b.position || 0);
                        return bDelta - aDelta;
                    });

                    competitorRows.forEach((row, idx) => {
                        suggestionItems.push({
                            id: `semrush-competitor-${skuId}-${idx}`,
                            type: 'competitor',
                            competitor_action: row.competitor_action,
                            our_gap: row.our_gap,
                            title: row.title || 'Competitor Gap',
                            date_range: row.date_range || row.window || '',
                            priority: row.priority || 'medium',
                        });
                    });
                }

                setHasSemrushData(semrushHasData);

                // AI Audit → citation cards (existing endpoint)
                if (category) {
                    try {
                        const aiRes = await api.get(`/v1/audit/results/${encodeURIComponent(category)}`);
                        if (!cancelled && aiRes) {
                            const aiData = aiRes.data?.data ?? aiRes.data ?? {};
                            const aggregateRate =
                                typeof aiData.aggregate_citation_rate === 'number'
                                    ? aiData.aggregate_citation_rate
                                    : null;
                            const passFail = aiData.pass_fail || null;
                            const runDate = aiData.run_date || '';
                            if (aggregateRate !== null) {
                                const pct = (aggregateRate * 100).toFixed(1);
                                const fixInstruction = passFail === 'fail'
                                    ? `AI citation rate is only ${pct}%. Strengthen Answer Block and Expert Authority to improve AI visibility.`
                                    : `AI citation rate is ${pct}%. Maintain strong coverage in Answer Block and Expert Authority to keep visibility high.`;
                                suggestionItems.push({
                                    id: `ai-audit-${skuId}`,
                                    type: 'citation',
                                    engine: 'AI Audit',
                                    drop_reason: passFail === 'fail' ? 'Low citation rate' : 'N/A',
                                    fix_instruction: fixInstruction,
                                    title: `AI visibility for ${aiData.category || 'this category'} is ${pct}%`,
                                    date_range: runDate,
                                    priority: passFail === 'fail' ? 'high' : 'medium',
                                });
                            }
                        }
                    } catch {
                        // Fail-soft: if AI Audit is unavailable, fall back to SKU suggestions only
                    }
                }

                const normalizedSuggestions = normalizeSuggestions(suggestionItems);
                setSuggestions(normalizedSuggestions);
            } catch (e) {
                if (!cancelled) {
                    setLoadError('Failed to load SKU.');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        load();

        const interval = setInterval(async () => {
            try {
                const fresh = await writerEditApi.get(skuId);
                if (cancelled) return;
                const payload = fresh?.data?.data ?? fresh?.data ?? {};
                const item = payload?.sku ?? payload;
                const freshTier = normalizeTier(item?.tier);
                if (freshTier && freshTier !== tier) {
                    setTier(freshTier);
                }
            } catch {
                // Fail-soft: background poll errors must not break the UI
            }
        }, 30000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [skuId]);

    useEffect(() => {
        if (!sku || isReadonly) return undefined;
        let cancelled = false;
        const timer = setTimeout(async () => {
            try {
                setValidateBusy(true);
                const body = {
                    sku_id: skuId,
                    tier: tier.toUpperCase(),
                    title: values.title,
                    description: values.description,
                    specification: values.specification,
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

    const progressCompletedCount = requiredFields.filter(
        (f) => isFieldComplete(f, values) || fieldStateAndHint(f, gates, values).state === 'pass'
    ).length;
    const progressTotalRequired = requiredFields.length;

    const gatePassedCount = gatedRequiredFields.filter((f) => fieldStateAndHint(f, gates, values).state === 'pass').length;
    const totalRequired = gatedRequiredFields.length;
    const requiredGateKeys = gatedRequiredFields.flatMap((f) => gateKeysForField(f));
    const relevantGates = requiredGateKeys.map((k) => gates[k]).filter(Boolean);
    const hasGateData = relevantGates.length > 0;
    const allRequiredPass = hasGateData && relevantGates.every((g) => g.status === 'pass');
    const progressPct =
        progressTotalRequired > 0 ? Math.round((progressCompletedCount / progressTotalRequired) * 100) : 0;

    const handleChange = (field, nextValue) => {
        setValues((prev) => ({ ...prev, [field]: nextValue }));
    };

    const handleSubmit = async () => {
        // SOURCE: Amendment Pack §4.2 — Submit calls publish endpoint directly
        setPublishError('');
        setPublishBusy(true);
        try {
            const res = await api.post(`/v1/sku/${encodeURIComponent(skuId)}/publish`);
            if (res.status >= 200 && res.status < 300) {
                // After successful publish: redirect to queue with success message
                navigate('/writer/queue', { state: { successMessage: 'Product published successfully.' } });
                return;
            }
        } catch (e) {
            const status = e?.response?.status;
            if (status === 400) {
                // Gate validation failed — keep per-field hints, surface generic message only
                setPublishError('Some content checks failed. Please review the fields and try again.');
            } else if (status === 403) {
                setPublishError("You don't have permission to publish this product.");
            } else {
                setPublishError('Something went wrong. Please try again.');
            }
            return;
        } finally {
            setPublishBusy(false);
        }
    };

    const dismissSuggestion = async (id) => {
        // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.1; README_First_CIE_v232_Developer_README.docx §5; openapi.yaml /sku/{sku_id}/suggestions/{suggestion_id}/status
        setSuggestions((prev) =>
            prev.map((s) => (s.id === id ? { ...s, dismissBusy: true, dismissError: '' } : s))
        );
        try {
            const res = await fetch(`/api/v1/sku/${encodeURIComponent(skuId)}/suggestions/${encodeURIComponent(id)}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    suggestion_id: id,
                    status: 'seen',
                }),
            });
            if (!res.ok) {
                throw new Error('Failed to update suggestion status');
            }
            setSuggestions((prev) => prev.filter((s) => s.id !== id));
        } catch (e) {
            setSuggestions((prev) =>
                prev.map((s) =>
                    s.id === id
                        ? {
                              ...s,
                              dismissBusy: false,
                              dismissError: 'Could not dismiss this suggestion. Please try again.',
                          }
                        : s
                )
            );
        }
    };

    if (loading) {
        return <div style={{ padding: 30, textAlign: 'center', color: THEME.textMid }}>Loading product...</div>;
    }

    if (loadError || !sku) {
        return <div style={{ padding: 30, textAlign: 'center', color: THEME.red }}>{loadError || 'SKU not found.'}</div>;
    }

    const bannerText = TIER_BANNER_COPY[tier] || TIER_BANNER_COPY.support;
    const bannerBg = TIER_BANNER_BG[tier] || TIER_BANNER_BG.support;

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

            <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                    {tier !== 'kill' && (
                        <div
                            style={{
                                marginBottom: 12,
                                background: bannerBg,
                                border: `1px solid ${C.border}`,
                                borderRadius: 6,
                                padding: '10px 12px',
                                color: THEME[tier] || THEME.support,
                                fontSize: '0.78rem',
                                fontWeight: 700,
                            }}
                        >
                            {bannerText}
                        </div>
                    )}

                    <div className="card" style={{ marginBottom: 12 }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
                            <div style={{ fontSize: '0.72rem', color: THEME.textMid }}>Progress</div>
                            <div style={{ fontSize: '0.72rem', color: THEME.text }}>
                                {progressCompletedCount} of {progressTotalRequired} fields complete
                            </div>
                        </div>
                        <div style={{ height: 7, borderRadius: 999, background: THEME.border, overflow: 'hidden' }}>
                            <div style={{ width: `${progressPct}%`, height: '100%', background: allRequiredPass ? THEME.green : THEME.accent }} />
                        </div>
                    </div>

                    {isReadonly ? (
                        <>
                            <TierLockBanner text={TIER_BANNER_COPY.kill} />
                            {/* §6.3 render_hidden_field — Kill tier: every field gets KILL_FIELD_TOOLTIP */}
                            {Object.keys(FIELD_LABELS).map((field) => {
                                const killTooltips = { [field]: { kill: KILL_FIELD_TOOLTIP } };
                                return (
                                    <HiddenFieldSlot key={field} fieldName={field} tier="kill" tooltips={killTooltips} />
                                );
                            })}
                        </>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                            {requiredFields.map((field) => {
                                const state = fieldStateAndHint(field, gates, values);
                                // SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 6 (Rule C)
                                // FAIL: show hint text. WARNING/PENDING: amber border only, no blocking text.
                                const showHint = state.state === 'fail';
                                const isInput = FIELD_TYPES[field] === 'input';
                                return (
                                    <div
                                        key={field}
                                        style={{
                                            background: THEME.surface,
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
                                        <div style={{ marginTop: 5, fontSize: '0.65rem', color: THEME.textMid }}>
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

                            {/* §6.3 render_hidden_field — placeholder divs for hidden fields */}
                            {(TIER_FIELD_MAP[tier]?.hidden || []).map((field) => (
                                <HiddenFieldSlot key={`hidden-${field}`} fieldName={field} tier={tier} tooltips={TIER_TOOLTIPS} />
                            ))}
                        </div>
                    )}

                    {!isReadonly && allRequiredPass && (
                        <div style={{ marginTop: 12 }}>
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={handleSubmit}
                                disabled={publishBusy}
                                onMouseEnter={() => setHoveredId('publish')}
                                onMouseLeave={() => setHoveredId(null)}
                                style={{
                                    background: hoveredId === 'publish' ? THEME.accentLight : undefined,
                                    borderColor: hoveredId === 'publish' ? THEME.accentBorder : undefined,
                                    color: hoveredId === 'publish' ? THEME.text : undefined,
                                }}
                            >
                                {publishBusy ? 'Publishing…' : 'Submit ✓'}
                            </button>
                            {publishError && (
                                <div
                                    style={{
                                        marginTop: 8,
                                        color: '#C62828',
                                        fontSize: '0.8rem',
                                        padding: '8px 12px',
                                        background: '#FFEBEE',
                                        borderRadius: 4,
                                        border: '1px solid #EF9A9A',
                                    }}
                                >
                                    {publishError}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <aside style={{ flex: '0 0 30%', width: '30%' }}>
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
                                {suggestions.length === 0 ? (
                                    <div
                                        style={{
                                            borderRadius: 6,
                                            border: `1px solid ${C.border}`,
                                            padding: 8,
                                            background: C.surface,
                                            fontSize: '0.7rem',
                                            color: C.textMid,
                                        }}
                                    >
                                        No suggestions right now. This product looks good.
                                    </div>
                                ) : (
                                    <>
                                        {!hasSemrushData && (
                                            <div
                                                style={{
                                                    borderRadius: 6,
                                                    border: `1px solid ${C.border}`,
                                                    padding: 8,
                                                    background: C.surface,
                                                    fontSize: '0.7rem',
                                                    color: C.textMid,
                                                }}
                                            >
                                                No keyword data yet. Ask your admin to upload the latest Semrush CSV export under Admin → Semrush Import.
                                            </div>
                                        )}
                                        {suggestions
                                            .filter((s) => hasSemrushData || (s.type !== 'keyword' && s.type !== 'competitor'))
                                            .map((s) => {
                                                const typeMeta = SUGGESTION_CARD_TYPE_META[s.type] || SUGGESTION_CARD_TYPE_META.keyword;
                                                const priorityMeta = PRIORITY_META[s.priority] || PRIORITY_META.medium;
                                                const prioColor = priorityMeta.color;
                                                return (
                                            <div
                                                key={s.id}
                                                style={{
                                                    borderRadius: 6,
                                                    border: `1px solid ${C.border}`,
                                                    borderLeft: `4px solid ${prioColor}`,
                                                    padding: 10,
                                                    background: C.surface,
                                                    fontSize: '0.7rem',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'space-between',
                                                        gap: 8,
                                                        marginBottom: 4,
                                                    }}
                                                >
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                                        <span style={{ fontSize: '0.9rem', color: typeMeta.iconColor }}>{typeMeta.icon}</span>
                                                        <span style={{ fontWeight: 700, color: C.text }}>{typeMeta.label}</span>
                                                    </div>
                                                    <div
                                                        style={{
                                                            display: 'inline-flex',
                                                            alignItems: 'center',
                                                            padding: '2px 6px',
                                                            borderRadius: 999,
                                                            border: `1px solid ${priorityMeta.color}`,
                                                            background: priorityMeta.bg,
                                                            color: priorityMeta.color,
                                                            fontSize: '0.6rem',
                                                            fontWeight: 700,
                                                            textTransform: 'uppercase',
                                                        }}
                                                    >
                                                        {priorityMeta.badgeText}
                                                    </div>
                                                </div>
                                                <div style={{ color: C.text, marginBottom: 6 }}>
                                                    <div style={{ fontSize: '0.82rem', fontWeight: 700, color: C.text, marginBottom: 6, lineHeight: 1.3 }}>
                                                        {s.title}
                                                    </div>
                                                    {s.explanation
                                                        ? <p style={{ fontSize: '0.75rem', color: C.text, margin: '6px 0' }}>{s.explanation}</p>
                                                        : <p style={{ fontSize: '0.75rem', color: C.textLight, margin: '6px 0', fontStyle: 'italic' }}>No explanation available for this suggestion.</p>
                                                    }
                                                </div>
                                                {s.dismissError && (
                                                    <div style={{ color: C.red, fontSize: '0.65rem', marginBottom: 4 }}>
                                                        {s.dismissError}
                                                    </div>
                                                )}
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 8 }}>
                                                    <span style={{ fontSize: '0.62rem', color: C.textLight }}>Source:</span>
                                                    <span style={{
                                                        fontSize: '0.62rem', fontWeight: 700,
                                                        padding: '1px 6px', borderRadius: 3,
                                                        background: C.accentLight, color: C.accent,
                                                        border: `1px solid ${C.accentBorder}`
                                                    }}>
                                                        {s.source}
                                                    </span>
                                                    <span style={{ fontSize: '0.62rem', color: C.textLight }}>
                                                        ({s.dateRange || 'Date unavailable'})
                                                    </span>
                                                </div>
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'flex-end',
                                                        marginTop: 6,
                                                    }}
                                                >
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary btn-sm"
                                                        onClick={() => dismissSuggestion(s.id)}
                                                        disabled={s.dismissBusy}
                                                        onMouseEnter={() => setHoveredId(`dismiss-suggestion-${s.id}`)}
                                                        onMouseLeave={() => setHoveredId(null)}
                                                        style={{
                                                            marginLeft: 6,
                                                            background: hoveredId === `dismiss-suggestion-${s.id}` ? C.muted : undefined,
                                                            borderColor:
                                                                hoveredId === `dismiss-suggestion-${s.id}` ? C.accentBorder : undefined,
                                                            fontSize: '0.6rem',
                                                            padding: '2px 6px',
                                                        }}
                                                    >
                                                        {s.dismissBusy ? 'Dismissing...' : 'Dismiss'}
                                                    </button>
                                                </div>
                                            </div>
                                                );
                                            })
                                        }
                                    </>
                                )}
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
