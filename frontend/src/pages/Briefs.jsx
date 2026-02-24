import React from 'react';
import { TierBadge } from '../components/common/UIComponents';

const Briefs = () => {
    const briefItems = [
        { label: "Missing Fields", content: "Title (G2), Tier Fields (G6.1), Authority (G7)" },
        { label: "Effort Cap", content: "45 minutes (Support tier)" },
        { label: "Intent Focus", content: "Compatibility — address fitting types and wattage equivalence" },
        { label: "Comparison Anchors", content: "vs Philips LED E27 8W, vs OSRAM E27 8.5W" },
        { label: "FAQ Template", content: "3 questions from golden query set pre-populated" },
        { label: "Gold-Set Example", content: "See CBL-BLK-3C-3M for reference structure" },
    ];

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Content Brief Generator</h1>
                <div className="page-subtitle">Auto-generated briefs based on tier, cluster intent, and missing fields</div>
            </div>

            <div className="card">
                <div className="flex items-center gap-12 mb-16">
                    <TierBadge tier="support" size="md" />
                    <span style={{ fontSize: "0.9rem", fontWeight: 700, color: "var(--text)", fontFamily: "var(--mono)" }}>BLB-LED-E27-8W</span>
                    <span style={{ fontSize: "0.7rem", color: "var(--text-muted)" }}>LED E27 Bulb 8W Warm</span>
                </div>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                    {briefItems.map(item => (
                        <div key={item.label} style={{ padding: 12, background: "var(--surface-alt)", borderRadius: 4, border: "1px solid var(--border)" }}>
                            <div style={{ fontSize: "0.58rem", color: "var(--text-dim)", textTransform: "uppercase", letterSpacing: "0.06em", marginBottom: 4, fontWeight: 700 }}>{item.label}</div>
                            <div style={{ fontSize: "0.75rem", color: "var(--text)" }}>{item.content}</div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default Briefs;
