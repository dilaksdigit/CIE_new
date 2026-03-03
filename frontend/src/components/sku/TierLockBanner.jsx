import React from 'react';

const TierLockBanner = ({ text }) => {
    const message =
        text ||
        'Scheduled for removal — content editing is disabled for Kill-tier products.';

    return (
        <div className="tier-lock-banner">
            {message}
        </div>
    );
};

export default TierLockBanner;
