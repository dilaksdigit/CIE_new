import React from 'react';

// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §1.5
// SOURCE: CIE_v232_Writer_View.jsx C object
const C = {
  bg: "#FAFAF8",
  surface: "#FFFFFF",
  muted: "#F5F4F1",
  border: "#E5E3DE",
  text: "#2D2B28",
  textMid: "#6B6860",
  textLight: "#9B978F",
  accent: "#5B7A3A",
  accentLight: "#EEF2E8",
  accentBorder: "#C5D4B0",
  hero: "#8B6914",
  heroBg: "#FDF6E3",
  heroBorder: "#E8D5A0",
  support: "#3D6B8E",
  supportBg: "#EBF3F9",
  supportBorder: "#B5D0E3",
  harvest: "#9E7C1A",
  harvestBg: "#FFF8E7",
  harvestBorder: "#E8D49A",
  kill: "#A63D2F",
  killBg: "#FDEEEB",
  killBorder: "#E5B5AD",
  green: "#2E7D32",
  greenBg: "#E8F5E9",
  greenBorder: "#A5D6A7",
  red: "#C62828",
  redBg: "#FFEBEE",
  redBorder: "#EF9A9A",
  amber: "#E65100",
  amberBg: "#FFFDE7",
  amberBorder: "#FFCC80",
  blue: "#1565C0",
  blueBg: "#E3F2FD",
  blueBorder: "#90CAF9",
};

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
                                borderRadius: 3, border: `1px solid ${C.amberBorder}`, fontWeight: 600
                            }}>{op.count}</span>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default BulkOps;
