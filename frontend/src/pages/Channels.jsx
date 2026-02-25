import React from 'react';
import {
    StatCard,
    SectionTitle,
    TrafficLight
} from '../components/common/UIComponents';

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

const Channels = () => {
    const channelStats = [
        { ch: "Own Website", score: 78, compete: 186, skip: 72 },
        { ch: "Google Shopping", score: 71, compete: 164, skip: 94 },
        { ch: "Amazon", score: 64, compete: 142, skip: 116 },
        { ch: "AI Assistants", score: 68, compete: 158, skip: 100 },
    ];

    const rules = [
        { rule: "Hero SKUs must score ≥85% to be included in channel feeds", status: "ENFORCED" },
        { rule: "Support SKUs must score ≥70% for Google Shopping and Amazon", status: "ENFORCED" },
        { rule: "Harvest SKUs are excluded from paid channels (Shopping/Amazon)", status: "ENFORCED" },
        { rule: "Kill SKUs are excluded from ALL channel feeds", status: "ENFORCED" },
        { rule: "AI Assistants channel uses JSON-LD — no feed push required", status: "PASSIVE" },
        { rule: "Google Shopping feed regenerates nightly at 02:00 UTC", status: "SCHEDULED" },
    ];

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Channel Governor</h1>
                <div className="page-subtitle">Portfolio-level channel readiness — COMPETE/SKIP decisions</div>
            </div>

            <div className="flex gap-14 mb-18 flex-wrap">
                {channelStats.map(ch => (
                    <div key={ch.ch} className="card" style={{ flex: 1, minWidth: 200 }}>
                        <div style={{ fontSize: "0.75rem", fontWeight: 700, color: "var(--text)", marginBottom: 12 }}>{ch.ch}</div>
                        <div style={{ fontSize: "1.8rem", fontWeight: 800, color: "var(--accent)", fontFamily: "var(--mono)" }}>{ch.score}%</div>
                        <div style={{ fontSize: "0.58rem", color: "var(--text-dim)", marginTop: 4 }}>avg readiness</div>
                        <div className="flex gap-8" style={{ marginTop: 12 }}>
                            <span style={{ fontSize: "0.58rem", padding: "2px 6px", background: "var(--green-bg)", color: "var(--green)", borderRadius: 3, border: `1px solid ${C.greenBorder}` }}>COMPETE: {ch.compete}</span>
                            <span style={{ fontSize: "0.58rem", padding: "2px 6px", background: "var(--red-bg)", color: "var(--red)", borderRadius: 3, border: `1px solid ${C.redBorder}` }}>SKIP: {ch.skip}</span>
                        </div>
                    </div>
                ))}
            </div>

            <div className="card">
                <SectionTitle sub="Hero ≥85 to compete, Support ≥70, Harvest/Kill excluded">Channel Eligibility Rules</SectionTitle>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                    {rules.map((r, i) => (
                        <div key={i} className="flex justify-between items-start gap-8" style={{
                            padding: 12, background: "var(--surface-alt)", borderRadius: 4,
                            border: "1px solid var(--border)", fontSize: "0.7rem", color: "var(--text-muted)",
                        }}>
                            <span>{r.rule}</span>
                            <span style={{
                                flexShrink: 0, padding: "2px 6px", borderRadius: 3, fontSize: "0.55rem", fontWeight: 700,
                                background: r.status === "ENFORCED" ? "var(--green-bg)" : "var(--orange-bg)",
                                color: r.status === "ENFORCED" ? "var(--green)" : "var(--orange)",
                                border: `1px solid ${r.status === "ENFORCED" ? C.greenBorder : C.amberBorder}`,
                            }}>{r.status}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default Channels;
