import { useState } from "react";

// ═══════════════════════════════════════════════════════════════
// CIE v2.3.2 — SIMPLIFIED CONTENT WRITER VIEW
// One person. One queue. AI tells you what to write and why.
// ═══════════════════════════════════════════════════════════════

const C = {
  bg: "#FAFAF8", surface: "#FFFFFF", muted: "#F5F4F1", border: "#E5E3DE",
  text: "#2D2B28", textMid: "#6B6860", textLight: "#9B978F",
  accent: "#5B7A3A", accentLight: "#EEF2E8", accentBorder: "#C5D4B0",
  hero: "#8B6914", heroBg: "#FDF6E3", heroBorder: "#E8D5A0",
  support: "#3D6B8E", supportBg: "#EBF3F9", supportBorder: "#B5D0E3",
  harvest: "#9E7C1A", harvestBg: "#FFF8E7", harvestBorder: "#E8D49A",
  kill: "#A63D2F", killBg: "#FDEEEB", killBorder: "#E5B5AD",
  green: "#2E7D32", greenBg: "#E8F5E9", greenBorder: "#A5D6A7",
  red: "#C62828", redBg: "#FFEBEE", redBorder: "#EF9A9A",
  amber: "#E65100", amberBg: "#FFFDE7", amberBorder: "#FFCC80",
  blue: "#1565C0", blueBg: "#E3F2FD", blueBorder: "#90CAF9",
};

const tierInfo = {
  hero: { label: "HERO", color: C.hero, bg: C.heroBg, border: C.heroBorder, time: "90 min", desc: "Top earner. Give it your best work." },
  support: { label: "SUPPORT", color: C.support, bg: C.supportBg, border: C.supportBorder, time: "45 min", desc: "SUPPORT SKU — Focused Coverage. This product supports revenue but does not lead. Primary intent + max 2 secondary intents enabled. Answer Block and Best-For/Not-For required. Max 2 hours per quarter. Guide: ~45 min" },
  harvest: { label: "HARVEST", color: C.harvest, bg: C.harvestBg, border: C.harvestBorder, time: "10 min", desc: "Basic info only. One field to fill." },
  kill: { label: "KILL", color: C.kill, bg: C.killBg, border: C.killBorder, time: "0 min", desc: "Being removed from sale. Do nothing." },
};

// ─── MOCK DATA ──────────────────────────────────────
const QUEUE = [
  {
    id: "CBL-BLK-3C-3M", name: "Black 3-Core Cable 3m", tier: "hero", category: "Cables",
    status: "needs_work", fieldsTotal: 6, fieldsDone: 2, urgency: "high",
    reason: "AI citation dropped 3 weeks in a row. Refresh needed.",
    margin: "42.3%", velocity: "94/mo", score: 87.4,
    aiSuggestions: [
      {
        type: "keyword", source: "Semrush", sourceDate: "Last 15 days",
        title: "Add 'E27 pendant cable' to your title",
        detail: "Semrush shows 'E27 pendant cable' gets 2,400 searches/month — up 18% this month. Your title says 'Black 3-Core Cable' which gets 340 searches/month. The high-volume phrase should come first, before the pipe separator.",
        priority: "high",
      },
      {
        type: "citation", source: "AI Audit", sourceDate: "Last Monday",
        title: "ChatGPT stopped recommending this product",
        detail: "When we asked ChatGPT 'What cable do I need for a pendant light?', it recommended Competitor X because their description says 'compatible with E27, B22, and GU10 fittings' in the first sentence. Your Answer Block buries fitting types in the third sentence. Move them to the opening line.",
        priority: "high",
      },
      {
        type: "trend", source: "Google Analytics", sourceDate: "Last 30 days",
        title: "'3-core braided cable' traffic is surging",
        detail: "Google Analytics shows landing page visits for '3-core braided cable' queries grew 45% in 30 days. You sell this exact product but the word 'braided' doesn't appear anywhere in your content. Add it to your title and Answer Block.",
        priority: "medium",
      },
      {
        type: "competitor", source: "Semrush", sourceDate: "Last 15 days",
        title: "Competitor X ranks for 'BS 6500 certified cable' — you don't",
        detail: "Your product IS BS 6500 certified but this only appears in the Authority block. Semrush shows Competitor X mentions it in their title and gets 890 clicks/month from it. Add the certification to your Answer Block.",
        priority: "medium",
      },
    ],
    fields: {
      title: { value: "Black 3-Core Cable for Pendant Lights", status: "warning", hint: "Add 'E27 pendant cable' — 2,400 searches/month (Semrush)" },
      answerBlock: { value: "This black 3-core cable works with pendant lights, wall lights, and table lamps. The 3-metre length suits most ceiling installations.", status: "fail", hint: "Too short (148 chars). Needs 250-300. Add fitting types (E27, B22, GU10) and BS 6500 certification." },
      bestFor: { value: "", status: "empty", hint: "Who should buy this? (minimum 2 entries)" },
      notFor: { value: "", status: "empty", hint: "Who should NOT buy this? (minimum 1 entry)" },
      authority: { value: "BS 6500 certified. BASEC verified.", status: "pass", hint: "" },
      intent: { value: "Compatibility", status: "pass", hint: "" },
    }
  },
  {
    id: "PND-BRS-IND-L", name: "Brass Industrial Pendant Large", tier: "hero", category: "Pendants",
    status: "needs_work", fieldsTotal: 6, fieldsDone: 5, urgency: "medium",
    reason: "Missing Best-For / Not-For entries.",
    margin: "48.1%", velocity: "67/mo", score: 88.9,
    aiSuggestions: [
      {
        type: "keyword", source: "Semrush", sourceDate: "Last 15 days",
        title: "Add 'industrial kitchen pendant' to bullet points",
        detail: "Semrush shows 'industrial kitchen pendant' gets 3,100 searches/month. Your product matches this exactly but the word 'kitchen' doesn't appear in your content. Google Analytics confirms 38% of buyers for this product also browse kitchen lighting.",
        priority: "high",
      },
      {
        type: "trend", source: "Google Analytics", sourceDate: "Last 30 days",
        title: "People searching 'brass pendant over kitchen island'",
        detail: "This long-tail phrase grew 62% in 30 days on GA. It describes exactly what your product does. Use it in your Best-For section: 'Hanging over kitchen islands and dining tables.'",
        priority: "medium",
      },
    ],
    fields: {
      title: { value: "Brass Industrial Pendant Light — Large Ceiling Fixture for Kitchen & Dining | Vintage Edison Style", status: "pass", hint: "" },
      answerBlock: { value: "This large brass pendant light brings industrial character to kitchens, dining rooms, and hallways. Compatible with E27 bulbs up to 60W. Hangs from standard ceiling hooks with the included 1.5m adjustable chain. Finished in hand-brushed antique brass with a 30cm dome shade.", status: "pass", hint: "" },
      bestFor: { value: "", status: "empty", hint: "Who should buy this? (minimum 2 entries)" },
      notFor: { value: "", status: "empty", hint: "Who should NOT buy this? (minimum 1 entry)" },
      authority: { value: "IP20 rated for indoor use. Compatible with UK standard ceiling roses. Tested to BS EN 60598.", status: "pass", hint: "" },
      intent: { value: "Inspiration", status: "pass", hint: "" },
    }
  },
  {
    id: "LMP-OPL-DRM-M", name: "Opal Drum Lampshade Medium", tier: "support", category: "Lampshades",
    status: "needs_work", fieldsTotal: 5, fieldsDone: 3, urgency: "medium",
    reason: "Answer Block too short. Missing Not-For.",
    margin: "36.7%", velocity: "42/mo", score: 71.2,
    aiSuggestions: [
      {
        type: "keyword", source: "Semrush", sourceDate: "Last 15 days",
        title: "Use 'drum lampshade for ceiling' in title",
        detail: "Semrush shows 'drum lampshade for ceiling' gets 1,800 searches/month. You currently have 'Opal Drum Lampshade Medium' which matches no high-volume search phrases.",
        priority: "high",
      },
    ],
    fields: {
      title: { value: "Opal Drum Lampshade Medium", status: "warning", hint: "Too generic. Add use case: 'for ceiling' or 'for pendant'" },
      answerBlock: { value: "Medium opal drum lampshade.", status: "fail", hint: "Way too short (28 chars). Needs 250-300." },
      bestFor: { value: "Living rooms and bedrooms where soft diffused light is needed.", status: "pass", hint: "" },
      notFor: { value: "", status: "empty", hint: "Who should NOT buy this? (minimum 1 entry)" },
      intent: { value: "Comparison", status: "pass", hint: "" },
    }
  },
  {
    id: "BLB-LED-E27-8W", name: "LED E27 Bulb 8W Warm", tier: "support", category: "Bulbs",
    status: "needs_work", fieldsTotal: 5, fieldsDone: 1, urgency: "low",
    reason: "New product. Needs initial content.",
    margin: "31.2%", velocity: "128/mo", score: 68.4,
    aiSuggestions: [
      {
        type: "keyword", source: "Semrush", sourceDate: "Last 15 days",
        title: "'LED bulb warm white E27' — 5,200 searches/month",
        detail: "This is the highest volume phrase in the LED bulb category. Make it your title opener.",
        priority: "high",
      },
    ],
    fields: {
      title: { value: "", status: "empty", hint: "Start with the intent phrase, then product details" },
      answerBlock: { value: "", status: "empty", hint: "250-300 chars. Address what fittings it works with." },
      bestFor: { value: "", status: "empty", hint: "Who should buy this?" },
      notFor: { value: "", status: "empty", hint: "Who should NOT buy this?" },
      intent: { value: "Compatibility", status: "pass", hint: "" },
    }
  },
  {
    id: "CBL-GRY-3C-1M", name: "Grey 3-Core Cable 1m", tier: "harvest", category: "Cables",
    status: "needs_work", fieldsTotal: 1, fieldsDone: 0, urgency: "low",
    reason: "Missing basic specification.",
    margin: "18.4%", velocity: "12/mo", score: 42.1,
    aiSuggestions: [],
    fields: {
      specification: { value: "", status: "empty", hint: "Basic spec only — length, core count, colour, rating." },
    }
  },
  {
    id: "CBL-WHT-2C-5M", name: "White 2-Core Cable 5m", tier: "hero", category: "Cables",
    status: "done", fieldsTotal: 6, fieldsDone: 6, urgency: "none",
    reason: "",
    margin: "39.8%", velocity: "78/mo", score: 82.1,
    aiSuggestions: [],
    fields: {}
  },
  {
    id: "CBL-RED-2C-2M", name: "Red 2-Core Cable 2m", tier: "kill", category: "Cables",
    status: "locked", fieldsTotal: 0, fieldsDone: 0, urgency: "none",
    reason: "Negative margin. Scheduled for delisting 28 Feb.",
    margin: "-2.1%", velocity: "3/mo", score: 8.2,
    aiSuggestions: [],
    fields: {}
  },
];

const DONE_STATS = { today: 3, thisWeek: 14, heroTime: "68%", avgTime: "52 min" };

// ─── COMPONENTS ─────────────────────────────────────

const TierTag = ({ tier }) => {
  const t = tierInfo[tier];
  return (
    <span style={{
      display: "inline-flex", padding: "3px 10px", borderRadius: 3,
      background: t.bg, border: `1px solid ${t.border}`, color: t.color,
      fontSize: "0.62rem", fontWeight: 700, letterSpacing: "0.05em",
    }}>{t.label}</span>
  );
};

const StatusDot = ({ status }) => {
  const map = {
    pass: { color: C.green, bg: C.greenBg },
    warning: { color: C.amber, bg: C.amberBg },
    fail: { color: C.red, bg: C.redBg },
    empty: { color: C.textLight, bg: C.muted },
  };
  const s = map[status] || map.empty;
  return <div style={{ width: 8, height: 8, borderRadius: "50%", background: s.color, flexShrink: 0 }} />;
};

const FieldProgress = ({ done, total }) => {
  if (total === 0) return <span style={{ fontSize: "0.65rem", color: C.textLight }}>—</span>;
  const pct = Math.round((done / total) * 100);
  const color = pct === 100 ? C.green : pct >= 50 ? C.amber : C.red;
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
      <div style={{ width: 50, height: 5, background: "#E8E8E4", borderRadius: 3, overflow: "hidden" }}>
        <div style={{ width: `${pct}%`, height: "100%", background: color, borderRadius: 3 }} />
      </div>
      <span style={{ fontSize: "0.65rem", color: C.textMid, fontWeight: 600 }}>{done}/{total}</span>
    </div>
  );
};

const SuggestionCard = ({ s }) => {
  const iconMap = {
    keyword: { icon: "🔍", label: "Keyword Opportunity" },
    citation: { icon: "🤖", label: "AI Visibility Issue" },
    trend: { icon: "📈", label: "Trending Search" },
    competitor: { icon: "⚔️", label: "Competitor Gap" },
  };
  const info = iconMap[s.type] || iconMap.keyword;
  const prioColor = s.priority === "high" ? C.red : C.amber;
  const prioBg = s.priority === "high" ? C.redBg : C.amberBg;
  const prioBorder = s.priority === "high" ? C.redBorder : C.amberBorder;

  return (
    <div style={{
      background: C.surface, border: `1px solid ${C.border}`, borderRadius: 6,
      padding: 16, marginBottom: 10,
      borderLeft: `4px solid ${prioColor}`,
    }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
        <span style={{ fontSize: "1rem" }}>{info.icon}</span>
        <span style={{ fontSize: "0.68rem", color: C.textLight, fontWeight: 600, textTransform: "uppercase", letterSpacing: "0.04em" }}>{info.label}</span>
        <span style={{
          marginLeft: "auto", fontSize: "0.58rem", padding: "2px 7px", borderRadius: 3,
          background: prioBg, color: prioColor, border: `1px solid ${prioBorder}`, fontWeight: 700,
        }}>{s.priority.toUpperCase()}</span>
      </div>
      <div style={{ fontSize: "0.82rem", fontWeight: 700, color: C.text, marginBottom: 6, lineHeight: 1.3 }}>{s.title}</div>
      <div style={{ fontSize: "0.74rem", color: C.textMid, lineHeight: 1.55 }}>{s.detail}</div>
      <div style={{
        marginTop: 10, paddingTop: 8, borderTop: `1px solid ${C.border}`,
        display: "flex", alignItems: "center", gap: 6,
      }}>
        <span style={{ fontSize: "0.6rem", color: C.textLight }}>Source:</span>
        <span style={{
          fontSize: "0.6rem", padding: "1px 6px", borderRadius: 3,
          background: C.blueBg, color: C.blue, border: `1px solid ${C.blueBorder}`, fontWeight: 600,
        }}>{s.source}</span>
        <span style={{ fontSize: "0.6rem", color: C.textLight }}>({s.sourceDate})</span>
      </div>
    </div>
  );
};

// ─── SCREENS ────────────────────────────────────────

const QueueScreen = ({ items, onSelect }) => {
  const [filter, setFilter] = useState("all");
  const needsWork = items.filter(i => i.status === "needs_work");
  const done = items.filter(i => i.status === "done");
  const locked = items.filter(i => i.status === "locked");

  const filtered = filter === "all" ? items.filter(i => i.status !== "locked" && i.status !== "done")
    : filter === "done" ? done
      : filter === "locked" ? locked
        : needsWork.filter(i => i.tier === filter);

  return (
    <div>
      {/* Top Stats */}
      <div style={{ display: "flex", gap: 10, marginBottom: 20, flexWrap: "wrap" }}>
        {[
          { label: "To Do", value: needsWork.length, color: C.amber },
          { label: "Done Today", value: DONE_STATS.today, color: C.green },
          { label: "Done This Week", value: DONE_STATS.thisWeek, color: C.accent },
          { label: "Hero Time", value: DONE_STATS.heroTime, color: C.hero, sub: "Target: 60%" },
        ].map(s => (
          <div key={s.label} style={{
            flex: 1, minWidth: 120, background: C.surface, border: `1px solid ${C.border}`,
            borderRadius: 6, padding: "12px 16px", boxShadow: "0 1px 2px rgba(0,0,0,0.03)",
          }}>
            <div style={{ fontSize: "0.6rem", color: C.textLight, textTransform: "uppercase", letterSpacing: "0.08em", fontWeight: 700, marginBottom: 4 }}>{s.label}</div>
            <div style={{ fontSize: "1.3rem", fontWeight: 700, color: s.color }}>{s.value}</div>
            {s.sub && <div style={{ fontSize: "0.58rem", color: C.textLight, marginTop: 2 }}>{s.sub}</div>}
          </div>
        ))}
      </div>

      {/* Filter Tabs */}
      <div style={{ display: "flex", gap: 6, marginBottom: 16, flexWrap: "wrap" }}>
        {[
          { id: "all", label: `All To Do (${needsWork.length})` },
          { id: "hero", label: "Heroes" },
          { id: "support", label: "Support" },
          { id: "harvest", label: "Harvest" },
          { id: "done", label: `Done (${done.length})` },
          { id: "locked", label: `Locked (${locked.length})` },
        ].map(f => (
          <button key={f.id} onClick={() => setFilter(f.id)} style={{
            padding: "6px 14px", borderRadius: 4, fontSize: "0.72rem", cursor: "pointer",
            background: filter === f.id ? C.accent : C.surface,
            color: filter === f.id ? "#fff" : C.textMid,
            border: `1px solid ${filter === f.id ? C.accent : C.border}`,
            fontWeight: filter === f.id ? 700 : 500,
          }}>{f.label}</button>
        ))}
      </div>

      {/* Queue List */}
      <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
        {filtered.length === 0 && (
          <div style={{ padding: 40, textAlign: "center", color: C.textLight, fontSize: "0.85rem" }}>
            {filter === "done" ? "Your completed products will show here." : filter === "locked" ? "Products scheduled for removal." : "Nothing here. Nice work!"}
          </div>
        )}
        {filtered.map(item => (
          <div key={item.id} onClick={() => item.status === "needs_work" && onSelect(item)}
            style={{
              background: C.surface, border: `1px solid ${C.border}`, borderRadius: 6,
              padding: "14px 18px", display: "flex", alignItems: "center", gap: 16,
              cursor: item.status === "needs_work" ? "pointer" : "default",
              opacity: item.status === "locked" ? 0.5 : 1,
              boxShadow: "0 1px 2px rgba(0,0,0,0.03)",
              borderLeft: item.urgency === "high" ? `4px solid ${C.red}` : item.urgency === "medium" ? `4px solid ${C.amber}` : `4px solid ${C.border}`,
            }}>
            <div style={{ flex: 1 }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 4 }}>
                <TierTag tier={item.tier} />
                <span style={{ fontSize: "0.82rem", fontWeight: 700, color: C.text }}>{item.name}</span>
                <span style={{ fontSize: "0.68rem", color: C.textLight }}>{item.id}</span>
              </div>
              {item.reason && (
                <div style={{ fontSize: "0.72rem", color: C.textMid, marginTop: 2 }}>{item.reason}</div>
              )}
              {item.aiSuggestions.length > 0 && (
                <div style={{ display: "flex", alignItems: "center", gap: 4, marginTop: 6 }}>
                  <span style={{ fontSize: "0.82rem" }}>💡</span>
                  <span style={{ fontSize: "0.65rem", color: C.accent, fontWeight: 600 }}>{item.aiSuggestions.length} suggestion{item.aiSuggestions.length > 1 ? "s" : ""} from Semrush & Analytics</span>
                </div>
              )}
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 16, flexShrink: 0 }}>
              {item.status === "needs_work" && <FieldProgress done={item.fieldsDone} total={item.fieldsTotal} />}
              {item.status === "done" && <span style={{ fontSize: "0.68rem", color: C.green, fontWeight: 700, padding: "3px 10px", background: C.greenBg, borderRadius: 3, border: `1px solid ${C.greenBorder}` }}>DONE</span>}
              {item.status === "locked" && <span style={{ fontSize: "0.68rem", color: C.red, fontWeight: 700, padding: "3px 10px", background: C.redBg, borderRadius: 3, border: `1px solid ${C.redBorder}` }}>LOCKED</span>}
              {item.status === "needs_work" && <span style={{ fontSize: "0.85rem", color: C.textLight }}>→</span>}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

const EditScreen = ({ item, onBack }) => {
  const t = tierInfo[item.tier];
  const fieldEntries = Object.entries(item.fields);
  const fieldLabels = {
    title: "Product Title", answerBlock: "Answer Block", bestFor: "Best For (who should buy this)",
    notFor: "Not For (who should NOT buy this)", authority: "Expert Authority (certifications & proof)",
    intent: "Main Customer Reason", specification: "Basic Specification",
  };
  const passCount = fieldEntries.filter(([, v]) => v.status === "pass").length;
  const allPass = passCount === fieldEntries.length;

  return (
    <div>
      {/* Back button */}
      <button onClick={onBack} style={{
        background: "none", border: "none", color: C.accent, fontSize: "0.78rem",
        cursor: "pointer", padding: "0 0 12px 0", fontWeight: 600, display: "flex", alignItems: "center", gap: 4,
      }}>← Back to queue</button>

      {/* Tier Banner */}
      <div style={{
        background: t.bg, border: `1px solid ${t.border}`, borderRadius: 6,
        padding: "14px 20px", marginBottom: 16, display: "flex", justifyContent: "space-between", alignItems: "center",
      }}>
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 4 }}>
            <TierTag tier={item.tier} />
            <span style={{ fontSize: "1rem", fontWeight: 700, color: C.text }}>{item.name}</span>
            <span style={{ fontSize: "0.72rem", color: C.textLight }}>{item.id}</span>
          </div>
          <div style={{ fontSize: "0.75rem", color: t.color, fontWeight: 500 }}>
            {t.desc} <span style={{ fontWeight: 700 }}>Time budget: {t.time}</span>
          </div>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <div style={{ textAlign: "right" }}>
            <div style={{ fontSize: "0.58rem", color: C.textLight, textTransform: "uppercase", letterSpacing: "0.06em" }}>Margin</div>
            <div style={{ fontSize: "0.85rem", fontWeight: 700, color: C.text }}>{item.margin}</div>
          </div>
          <div style={{ width: 1, height: 30, background: t.border }} />
          <div style={{ textAlign: "right" }}>
            <div style={{ fontSize: "0.58rem", color: C.textLight, textTransform: "uppercase", letterSpacing: "0.06em" }}>Sales</div>
            <div style={{ fontSize: "0.85rem", fontWeight: 700, color: C.text }}>{item.velocity}</div>
          </div>
          <div style={{ width: 1, height: 30, background: t.border }} />
          <div style={{ textAlign: "right" }}>
            <div style={{ fontSize: "0.58rem", color: C.textLight, textTransform: "uppercase", letterSpacing: "0.06em" }}>Priority Score</div>
            <div style={{ fontSize: "0.85rem", fontWeight: 700, color: t.color }}>{item.score}</div>
          </div>
        </div>
      </div>

      <div style={{ display: "flex", gap: 16, alignItems: "flex-start" }}>
        {/* Left — Fields */}
        <div style={{ flex: 1 }}>
          {/* Progress bar */}
          <div style={{
            background: C.surface, border: `1px solid ${C.border}`, borderRadius: 6,
            padding: "12px 18px", marginBottom: 14, display: "flex", alignItems: "center", gap: 12,
            boxShadow: "0 1px 2px rgba(0,0,0,0.03)",
          }}>
            <span style={{ fontSize: "0.72rem", fontWeight: 600, color: C.textMid }}>Progress:</span>
            <div style={{ flex: 1, height: 8, background: "#E8E8E4", borderRadius: 4, overflow: "hidden" }}>
              <div style={{
                width: `${(passCount / fieldEntries.length) * 100}%`, height: "100%", borderRadius: 4,
                background: allPass ? C.green : C.amber, transition: "width 0.3s ease",
              }} />
            </div>
            <span style={{ fontSize: "0.72rem", fontWeight: 700, color: allPass ? C.green : C.textMid }}>{passCount}/{fieldEntries.length} done</span>
            {allPass && (
              <button style={{
                background: C.green, color: "#fff", border: "none", borderRadius: 4,
                padding: "8px 20px", fontSize: "0.78rem", fontWeight: 700, cursor: "pointer",
              }}>Submit ✓</button>
            )}
          </div>

          {/* Fields */}
          {fieldEntries.map(([key, field]) => {
            const label = fieldLabels[key] || key;
            const statusColor = field.status === "pass" ? C.green : field.status === "warning" ? C.amber : field.status === "fail" ? C.red : C.textLight;
            const statusBorder = field.status === "pass" ? C.greenBorder : field.status === "warning" ? C.amberBorder : field.status === "fail" ? C.redBorder : C.border;
            const statusLabel = field.status === "pass" ? "✓ Good" : field.status === "warning" ? "⚠ Needs improvement" : field.status === "fail" ? "✗ Fix this" : "Empty — fill this in";
            const isLong = key === "answerBlock" || key === "authority" || key === "bestFor" || key === "notFor";

            return (
              <div key={key} style={{
                background: C.surface, border: `1px solid ${statusBorder}`, borderRadius: 6,
                padding: 16, marginBottom: 10, boxShadow: "0 1px 2px rgba(0,0,0,0.03)",
                borderLeft: `4px solid ${statusColor}`,
              }}>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 8 }}>
                  <span style={{ fontSize: "0.78rem", fontWeight: 700, color: C.text }}>{label}</span>
                  <span style={{ fontSize: "0.62rem", fontWeight: 700, color: statusColor }}>{statusLabel}</span>
                </div>
                {key === "intent" ? (
                  <div style={{
                    padding: "10px 14px", background: C.muted, borderRadius: 4, fontSize: "0.8rem", color: C.text,
                  }}>
                    {field.value || "Not set"} <span style={{ fontSize: "0.62rem", color: C.textLight }}>(Set by system based on search data)</span>
                  </div>
                ) : isLong ? (
                  <textarea rows={3} defaultValue={field.value} placeholder={field.hint} style={{
                    width: "100%", background: C.muted, border: `1px solid ${C.border}`, borderRadius: 4,
                    padding: "10px 14px", color: C.text, fontSize: "0.8rem", resize: "vertical",
                    outline: "none", fontFamily: "inherit", boxSizing: "border-box",
                  }} />
                ) : (
                  <input defaultValue={field.value} placeholder={field.hint} style={{
                    width: "100%", background: C.muted, border: `1px solid ${C.border}`, borderRadius: 4,
                    padding: "10px 14px", color: C.text, fontSize: "0.8rem", outline: "none",
                    boxSizing: "border-box",
                  }} />
                )}
                {field.hint && field.status !== "pass" && (
                  <div style={{ marginTop: 6, fontSize: "0.7rem", color: statusColor, lineHeight: 1.4 }}>
                    💡 {field.hint}
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Right — AI Suggestions */}
        {item.aiSuggestions.length > 0 && (
          <div style={{ width: 340, flexShrink: 0 }}>
            <div style={{
              background: C.accentLight, border: `1px solid ${C.accentBorder}`, borderRadius: 6,
              padding: "12px 16px", marginBottom: 12,
            }}>
              <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 4 }}>
                <span style={{ fontSize: "1rem" }}>🧠</span>
                <span style={{ fontSize: "0.82rem", fontWeight: 700, color: C.accent }}>AI Suggestions</span>
              </div>
              <div style={{ fontSize: "0.7rem", color: C.textMid, lineHeight: 1.4 }}>
                Based on real data from your Semrush reports and Google Analytics. Every suggestion includes its source and date so you can verify it.
              </div>
            </div>
            {item.aiSuggestions.map((s, i) => <SuggestionCard key={i} s={s} />)}
          </div>
        )}
      </div>
    </div>
  );
};

// ═══════════════════════════════════════════════════════════════
// MAIN APP
// ═══════════════════════════════════════════════════════════════
export default function CIEWriter() {
  const [selected, setSelected] = useState(null);

  return (
    <div style={{
      minHeight: "100vh", background: C.bg,
      fontFamily: "'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif",
      color: C.text,
    }}>
      {/* Top Bar */}
      <div style={{
        background: C.surface, borderBottom: `1px solid ${C.border}`,
        padding: "10px 24px", display: "flex", alignItems: "center", justifyContent: "space-between",
        boxShadow: "0 1px 2px rgba(0,0,0,0.04)",
      }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
          <div style={{
            width: 30, height: 30, borderRadius: 4, background: C.accent,
            display: "flex", alignItems: "center", justifyContent: "center",
            fontSize: "0.78rem", fontWeight: 800, color: "#fff",
          }}>C</div>
          <div>
            <span style={{ fontSize: "0.88rem", fontWeight: 800, color: C.text }}>CIE</span>
            <span style={{ fontSize: "0.65rem", color: C.textLight, marginLeft: 6 }}>Content Writer</span>
          </div>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <div style={{
            padding: "4px 10px", borderRadius: 4, background: C.greenBg,
            border: `1px solid ${C.greenBorder}`, fontSize: "0.65rem", color: C.green, fontWeight: 700,
          }}>
            Hero time: {DONE_STATS.heroTime}
          </div>
          <div style={{
            width: 30, height: 30, borderRadius: "50%", background: C.accentLight,
            display: "flex", alignItems: "center", justifyContent: "center",
            fontSize: "0.62rem", fontWeight: 700, color: C.accent,
          }}>CW</div>
        </div>
      </div>

      {/* Content */}
      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "20px 24px" }}>
        {!selected && (
          <div style={{ marginBottom: 18 }}>
            <h1 style={{ fontSize: "1.15rem", fontWeight: 800, color: C.text, margin: "0 0 4px 0", textTransform: "uppercase", letterSpacing: "0.02em" }}>
              My Product Queue
            </h1>
            <div style={{ fontSize: "0.72rem", color: C.textLight }}>
              Products sorted by priority. Heroes first, then Support, then Harvest. AI suggestions included where available.
            </div>
          </div>
        )}
        {selected ? (
          <EditScreen item={selected} onBack={() => setSelected(null)} />
        ) : (
          <QueueScreen items={QUEUE} onSelect={setSelected} />
        )}
      </div>
    </div>
  );
}
