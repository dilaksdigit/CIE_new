// SOURCE: CLAUDE.md Section 8 — Core Colours (locked palette)
//         CIE_v232_Developer_Amendment_Pack_v2.docx Section 9 Phase 1

export const THEME = {
  // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §5
  // FIX: UI-01/UI-02 — exact light palette and harvest color.
  // ── Backgrounds ────────────────────────────────────────────────
  bg:            "#FAFAF8",
  surface:       "#FFFFFF",   // white — cards, panels
  muted:         "#F5F4F1",

  // ── Borders ────────────────────────────────────────────────────
  border:        "#E5E3DE",

  // ── Text ───────────────────────────────────────────────────────
  text:          "#2D2B28",
  textMid:       "#6B6860",
  textLight:     "#9B978F",

  // ── Accent ─────────────────────────────────────────────────────
  accent:        "#5B7A3A",   // olive green — primary action
  accentLight:   "#EEF2E8",
  accentBorder:  "#C5D4B0",

  // ── Tier: Hero ─────────────────────────────────────────────────
  hero:          "#8B6914",   // gold
  heroBg:        "#FDF6E3",
  heroBorder:    "#E8D5A0",

  // ── Tier: Support ──────────────────────────────────────────────
  support:       "#3D6B8E",   // steel blue
  supportBg:     "#EBF3F9",
  supportBorder: "#B5D0E3",

  // ── Tier: Harvest ──────────────────────────────────────────────
  harvest:       "#9E7C1A",
  harvestBg:     "#FFF8E7",
  harvestBorder: "#E8D49A",

  // ── Tier: Kill ─────────────────────────────────────────────────
  kill:          "#A63D2F",   // muted red
  killBg:        "#FDEEEB",
  killBorder:    "#E5B5AD",

  // ── Status: Pass / Success ─────────────────────────────────────
  green:         "#2E7D32",
  greenBg:       "#E8F5E9",
  greenBorder:   "#A5D6A7",

  // ── Status: Fail / Error ───────────────────────────────────────
  red:           "#C62828",
  redBg:         "#FFEBEE",
  redBorder:     "#EF9A9A",

  // ── Status: Warning ────────────────────────────────────────────
  amber:         "#E65100",
  amberBg:       "#FFFDE7",
  amberBorder:   "#FFCC80",

  // ── Informational ──────────────────────────────────────────────
  blue:          "#1565C0",
  blueBg:        "#E3F2FD",
  blueBorder:    "#90CAF9",

  // ── Bulk Ops layout (single source; no magic numbers in page) ───
  bulkOps: {
    gridMinPx:    280,
    gapPx:        12,
    iconRem:      1.2,
    titleRem:     0.8,
    descRem:      0.65,
    badgeRem:     0.58,
    badgePadding: '2px 6px',
    badgeRadius:  3,
  },
};

export default THEME;

