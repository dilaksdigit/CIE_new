/**
 * CIE v2.3.1 + v2.3.2 Patch 6 — CMS form field visibility and tier banner copy per tier.
 * Input: tier string (hero/support/harvest/kill)
 * Output: show/hide form sections, max secondary intents, readonly state, exact banner text.
 */

/** v2.3.2 Patch 6: Exact banner copy per tier (CIE Hardening Addendum §6.1). */
export const TIER_BANNER_COPY = {
    hero: 'HERO SKU — Full CIE Coverage. This product is a top-revenue performer. All 9 intent types, full Answer Block, FAQ, JSON-LD, and channel feeds are enabled. Target: ≥85 readiness on all active channels within 30 days.',
    support: 'SUPPORT SKU — Focused Coverage. This product supports revenue but does not lead. Primary intent + max 2 secondary intents enabled. Answer Block and Best-For/Not-For required. Max 2 hours per quarter.',
    harvest: 'HARVEST SKU — Maintenance Mode. This product has low margin and limited growth potential. Only Specification + 1 optional intent are available. Answer Block, Best-For/Not-For, and Expert Authority are suspended. Max 30 minutes per quarter. Focus your time on Hero SKUs instead.',
    kill: 'KILL SKU — Editing Disabled. This product has negative margin or is flagged for delisting. All content fields are read-only. No time investment permitted. If you believe this classification is wrong, contact your Portfolio Holder to request a tier review (requires Finance co-approval).',
};

export const TIER_FIELD_MAP = {
    hero: {
        enabled: ['all_9_intents', 'answer_block', 'best_for', 'not_for', 'expert_authority', 'wikidata_uri', 'json_ld_preview'],
        max_secondary: 3,
        banner: TIER_BANNER_COPY.hero,
    },
    support: {
        enabled: ['all_9_intents', 'answer_block', 'best_for', 'not_for', 'expert_authority'],
        max_secondary: 2,
        hidden: ['wikidata_uri'],
        banner: TIER_BANNER_COPY.support,
    },
    harvest: {
        enabled: ['specification', 'problem_solving', 'compatibility'],
        hidden: ['answer_block', 'best_for', 'not_for', 'expert_authority', 'wikidata_uri', 'comparison', 'installation', 'troubleshooting', 'inspiration', 'regulatory', 'replacement'],
        max_secondary: 1,
        banner: TIER_BANNER_COPY.harvest,
    },
    kill: {
        enabled: [],
        readonly: true,
        banner: TIER_BANNER_COPY.kill,
    },
};

/**
 * Normalize tier to lowercase key (HERO -> hero).
 */
export function normalizeTier(tier) {
    if (!tier || typeof tier !== 'string') return '';
    return tier.trim().toLowerCase();
}

/**
 * Get config for tier. Uses TIER_FIELD_MAP keys (lowercase).
 */
export function getTierConfig(tier) {
    const key = normalizeTier(tier);
    return TIER_FIELD_MAP[key] || TIER_FIELD_MAP.support;
}

/**
 * Whether this tier is read-only (Kill).
 */
export function isTierReadonly(tier) {
    return getTierConfig(tier).readonly === true;
}

/**
 * Banner message for tier (v2.3.2 Patch 6: exact copy per tier).
 */
export function getTierBanner(tier) {
    const config = getTierConfig(tier);
    return config.banner ?? TIER_BANNER_COPY[normalizeTier(tier)] ?? null;
}

/**
 * Max secondary intents allowed for this tier (0 for kill).
 */
export function getMaxSecondaryIntents(tier) {
    const config = getTierConfig(tier);
    if (config.readonly) return 0;
    return config.max_secondary ?? 2;
}

/**
 * Whether a form field/section should be visible for this tier.
 * Field names: answer_block, best_for, not_for, expert_authority, wikidata_uri,
 * json_ld_preview, and intent keys (specification, problem_solving, compatibility, etc.)
 */
export function isFieldEnabledForTier(tier, fieldName) {
    const config = getTierConfig(tier);
    if (config.readonly) return false;
    if (config.hidden && config.hidden.includes(fieldName)) return false;
    if (config.enabled && config.enabled.length > 0) {
        if (config.enabled.includes('all_9_intents') && !config.hidden?.includes(fieldName)) return true;
        return config.enabled.includes(fieldName);
    }
    return true;
}

/**
 * Apply tier restrictions (for use on page load).
 * - If readonly: call disableAllFields(), showBanner(config.banner).
 * - Else: hide fields in config.hidden.
 * Returns the config for the tier.
 */
export function applyTierRestrictions(tier, options = {}) {
    const config = getTierConfig(tier);
    if (config.readonly) {
        if (typeof options.disableAllFields === 'function') options.disableAllFields();
        if (typeof options.showBanner === 'function') options.showBanner(config.banner);
        return config;
    }
    if (config.hidden && Array.isArray(config.hidden) && typeof options.hideField === 'function') {
        config.hidden.forEach((field) => options.hideField(field));
    }
    return config;
}

export default {
    TIER_FIELD_MAP,
    normalizeTier,
    getTierConfig,
    isTierReadonly,
    getTierBanner,
    getMaxSecondaryIntents,
    isFieldEnabledForTier,
    applyTierRestrictions,
};
