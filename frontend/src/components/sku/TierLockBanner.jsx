// SOURCE: CIE_v232_Hardening_Addendum.pdf §6.2 / §6.3
import React from 'react';

// §6.1 banner copy — do not replace with §6.2 field tooltip (KILL_FIELD_TOOLTIP).
// Both strings exist. Both are required. They serve different UI roles.
const CANONICAL_KILL_TEXT =
    'KILL SKU — Editing Disabled. This product has negative margin or is ' +
    'flagged for delisting. All content fields are read-only. No time ' +
    'investment permitted. If you believe this classification is wrong, ' +
    'contact your Portfolio Holder to request a tier review (requires ' +
    'Finance co-approval).';

const TierLockBanner = ({ text }) => {
    const message = text || CANONICAL_KILL_TEXT;

    return (
        <div className="tier-lock-banner">
            {message}
        </div>
    );
};

export default TierLockBanner;
