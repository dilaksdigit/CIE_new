// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 6

import React from 'react';

/** §6.1 Banner background colours (Hardening Addendum exactly — not badge colours). */
const BANNER_BG = {
    hero: '#E8F5E9',
    support: '#FFF8E1',
    harvest: '#FFF3E0',
    kill: '#FFEBEE',
};

/** §6.1 Exact banner copy per tier. */
const BANNER_TEXT = {
    hero: 'HERO SKU — Full CIE Coverage. This product is a top-revenue performer. All 9 intent types, full Answer Block, FAQ, JSON-LD, and channel feeds are enabled. Target: ≥85 readiness on all active channels within 30 days.',
    support: 'SUPPORT SKU — Focused Coverage. This product supports revenue but does not lead. Primary intent + max 2 secondary intents enabled. Answer Block and Best-For/Not-For required. Max 2 hours per quarter.',
    harvest: 'HARVEST SKU — Maintenance Mode. This product has low margin and limited growth potential. Only Specification + 1 optional intent are available. Answer Block, Best-For/Not-For, and Expert Authority are suspended. Max 30 minutes per quarter. Focus your time on Hero SKUs instead.',
    kill: 'KILL SKU — Editing Disabled. This product has negative margin or is flagged for delisting. All content fields are read-only. No time investment permitted. If you believe this classification is wrong, contact your Portfolio Holder to request a tier review (requires Finance co-approval).',
};

const TIER_LABELS = {
    hero: 'HERO',
    support: 'SUPPORT',
    harvest: 'HARVEST',
    kill: 'KILL',
};

/**
 * Tier CMS banner for writer edit left panel.
 * Desktop only (min-width 1280px). Text only, no emojis or icons.
 * @param {{ tier: 'hero'|'support'|'harvest'|'kill' }} props
 */
function TierBanner({ tier }) {
    const key = String(tier || '').trim().toLowerCase();
    const bg = BANNER_BG[key] || BANNER_BG.support;
    const text = BANNER_TEXT[key] || BANNER_TEXT.support;
    const label = TIER_LABELS[key] || key.toUpperCase();

    return (
        <div
            className="tier-banner"
            style={{
                width: '100%',
                background: bg,
                padding: '10px 12px',
                marginBottom: 12,
                fontSize: '0.78rem',
                lineHeight: 1.4,
                color: '#1a1a1a',
            }}
        >
            <strong style={{ textTransform: 'uppercase', fontWeight: 700 }}>{label} SKU</strong>
            {' — '}
            {text.substring((label + ' SKU — ').length)}
        </div>
    );
}

export default TierBanner;
