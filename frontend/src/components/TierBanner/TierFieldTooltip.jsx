// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 6

import React from 'react';
import { TIER_TOOLTIPS, KILL_FIELD_TOOLTIP } from '../../lib/tierFieldMap';

/**
 * Renders the exact tooltip string for a field hidden for the given tier (§6.2).
 * Returns null if the field is not hidden for that tier (or no tooltip defined).
 * Kill tier: every field gets the "All fields" kill tooltip.
 * @param {{ fieldName: string, tier: string }} props
 */
function TierFieldTooltip({ fieldName, tier }) {
    const t = String(tier || '').trim().toLowerCase();
    const field = String(fieldName || '').trim();

    if (t === 'kill') {
        return (
            <div className="cie-hidden-field-info" style={{
                padding: '10px 14px',
                background: '#F5F5F5',
                border: '1px dashed #BDBDBD',
                borderRadius: 4,
                fontSize: '0.78rem',
                color: '#757575',
                marginTop: 6,
            }}>
                {KILL_FIELD_TOOLTIP}
            </div>
        );
    }

    const message = TIER_TOOLTIPS[field]?.[t];
    if (!message) return null;

    return (
        <div className="cie-hidden-field-info" style={{
            padding: '10px 14px',
            background: '#F5F5F5',
            border: '1px dashed #BDBDBD',
            borderRadius: 4,
            fontSize: '0.78rem',
            color: '#757575',
            marginTop: 6,
        }}>
            {message}
        </div>
    );
}

export default TierFieldTooltip;
