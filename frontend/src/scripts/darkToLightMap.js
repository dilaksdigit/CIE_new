// SOURCE: CIE_v232_UI_Restructure_Instructions.docx — Section 1.5
// SOURCE: CIE_v232_Writer_View.jsx — const C block

/**
 * darkToLightMap
 *
 * Keys   = old dark-theme hex values (lowercase).
 * Values = { replacement: string, token: string }
 *
 * RULE: If a hex is found in source files but NOT in this map,
 * the script must print: UNMAPPED [hex] in [file]:[line] — manual review required.
 * It must NOT replace unmapped values automatically.
 */
const darkToLightMap = {

  // ── Common dark backgrounds ──────────────────────────────────────────
  "#0f0f0f": { replacement: "#FAFAF8", token: "bg" },
  "#111111": { replacement: "#FAFAF8", token: "bg" },
  "#121212": { replacement: "#FAFAF8", token: "bg" },
  "#141414": { replacement: "#FAFAF8", token: "bg" },
  "#1a1a1a": { replacement: "#FAFAF8", token: "bg" },
  "#1c1c1c": { replacement: "#FAFAF8", token: "bg" },
  "#1e1e1e": { replacement: "#FAFAF8", token: "bg" },
  "#212121": { replacement: "#FAFAF8", token: "bg" },
  "#222222": { replacement: "#FAFAF8", token: "bg" },
  "#242424": { replacement: "#FAFAF8", token: "bg" },
  "#252525": { replacement: "#FAFAF8", token: "bg" },
  "#262626": { replacement: "#FAFAF8", token: "bg" },
  "#272727": { replacement: "#FAFAF8", token: "bg" },
  "#282828": { replacement: "#FAFAF8", token: "bg" },
  "#2a2a2a": { replacement: "#FAFAF8", token: "bg" },
  "#2c2c2c": { replacement: "#FAFAF8", token: "bg" },
  "#2d2d2d": { replacement: "#FAFAF8", token: "bg" },
  "#303030": { replacement: "#FAFAF8", token: "bg" },
  "#333333": { replacement: "#FAFAF8", token: "bg" },
  "#363636": { replacement: "#FAFAF8", token: "bg" },
  "#383838": { replacement: "#FAFAF8", token: "bg" },
  "#3a3a3a": { replacement: "#FAFAF8", token: "bg" },
  "#3c3c3c": { replacement: "#FAFAF8", token: "bg" },
  "#404040": { replacement: "#FAFAF8", token: "bg" },

  // ── Common dark card / surface tones ─────────────────────────────────
  "#1f1f1f": { replacement: "#FFFFFF", token: "surface" },
  "#232323": { replacement: "#FFFFFF", token: "surface" },
  "#2b2b2b": { replacement: "#FFFFFF", token: "surface" },
  "#2e2e2e": { replacement: "#FFFFFF", token: "surface" },
  "#313131": { replacement: "#FFFFFF", token: "surface" },
  "#323232": { replacement: "#FFFFFF", token: "surface" },
  "#353535": { replacement: "#FFFFFF", token: "surface" },
  "#393939": { replacement: "#FFFFFF", token: "surface" },
  "#3b3b3b": { replacement: "#FFFFFF", token: "surface" },
  "#3e3e3e": { replacement: "#FFFFFF", token: "surface" },
  "#3f3f3f": { replacement: "#FFFFFF", token: "surface" },
  "#444444": { replacement: "#FFFFFF", token: "surface" },
  "#454545": { replacement: "#FFFFFF", token: "surface" },
  "#464646": { replacement: "#FFFFFF", token: "surface" },
  "#484848": { replacement: "#FFFFFF", token: "surface" },
  "#4a4a4a": { replacement: "#FFFFFF", token: "surface" },
  "#4d4d4d": { replacement: "#FFFFFF", token: "surface" },

  // ── Dark muted / secondary surface tones ─────────────────────────────
  "#2f2f2f": { replacement: "#F5F4F1", token: "muted" },
  "#343434": { replacement: "#F5F4F1", token: "muted" },
  "#3d3e3f": { replacement: "#F5F4F1", token: "muted" },
  "#404142": { replacement: "#F5F4F1", token: "muted" },
  "#424242": { replacement: "#F5F4F1", token: "muted" },
  "#4b4b4b": { replacement: "#F5F4F1", token: "muted" },
  "#4c4c4c": { replacement: "#F5F4F1", token: "muted" },
  "#4e4e4e": { replacement: "#F5F4F1", token: "muted" },
  "#4f4f4f": { replacement: "#F5F4F1", token: "muted" },
  "#505050": { replacement: "#F5F4F1", token: "muted" },

  // ── Dark borders ─────────────────────────────────────────────────────
  "#555555": { replacement: "#E5E3DE", token: "border" },
  "#585858": { replacement: "#E5E3DE", token: "border" },
  "#5a5a5a": { replacement: "#E5E3DE", token: "border" },
  "#5c5c5c": { replacement: "#E5E3DE", token: "border" },
  "#5f5f5f": { replacement: "#E5E3DE", token: "border" },
  "#606060": { replacement: "#E5E3DE", token: "border" },
  "#626262": { replacement: "#E5E3DE", token: "border" },
  "#636363": { replacement: "#E5E3DE", token: "border" },
  "#646464": { replacement: "#E5E3DE", token: "border" },
  "#666666": { replacement: "#E5E3DE", token: "border" },
  "#6a6a6a": { replacement: "#E5E3DE", token: "border" },

  // ── Dark text on dark bg → light-theme text ──────────────────────────
  // Light text on dark bg → becomes dark text on light bg
  "#f0f0f0": { replacement: "#2D2B28", token: "text" },
  "#efefef": { replacement: "#2D2B28", token: "text" },
  "#eeeeee": { replacement: "#2D2B28", token: "text" },
  "#e8e8e8": { replacement: "#2D2B28", token: "text" },
  "#e0e0e0": { replacement: "#2D2B28", token: "text" },
  "#dddddd": { replacement: "#2D2B28", token: "text" },
  "#d9d9d9": { replacement: "#2D2B28", token: "text" },
  "#cccccc": { replacement: "#6B6860", token: "textMid" },
  "#c0c0c0": { replacement: "#6B6860", token: "textMid" },
  "#bbbbbb": { replacement: "#6B6860", token: "textMid" },
  "#b0b0b0": { replacement: "#6B6860", token: "textMid" },
  "#aaaaaa": { replacement: "#9B978F", token: "textLight" },
  "#a0a0a0": { replacement: "#9B978F", token: "textLight" },
  "#999999": { replacement: "#9B978F", token: "textLight" },
  "#909090": { replacement: "#9B978F", token: "textLight" },
  "#888888": { replacement: "#9B978F", token: "textLight" },

  // ── CSS variable names (dark theme) ───────────────────────────────────
  // These are string-pattern matches, not hex. See PATTERN SCAN section below.
};

/**
 * CSS variable name mapping — old dark var name → new THEME token.
 * The script must also scan for these string patterns.
 */
const cssVarMap = {
  "--bg-dark":        { replacement: "var(--bg)",        token: "bg",        hex: "#FAFAF8" },
  "--surface-dark":   { replacement: "var(--surface)",   token: "surface",   hex: "#FFFFFF" },
  "--card-dark":      { replacement: "var(--surface)",   token: "surface",   hex: "#FFFFFF" },
  "--panel-dark":     { replacement: "var(--muted)",     token: "muted",     hex: "#F5F4F1" },
  "--border-dark":    { replacement: "var(--border)",    token: "border",    hex: "#E5E3DE" },
  "--text-light":     { replacement: "var(--text)",      token: "text",      hex: "#2D2B28" },
  "--text-dim":       { replacement: "var(--textMid)",   token: "textMid",   hex: "#6B6860" },
  "--text-muted":     { replacement: "var(--textLight)", token: "textLight", hex: "#9B978F" },
};

module.exports = { darkToLightMap, cssVarMap };

