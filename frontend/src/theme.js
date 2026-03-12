// SOURCE: CLAUDE.md Section 8 — Core Colours (locked palette)
//         CIE_v232_Developer_Amendment_Pack_v2.docx Section 9 Phase 1

export const THEME = {
  // ── Backgrounds ────────────────────────────────────────────────
  bg:            "#FAFAFA",   // CORRECTED was #FAFAF8 — page background
  surface:       "#FFFFFF",   // white — cards, panels
  muted:         "#F5F5F4",   // CORRECTED was #F5F4F1 — surfaceAlt / alt backgrounds

  // ── Borders ────────────────────────────────────────────────────
  border:        "#E5E5E5",   // CORRECTED was #E5E3DE

  // ── Text ───────────────────────────────────────────────────────
  text:          "#2D2D2D",   // CORRECTED was #2D2B28 — primary text
  textMid:       "#6B6B6B",   // CORRECTED was #6B6860 — secondary text (textMuted)
  textLight:     "#9B978F",   // muted grey — light/placeholder text

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
  harvest:       "#B8860B",   // CORRECTED was #9E7C1A — Harvest badge text colour
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
};

export default THEME;

