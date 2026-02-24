import React from 'react';
const tierConfig = {
    HERO: { label: 'Hero', color: '#8B6914', bg: '#FDF6E3', border: '#E8D5A0', icon: '⭐' },
    SUPPORT: { label: 'Support', color: '#3D6B8E', bg: '#EBF3F9', border: '#B5D0E3', icon: '️' },
    HARVEST: { label: 'Harvest', color: '#9E7C1A', bg: '#FFF8E7', border: '#E8D49A', icon: '  ' },
    KILL: { label: 'Kill', color: '#A63D2F', bg: '#FDEEEB', border: '#E5B5AD', icon: '  ' },
};
export function TierBadge({ tier }) {
    const config = tierConfig[tier] || tierConfig.SUPPORT;
    return (
        <span
            className="tier-badge"
            style={{
                backgroundColor: config.bg,
                color: config.color,
                border: `1px solid ${config.border}`,
                padding: '4px 12px',
                borderRadius: '4px',
                fontWeight: 'bold',
                fontSize: '14px',
            }}
        >
            {config.icon} {config.label}
        </span>
    );
}
