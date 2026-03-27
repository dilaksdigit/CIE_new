// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 6 (banner colours); CIE_v232_UI_Restructure_Instructions.docx §2.1 (copy via tierFieldMap)

import React from 'react';
import { TIER_BANNER_COPY } from '../../lib/tierFieldMap';

// SOURCE: CLAUDE.md §8; CIE_v232_UI_Restructure_Instructions.docx §5
// FIX: UI-17 — tier banner palette aligned to tier colors.
const BANNER_STYLES = {
    hero: { bg: '#FDF6E3', border: '#E8D5A0', text: '#8B6914' },
    support: { bg: '#EBF3F9', border: '#B5D0E3', text: '#3D6B8E' },
    harvest: { bg: '#FFF8E7', border: '#E8D49A', text: '#B8860B' },
    kill: { bg: '#FDEEEB', border: '#E5B5AD', text: '#A63D2F' },
};

/**
 * Tier CMS banner for writer edit left panel.
 * Desktop only (min-width 1280px). Text only, no emojis or icons.
 * @param {{ tier: 'hero'|'support'|'harvest'|'kill' }} props
 */
function TierBanner({ tier }) {
    const key = String(tier || '').trim().toLowerCase();
    const style = BANNER_STYLES[key] || BANNER_STYLES.support;
    // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.1 — single canonical copy from TIER_BANNER_COPY
    const text = TIER_BANNER_COPY[key] || TIER_BANNER_COPY.support;

    return (
        <div
            className="tier-banner"
            style={{
                width: '100%',
                background: style.bg,
                border: `1px solid ${style.border}`,
                padding: '10px 12px',
                marginBottom: 12,
                fontSize: '0.78rem',
                lineHeight: 1.4,
                color: style.text,
            }}
        >
            {text}
        </div>
    );
}

export default TierBanner;
