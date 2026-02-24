import React from 'react';

const BulkOps = () => {
    const ops = [
        { op: "Bulk Tier Reassignment", desc: "Apply ERP sync tier changes to multiple SKUs", icon: "▦", count: "12 pending" },
        { op: "Bulk Cluster Assignment", desc: "Move SKUs between semantic clusters", icon: "⬡", count: "—" },
        { op: "Bulk Status Change", desc: "Draft → Active, Active → Archived", icon: "⊞", count: "—" },
        { op: "FAQ Template Application", desc: "Apply FAQ templates to category SKUs", icon: "📋", count: "34 pending" },
        { op: "CSV Import", desc: "Import SKU data from spreadsheet", icon: "↓", count: "—" },
        { op: "CSV Export", desc: "Export current SKU data for analysis", icon: "↑", count: "—" },
    ];

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Bulk Operations</h1>
                <div className="page-subtitle">Admin only — mass updates with preview and confirmation. Max 500 SKUs per operation.</div>
            </div>

            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))", gap: 12 }}>
                {ops.map(op => (
                    <div key={op.op} className="card" style={{ cursor: "pointer", display: 'flex', flexDirection: 'column' }}>
                        <div style={{ fontSize: "1.2rem", marginBottom: 8 }}>{op.icon}</div>
                        <div style={{ fontSize: "0.8rem", fontWeight: 700, color: "var(--text)", marginBottom: 4 }}>{op.op}</div>
                        <div style={{ fontSize: "0.65rem", color: "var(--text-muted)", marginBottom: 12, flex: 1 }}>{op.desc}</div>
                        {op.count !== "—" && (
                            <span style={{
                                display: 'inline-block',
                                alignSelf: 'flex-start',
                                fontSize: "0.58rem", padding: "2px 6px",
                                background: "var(--orange-bg)", color: "var(--orange)",
                                borderRadius: 3, border: "1px solid #FFE082", fontWeight: 600
                            }}>{op.count}</span>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default BulkOps;
