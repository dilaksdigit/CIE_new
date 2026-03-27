// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 6
// SOURCE: CIE_v232_Hardening_Addendum.pdf §6.2 / §6.3
// SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 5
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §§4.1, 4.2, 5; Trap 2 | CIE_v232_UI_Restructure_Instructions.docx §2.1 | CIE_v232_Semrush_CSV_Import_Spec.docx §§1, 3.2, 3.3 | CIE_v232_Writer_View.jsx
import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api, { writerEditApi, configApi } from '../services/api';
import THEME from '../theme';
import TierBanner from '../components/TierBanner/TierBanner';
import TierFieldTooltip from '../components/TierBanner/TierFieldTooltip';
import { TIER_FIELD_MAP } from '../lib/tierFieldMap';

const C = THEME;

const FIELD_LABELS = {
    title: 'Product Title',
    description: 'Description',
    specification: 'Basic Specification',
    answer_block: 'Answer Block',
    best_for: 'Best For (who should buy this)',
    not_for: 'Not For (who should NOT buy this)',
    expert_authority: 'Expert Authority (certifications & proof)',
    main_customer_reason: 'Main Customer Reason',
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
    main_customer_reason: 'readonly',
};

// Field min/max from BusinessRules (content.title_max_length, gates.description_word_count_min, etc.) — loaded on mount
// faq_tab: min 0 so it counts as complete for progress (optional; no gate) and progress bar can show 6/6 for Hero
const getDefaultFieldRanges = () => ({
    title: { min: 1, max: null },
    description: { min: null, max: null },
    specification: { min: 50, max: null },
    best_for: { min: 1, max: null },
    not_for: { min: 1, max: null },
    expert_authority: { min: 1, max: null },
    faq_tab: { min: 0, max: null },
});

const normalizeTier = (tier) => String(tier || '').trim().toLowerCase();
const SUGGESTION_CARD_TYPE_META = {
    keyword:    { label: 'Keyword Opportunity', iconColor: '#5B7A3A' },
    citation:   { label: 'AI Visibility Issue', iconColor: '#C62828' },
    trend:      { label: 'Trending Search', iconColor: '#1565C0' },
    competitor: { label: 'Competitor Gap', iconColor: '#E65100' },
};

const PRIORITY_META = {
    // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.1 priority mapping
    high:   { badgeText: 'HIGH', color: THEME.red,   bg: THEME.redBg },
    medium: { badgeText: 'MED',  color: THEME.amber, bg: THEME.amberBg },
    low:    { badgeText: 'LOW',  color: THEME.amber, bg: THEME.amberBg },
};

const PRIORITY_ORDER = ['high', 'medium', 'low'];
const CARD_ICONS = {
    // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2; CLAUDE.md §8
    // FIX: UI-25 — text icon indicators (no emojis).
    keyword: { symbol: 'KW', color: '#2E7D32' },
    citation: { symbol: 'AI', color: '#C62828' },
    trend: { symbol: 'TR', color: '#1565C0' },
    competitor: { symbol: 'CG', color: '#E65100' },
};

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

// SOURCE: CLAUDE.md R3 — Gate keys are for INTERNAL mapping only. Never render g1, g2, G1, G2 etc. to DOM. Use user_message from validation response for all writer-facing text.
const normalizeGateKey = (value) => {
    const v = String(value || '').toLowerCase().replace(/\s+/g, '_');
    if (v.includes('vector')) return 'vector_similarity';
    if (v.startsWith('g1')) return 'g1';
    if (v.startsWith('g2')) return 'g2';
    if (v.startsWith('g3')) return 'g3';
    if (v.startsWith('g4')) return 'g4';
    if (v.startsWith('g5')) return 'g5';
    // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 — G6 and G6.1 are distinct
    if (v.startsWith('g6_1') || v === 'g6.1') return 'g6_1';
    if (v.startsWith('g6')) return 'g6';
    if (v.startsWith('g7')) return 'g7';
    return v;
};

const gateKeysForField = (field) => {
    if (field === 'title') return ['g1', 'g2', 'g3'];
    if (field === 'description' || field === 'specification') return ['g6', 'g6_1', 'vector_similarity'];
    if (field === 'problem_solving' || field === 'compatibility') return ['g2', 'g3', 'g6', 'g6_1'];
    if (field === 'answer_block') return ['g4'];
    if (field === 'best_for' || field === 'not_for') return ['g5'];
    if (field === 'expert_authority') return ['g7'];
    if (field === 'main_customer_reason') return ['g2', 'g3'];
    return [];
};

const normalizeGates = (rawGates) => {
    const map = {};
    if (Array.isArray(rawGates)) {
        rawGates.forEach((g) => {
            const key = normalizeGateKey(g?.gate ?? g?.code ?? g?.id);
            const rawStatus = (g && (g.status || g.state)) || (g?.passed === true ? 'pass' : g?.passed === false ? 'fail' : null);
            // SOURCE: openapi.yaml ValidationResponse.gates.status, CIE_v232_Hardening_Addendum.pdf §1.1 — not_applicable is non-blocking for Submit visibility
            // SOURCE: openapi.yaml — vector_check.status enum includes 'warn'
            // SOURCE: CIE_v232_UI_Restructure_Instructions §6 — amber #E65100 for warning states
            const status =
                rawStatus === 'pass' || rawStatus === 'not_applicable'
                    ? 'pass'
                    : rawStatus === 'warning' || rawStatus === 'warn' || rawStatus === 'pending'
                    ? 'warning'
                    : 'fail';
            map[key] = {
                status,
                rawStatus,
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
            // SOURCE: openapi.yaml ValidationResponse.gates.status, CIE_v232_Hardening_Addendum.pdf §1.1
            // SOURCE: openapi.yaml — vector_check.status enum includes 'warn'
            // SOURCE: CIE_v232_UI_Restructure_Instructions §6 — amber #E65100 for warning states
            const status =
                rawStatus === 'pass' || rawStatus === 'not_applicable'
                    ? 'pass'
                    : rawStatus === 'warning' || rawStatus === 'warn' || rawStatus === 'pending'
                    ? 'warning'
                    : 'fail';
            map[key] = {
                status,
                rawStatus,
                reason: v?.reason || '',
                metadata: v?.metadata || {},
                user_message: v?.user_message || v?.metadata?.user_message || '',
            };
        });
    }
    return map;
};

// SOURCE: openapi.yaml — vector_check at root can be warn/pending while gates.vector_check still shows pass (warn_only path)
const mergeTopLevelVectorCheck = (gateMap, vectorCheck) => {
    if (!vectorCheck || typeof vectorCheck !== 'object') return gateMap;
    const rawStatus = String(vectorCheck.status || 'pass').toLowerCase();
    const uiStatus =
        rawStatus === 'pass' || rawStatus === 'not_applicable'
            ? 'pass'
            : rawStatus === 'warning' || rawStatus === 'warn' || rawStatus === 'pending'
              ? 'warning'
              : 'fail';
    const prev = gateMap.vector_similarity;
    const um = (vectorCheck.user_message || prev?.user_message || '').trim();
    return {
        ...gateMap,
        vector_similarity: {
            status: uiStatus,
            rawStatus,
            reason: prev?.reason || '',
            metadata: prev?.metadata || {},
            user_message: um,
        },
    };
};

const pickList = (...values) => {
    for (const value of values) {
        if (Array.isArray(value) && value.length > 0) return value.join(', ');
        if (typeof value === 'string' && value.trim()) return value.trim();
    }
    return '';
};

const toList = (value) =>
    String(value || '')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

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

// SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §4.4 — default when vector/pending has no user_message
const PENDING_GATE_HINT = 'Validation pending. Content saved but awaiting AI review.';

/** abMin/abMax = gates.answer_block_min_chars / gates.answer_block_max_chars (§5.3); required for g4 fallback when API omits min/max */
const gateHintText = (gateKey, gate, values, abMin, abMax) => {
    const meta = gate?.metadata || {};
    const missingTerms = gate?.missing_terms ?? meta.missing_terms ?? meta.terms ?? meta.key_terms;
    const termsStr = Array.isArray(missingTerms) ? missingTerms.join(', ') : (typeof missingTerms === 'string' ? missingTerms : '');
    const missingElements = gate?.missing_elements ?? meta.missing_elements ?? meta.elements ?? meta.missing;
    const elementsArr = Array.isArray(missingElements) ? missingElements : (typeof missingElements === 'string' ? [missingElements] : []);
    const categoryTerms = gate?.category_terms ?? meta.category_terms ?? meta.terms;
    const categoryStr = Array.isArray(categoryTerms) ? categoryTerms.join(', ') : (typeof categoryTerms === 'string' ? categoryTerms : '');
    const minChars = gate?.min_chars ?? meta.min_chars ?? meta.min ?? abMin;
    const maxChars = gate?.max_chars ?? meta.max_chars ?? meta.max ?? abMax;
    const currentChars = gate?.current_chars ?? meta.current_chars ?? meta.current_length ?? (values?.answer_block != null ? String(values.answer_block).length : null);

    if (gateKey === 'g1') {
        if (elementsArr.length >= 3) {
            const [keyFeature, productType, differentiator] = elementsArr;
            return `Title format needs fixing. Follow: ${keyFeature} + ${productType} + ${differentiator}.`;
        }
        return 'Title format needs fixing. Follow: [Key Feature] + [Product Type] + [Differentiator].';
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
        if (currentChars != null && minChars != null && maxChars != null) {
            const missing = elementsArr.length ? elementsArr.join(', ') : 'more detail';
            return `Too short (${currentChars} chars). Needs ${minChars}-${maxChars}. Add ${missing}.`;
        }
        return 'Answer block is too short. Add more detail to meet the required length.';
    }
    if (gateKey === 'g5') return 'Technical details incomplete. Add certifications, specs, or standards that prove quality.';
    if (gateKey === 'g6') return 'Missing commercial info. Add pricing context, warranty, or delivery details as needed.';
    if (gateKey === 'g6_1') return 'Intent selection is not allowed for this product tier. Adjust primary or supporting intents per tier rules.';
    if (gateKey === 'g7') return 'Authority section needs expert credentials. Add industry standards, testing results, or certifications.';
    if (gateKey === 'vector_similarity') {
        if (categoryStr) return `Your content has drifted from the category focus. Rewrite to include more ${categoryStr}.`;
        return 'Your content has drifted from the category focus. Review the category keywords.';
    }
    return '';
};

const fieldStateAndHint = (field, gates, values, abMin, abMax) => {
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
            ? (primaryFail.gate.user_message || gateHintText(primaryFail.key, primaryFail.gate, values, abMin, abMax))
            : '';
        return { state: 'fail', hint };
    }

    if (hasWarning) {
        const primaryWarning = related.find((r) => r.gate.status === 'warning') || null;
        // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §6, CLAUDE.md §11 — warn/pending show hint text, not border-only
        let hint = '';
        if (primaryWarning) {
            hint = (primaryWarning.gate.user_message || '').trim();
            if (!hint && primaryWarning.gate.rawStatus === 'pending') {
                hint = PENDING_GATE_HINT;
            }
            if (!hint) {
                hint = gateHintText(primaryWarning.key, primaryWarning.gate, values, abMin, abMax);
            }
            // SOURCE: CLAUDE.md §11 — vector warn often has scrubbed message; keep canonical copy
            if (!hint && primaryWarning.key === 'vector_similarity') {
                hint = 'Your content may not align with the intent. Consider revising.';
            }
        } else {
            hint = PENDING_GATE_HINT;
        }
        return { state: 'warning', hint: hint || PENDING_GATE_HINT };
    }

    return { state: 'pass', hint: '' };
};

/** Client-side completion: field has enough content to count as "complete" for progress. When answerBlockMin/Max are null, skip answer_block validation (no fail). */
const isFieldComplete = (field, values, answerBlockMin, answerBlockMax, fieldRanges) => {
    const v = String((values && values[field]) || '').trim();
    if (field === 'main_customer_reason') {
        return v.length > 0;
    }
    if (field === 'answer_block') {
        if (answerBlockMin == null || answerBlockMax == null) return false; // skip until API loads
        const len = v.length;
        if (len < answerBlockMin || len > answerBlockMax) return false;
        return true;
    }
    const range = (fieldRanges || getDefaultFieldRanges())[field];
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

const counterText = (field, value, answerBlockMin, answerBlockMax, fieldRanges) => {
    const len = String(value || '').length;
    if (field === 'answer_block') {
        if (answerBlockMin == null || answerBlockMax == null) return `${len} / —`;
        return `${len}/${answerBlockMin}-${answerBlockMax}`;
    }
    const range = (fieldRanges || getDefaultFieldRanges())[field] || { min: null, max: null };
    if (range.min != null && range.max != null) return `${len}/${range.min}-${range.max}`;
    if (range.max != null) return `${len}/max ${range.max}`;
    return `${len}/—`;
};

const shouldShowCounter = (field) => field === 'title' || field === 'answer_block';

const counterColor = (field, value, answerBlockMin, answerBlockMax, fieldRanges) => {
    const len = String(value || '').length;
    if (field === 'answer_block') {
        if (answerBlockMax != null && len > answerBlockMax) return '#C62828';
        return '#6B6B6B';
    }
    if (field === 'title') {
        const max = (fieldRanges || getDefaultFieldRanges()).title?.max;
        if (max != null && len > max) return '#C62828';
        return '#6B6B6B';
    }
    return '#6B6B6B';
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
        main_customer_reason: '',
    });
    const [gates, setGates] = useState({});
    const [degradedMode, setDegradedMode] = useState(false);
    const [publishAllowed, setPublishAllowed] = useState(true);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [validateBusy, setValidateBusy] = useState(false);
    const [publishBusy, setPublishBusy] = useState(false);
    const [publishError, setPublishError] = useState('');
    const [chsData, setChsData] = useState(null);

    // §5.3 gates.answer_block_min_chars / gates.answer_block_max_chars, content.title_max_length, gates.description_word_count_min — no literals
    const [answerBlockMin, setAnswerBlockMin] = useState(null);
    const [answerBlockMax, setAnswerBlockMax] = useState(null);
    const [fieldRanges, setFieldRanges] = useState(() => getDefaultFieldRanges());

    useEffect(() => {
        let cancelled = false;
        configApi
            .get()
            .then((res) => {
                if (cancelled) return;
                const raw = res.data?.data ?? res.data ?? {};
                const gates = raw.gates || {};
                const thresholds = raw.gate_thresholds || {};
                const content = raw.content || {};
                const minVal = gates.answer_block_min_chars ?? thresholds.answer_block_min_chars ?? thresholds.answer_block_min;
                const maxVal = gates.answer_block_max_chars ?? thresholds.answer_block_max_chars ?? thresholds.answer_block_max;
                const titleMaxVal = content.title_max_length ?? thresholds.title_max_length;
                const descMinVal = gates.description_word_count_min ?? thresholds.description_word_count_min;
                if (minVal != null && minVal !== '') {
                    const n = parseInt(String(minVal), 10);
                    if (!Number.isNaN(n)) setAnswerBlockMin(n);
                }
                if (maxVal != null && maxVal !== '') {
                    const n = parseInt(String(maxVal), 10);
                    if (!Number.isNaN(n)) setAnswerBlockMax(n);
                }
                const base = getDefaultFieldRanges();
                let titleMax = null;
                let descMin = null;
                if (titleMaxVal != null && titleMaxVal !== '') {
                    const n = parseInt(String(titleMaxVal), 10);
                    if (!Number.isNaN(n)) titleMax = n;
                }
                if (descMinVal != null && descMinVal !== '') {
                    const n = parseInt(String(descMinVal), 10);
                    if (!Number.isNaN(n)) descMin = n;
                }
                setFieldRanges({
                    ...base,
                    title: { min: 1, max: titleMax },
                    description: { min: descMin, max: null },
                });
            })
            .catch(() => {});
        return () => {
            cancelled = true;
        };
    }, []);

    const [suggestionsOpen, setSuggestionsOpen] = useState(true);
    const [suggestions, setSuggestions] = useState([]);
    const [hoveredId, setHoveredId] = useState(null);
    const [hasSemrushData, setHasSemrushData] = useState(false);
    const [auditUnavailable, setAuditUnavailable] = useState(false);
    const [copiedSuggestionId, setCopiedSuggestionId] = useState(null);
    // SOURCE: CIE_Master_Developer_Build_Spec.docx §4.4 / §4.5 — AI pre-fill + confidence
    const [aiSuggestLoading, setAiSuggestLoading] = useState(false);
    const [aiSuggestResponse, setAiSuggestResponse] = useState(null);
    const [aiSuggestError, setAiSuggestError] = useState('');

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
                setAuditUnavailable(false);
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
                    best_for: pickList(item?.best_for, item?.bestFor),
                    not_for: pickList(item?.not_for, item?.notFor),
                    expert_authority: item?.expert_authority || item?.expert_authority_name || '',
                    main_customer_reason: String(item?.primary_intent || '')
                        .replace(/_/g, ' ')
                        .replace(/\b\w/g, (c) => c.toUpperCase()),
                });
                // SOURCE: CLAUDE.md §15
                // FIX: UI-23 — CHS weighted component breakdown.
                setChsData(payload?.chs ?? item?.chs ?? null);

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
                        // SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 5 — priority from search_volume thresholds
                        const vol = row.search_volume;
                        let priority = 'medium';
                        if (vol != null && vol !== '') {
                            const n = Number(vol);
                            if (!Number.isNaN(n)) {
                                priority = n >= 1000 ? 'high' : n >= 300 ? 'medium' : 'low';
                            }
                        }
                        suggestionItems.push({
                            id: `semrush-keyword-${skuId}-${idx}`,
                            type: 'keyword',
                            keyword: row.keyword,
                            search_volume: row.search_volume,
                            trend: row.trend,
                            instruction: row.instruction,
                            title: row.title || (row.keyword ? `Keyword: ${row.keyword}` : 'Keyword Opportunity'),
                            date_range: row.date_range || row.window || '',
                            priority,
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
                        // SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 5 — HIGH if gap keyword search_volume > 1000
                        const priority = (row.search_volume != null && Number(row.search_volume) > 1000) ? 'high' : 'medium';
                        suggestionItems.push({
                            id: `semrush-competitor-${skuId}-${idx}`,
                            type: 'competitor',
                            competitor_action: row.competitor_action,
                            our_gap: row.our_gap,
                            title: row.title || 'Competitor Gap',
                            date_range: row.date_range || row.window || '',
                            priority,
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
                        // Fail-soft: if AI Audit is unavailable, show user message per CIE_v232_Hardening_Addendum §4.5
                        setAuditUnavailable(true);
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
        // SOURCE: CLAUDE.md Section 7 — Tier changes propagate to field visibility immediately
        }, 5000);

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
                    best_for: toList(values.best_for),
                    not_for: toList(values.not_for),
                    expert_authority: values.expert_authority,
                };
                const res = await writerEditApi.validate(skuId, body);
                if (cancelled) return;
                // SOURCE: openapi.yaml ValidationResponse at JSON root (PHP validate unwrapped per ENF§7.2)
                const data = res?.data && typeof res.data === 'object' ? res.data : {};
                const gatePayload = data.gates ?? {};
                setGates(mergeTopLevelVectorCheck(normalizeGates(gatePayload), data.vector_check));
                setDegradedMode(Boolean(data.degraded_mode));
                setPublishAllowed(data.publish_allowed !== false);
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
        (f) =>
            isFieldComplete(f, values, answerBlockMin, answerBlockMax, fieldRanges) ||
            fieldStateAndHint(f, gates, values, answerBlockMin, answerBlockMax).state === 'pass'
    ).length;
    const progressTotalRequired = requiredFields.length;

    const gatePassedCount = gatedRequiredFields.filter(
        (f) => fieldStateAndHint(f, gates, values, answerBlockMin, answerBlockMax).state === 'pass'
    ).length;
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

    const handleAiSuggest = async () => {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §4.4 / §4.5
        setAiSuggestLoading(true);
        setAiSuggestError('');
        setAiSuggestResponse(null);
        try {
            const res = await writerEditApi.suggest(skuId);
            const data = res?.data ?? {};
            setAiSuggestResponse(data);
            if (data.error) {
                setAiSuggestError(data.error);
            }
        } catch {
            const msg = 'AI suggestions unavailable — enter manually.';
            setAiSuggestError(msg);
            setAiSuggestResponse({ error: msg, fields_editable: true });
        } finally {
            setAiSuggestLoading(false);
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

            {/* SOURCE: Hardening Addendum §1.1 — degraded mode banner */}
            {degradedMode && (
                <div
                    className="degraded-banner"
                    style={{
                        background: '#FFF3CD',
                        border: '1px solid #FFEAA7',
                        padding: '12px 16px',
                        marginBottom: '16px',
                        borderRadius: '4px',
                        color: '#856404',
                        fontWeight: 600,
                        fontSize: '0.9rem',
                    }}
                >
                    Description validation temporarily unavailable. Your changes are saved but
                    publishing is paused until validation completes (typically within 30 minutes).
                </div>
            )}

            {/* SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2
                FIX: UI-15 — explicit 70/30 split. */}
            <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                <div style={{ width: '70%', minWidth: 0 }}>
                    <TierBanner tier={tier} />
                    {chsData && (
                        <div className="card" style={{ marginBottom: 12 }}>
                            <div style={{ fontSize: '0.82rem', fontWeight: 700, color: C.text, marginBottom: 10 }}>
                                Content Health Score: {chsData.total ?? '—'}/100
                            </div>
                            {[
                                ['intent_alignment', 'Intent Alignment', '25%'],
                                ['semantic_coverage', 'Semantic Coverage', '20%'],
                                ['technical_seo', 'Technical SEO Hygiene', '20%'],
                                ['competitive_gap', 'Competitive Gap', '20%'],
                                ['ai_readiness', 'AI Readiness', '15%'],
                            ].map(([key, label, weight]) => (
                                <div key={key} style={{ display: 'flex', justifyContent: 'space-between', padding: '4px 0', borderBottom: `1px solid ${C.muted}` }}>
                                    <span style={{ color: C.textMid, fontSize: '0.76rem' }}>{label} ({weight})</span>
                                    <span style={{ fontFamily: 'var(--mono)', color: C.text }}>
                                        {chsData[key] ?? (key === 'competitive_gap' && chsData.has_semrush === false ? 'No Data' : '—')}
                                    </span>
                                </div>
                            ))}
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

                    {/* SOURCE: ENF§2.1 G6.1, BUILD§Step3, UI_Restructure §2.1 — Kill: zero content field cards in DOM (banner + header identity only) */}
                    {normalizeTier(tier) === 'kill' ? (
                        <div
                            style={{
                                background: '#FDEEEB',
                                border: '1px solid #E5B5AD',
                                color: '#A63D2F',
                                borderRadius: 6,
                                padding: 12,
                                marginBottom: 12,
                                fontSize: '0.78rem',
                                fontWeight: 600,
                            }}
                        >
                            This product is locked and scheduled for removal. No edit capability.
                        </div>
                    ) : isReadonly ? (
                        <>
                            {Object.keys(FIELD_LABELS).map((field) => (
                                <div
                                    key={field}
                                    style={{
                                        background: THEME.surface,
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 6,
                                        padding: 12,
                                        marginBottom: 12,
                                    }}
                                >
                                    <div style={{ fontSize: '0.72rem', fontWeight: 700, color: C.text, marginBottom: 6 }}>
                                        {FIELD_LABELS[field]}
                                    </div>
                                    <TierFieldTooltip fieldName={field} tier={tier} />
                                </div>
                            ))}
                        </>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                            {requiredFields.map((field) => {
                                const state = fieldStateAndHint(field, gates, values, answerBlockMin, answerBlockMax);
                                // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §6, CLAUDE.md §11 — fail + warn/pending show user_message (or PENDING_GATE_HINT)
                                const showHint = state.state === 'fail' || state.state === 'warning';
                                const isInput = FIELD_TYPES[field] === 'input';
                                const isReadonlyField = FIELD_TYPES[field] === 'readonly';
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
                                        {isReadonlyField ? (
                                            <>
                                                <input
                                                    className="field-input readonly"
                                                    value={values[field] || ''}
                                                    readOnly
                                                    disabled
                                                    style={{ background: C.muted, borderColor: C.border, color: C.textMid }}
                                                />
                                                <div style={{ marginTop: 6, fontSize: '0.68rem', color: C.textMid }}>
                                                    (Set by system based on search data)
                                                </div>
                                            </>
                                        ) : isInput ? (
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
                                        {shouldShowCounter(field) && (
                                            <div
                                                style={{
                                                    marginTop: 5,
                                                    fontSize: '0.65rem',
                                                    color: counterColor(field, values[field], answerBlockMin, answerBlockMax, fieldRanges),
                                                }}
                                            >
                                                {counterText(field, values[field], answerBlockMin, answerBlockMax, fieldRanges)}
                                            </div>
                                        )}
                                        {showHint && state.hint && (
                                            <div
                                                style={{
                                                    marginTop: 6,
                                                    fontSize: '0.68rem',
                                                    color: hintColorForState(state.state),
                                                    background: state.state === 'fail' ? '#FFEBEE' : '#FFFDE7',
                                                    border: `1px solid ${state.state === 'fail' ? '#EF9A9A' : '#FFCC80'}`,
                                                    borderRadius: 4,
                                                    padding: '6px 8px',
                                                }}
                                            >
                                                {state.hint}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}

                            {/* §6.2 Hidden fields: field card with label + TierFieldTooltip below */}
                            {(TIER_FIELD_MAP[tier]?.hidden || []).map((field) => (
                                <div
                                    key={`hidden-${field}`}
                                    style={{
                                        background: THEME.surface,
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 6,
                                        padding: 12,
                                        marginBottom: 12,
                                    }}
                                >
                                    <div style={{ fontSize: '0.72rem', fontWeight: 700, color: C.text, marginBottom: 6 }}>
                                        {FIELD_LABELS[field] || field}
                                    </div>
                                    <TierFieldTooltip fieldName={field} tier={tier} />
                                </div>
                            ))}
                        </div>
                    )}

                    {!isReadonly && allRequiredPass && (
                        <div style={{ marginTop: 12 }}>
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={handleSubmit}
                                disabled={publishBusy || !publishAllowed}
                                onMouseEnter={() => setHoveredId('publish')}
                                onMouseLeave={() => setHoveredId(null)}
                                style={{
                                    background: hoveredId === 'publish' ? THEME.accentLight : undefined,
                                    borderColor: hoveredId === 'publish' ? THEME.accentBorder : undefined,
                                    color: hoveredId === 'publish' ? THEME.text : undefined,
                                }}
                            >
                                {publishBusy ? 'Publishing…' : 'Submit'}
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

                <aside style={{ width: '30%', minWidth: 0 }}>
                    <div className="card">
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: suggestionsOpen ? 10 : 0 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                <span style={{ display: 'inline-flex', alignItems: 'center', color: C.accent }}>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 3a7 7 0 0 0-4.6 12.3c.9.8 1.6 1.8 1.8 3h5.6c.2-1.2.9-2.2 1.8-3A7 7 0 0 0 12 3Z" stroke="currentColor" strokeWidth="1.8"/>
                                        <path d="M9.5 20h5M10 22h4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"/>
                                    </svg>
                                </span>
                                <div style={{ fontSize: '0.76rem', fontWeight: 700, color: C.text }}>AI Suggestions</div>
                            </div>
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
                                <div style={{ fontSize: '0.68rem', color: C.textMid }}>
                                    Suggestions are built from Semrush, AI Audit, and Google Analytics data.
                                </div>
                                {/* SOURCE: CIE_Master_Developer_Build_Spec.docx §4.5 — FIX: AI-10 fail-soft copy */}
                                {auditUnavailable && (
                                    <p style={{ color: '#6B6860', fontSize: '13px', marginBottom: '12px' }}>
                                        AI suggestions unavailable — enter manually.
                                    </p>
                                )}
                                {!isReadonly && (
                                    <div style={{ marginBottom: 10 }}>
                                        <button
                                            type="button"
                                            className="btn btn-secondary btn-sm"
                                            disabled={aiSuggestLoading}
                                            onClick={() => handleAiSuggest()}
                                            onMouseEnter={() => setHoveredId('ai-suggest')}
                                            onMouseLeave={() => setHoveredId(null)}
                                            style={{
                                                background: hoveredId === 'ai-suggest' ? C.muted : undefined,
                                                borderColor: hoveredId === 'ai-suggest' ? C.accentBorder : undefined,
                                            }}
                                        >
                                            {aiSuggestLoading ? 'Requesting suggestions…' : 'Get AI content suggestions'}
                                        </button>
                                    </div>
                                )}
                                {aiSuggestError && (
                                    <p style={{ color: '#6B6860', fontSize: '13px', marginBottom: 8 }}>{aiSuggestError}</p>
                                )}
                                {aiSuggestResponse &&
                                    (() => {
                                        const c = Number(aiSuggestResponse.confidence_score);
                                        return !Number.isNaN(c) && c < 0.6;
                                    })() &&
                                    !aiSuggestResponse.error && (
                                        <div
                                            style={{
                                                padding: '8px 12px',
                                                backgroundColor: '#FFF3E0',
                                                border: '1px solid #E65100',
                                                borderRadius: '4px',
                                                color: '#E65100',
                                                fontSize: '13px',
                                                marginBottom: '8px',
                                            }}
                                        >
                                            Low confidence suggestion — please review carefully before saving.
                                        </div>
                                    )}
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
                                                const cardTypeColor = typeMeta.iconColor;
                                                return (
                                            <div
                                                key={s.id}
                                                style={{
                                                    borderRadius: 6,
                                                    border: `1px solid ${C.border}`,
                                                    borderLeft: `4px solid ${cardTypeColor}`,
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
                                                        <div
                                                            style={{
                                                                width: '28px',
                                                                height: '28px',
                                                                borderRadius: '50%',
                                                                backgroundColor: `${(CARD_ICONS[s.type] || CARD_ICONS.keyword).color}15`,
                                                                color: (CARD_ICONS[s.type] || CARD_ICONS.keyword).color,
                                                                display: 'flex',
                                                                alignItems: 'center',
                                                                justifyContent: 'center',
                                                                fontSize: '11px',
                                                                fontWeight: 700,
                                                                flexShrink: 0,
                                                            }}
                                                        >
                                                            {(CARD_ICONS[s.type] || CARD_ICONS.keyword).symbol}
                                                        </div>
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
                                                {/* SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 5 Step 2c — each card: source label + data date */}
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 8 }}>
                                                    <span style={{ fontSize: '0.72rem', color: C.textMid }}>Source:</span>
                                                    <span style={{
                                                        fontSize: '0.72rem', fontWeight: 700,
                                                        padding: '1px 6px', borderRadius: 3,
                                                        background: C.accentLight, color: C.accent,
                                                        border: `1px solid ${C.accentBorder}`
                                                    }}>
                                                        {s.source}
                                                    </span>
                                                    <span style={{ fontSize: '0.72rem', color: C.textMid }}>
                                                        As of: {s.dateRange || 'Date unavailable'}
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
                                                        onClick={() => {
                                                            const textToCopy = s.explanation || s.title || '';
                                                            navigator.clipboard.writeText(textToCopy).then(() => {
                                                                setCopiedSuggestionId(s.id);
                                                                window.setTimeout(() => setCopiedSuggestionId(null), 2000);
                                                            });
                                                        }}
                                                        onMouseEnter={() => setHoveredId(`use-suggestion-${s.id}`)}
                                                        onMouseLeave={() => setHoveredId(null)}
                                                        style={{
                                                            marginRight: 6,
                                                            background: hoveredId === `use-suggestion-${s.id}` ? C.muted : undefined,
                                                            borderColor: hoveredId === `use-suggestion-${s.id}` ? C.accentBorder : undefined,
                                                            fontSize: '0.6rem',
                                                            padding: '2px 6px',
                                                        }}
                                                    >
                                                        {copiedSuggestionId === s.id ? 'Copied!' : 'Use this suggestion'}
                                                    </button>
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
