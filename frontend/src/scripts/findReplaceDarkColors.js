// SOURCE: CIE_v232_UI_Restructure_Instructions.docx — Section 1.5
// SOURCE: CIE_v232_Writer_View.jsx — const C block

"use strict";

const fs   = require("fs");
const path = require("path");
const { darkToLightMap, cssVarMap } = require("./darkToLightMap");

// ── Config ────────────────────────────────────────────────────────────────
// Scan only inside frontend/src (this script lives in src/scripts).
const SRC_ROOT   = path.resolve(__dirname, "..");   // src/
const EXTENSIONS = new Set([".jsx", ".js", ".tsx", ".ts", ".css"]);
const EXCLUDE    = [
  path.resolve(__dirname),                 // src/scripts/ — exclude self
  path.resolve(SRC_ROOT, "theme.js"),      // src/theme.js — exclude generated file
];

// ── Helpers ───────────────────────────────────────────────────────────────

function normaliseHex(raw) {
  // Expand 3-digit shorthand to 6-digit
  let h = raw.toLowerCase();
  if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
  return "#" + h;
}

function isDarkBackground(hex) {
  // Returns true if hex falls in the known-dark range (#0f0f0f–#6a6a6a)
  const n = parseInt(hex.replace("#", ""), 16);
  const r = (n >> 16) & 0xff;
  const g = (n >>  8) & 0xff;
  const b =  n        & 0xff;
  // Luminance proxy: if all channels are below ~42% treat as dark background
  return r < 107 && g < 107 && b < 107;
}

function walk(dir, results = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === "node_modules") continue;
      walk(fullPath, results);
    } else if (EXTENSIONS.has(path.extname(entry.name))) {
      if (!EXCLUDE.some(ex => fullPath.startsWith(ex))) {
        results.push(fullPath);
      }
    }
  }
  return results;
}

// ── Core scan + replace ───────────────────────────────────────────────────

const HEX_PATTERN = /#([0-9a-fA-F]{3,6})\b/g;

let totalFiles        = 0;
let modifiedFiles     = 0;
let totalReplacements = 0;
const unmapped        = [];
const replacements    = [];

function processFile(filePath) {
  totalFiles++;
  const raw   = fs.readFileSync(filePath, "utf8");
  const lines = raw.split("\n");
  let modified = false;

  const newLines = lines.map((line, idx) => {
    const lineNum = idx + 1;
    let newLine = line;

    // CSS variable name replacements (Pattern 4)
    for (const [varName, mapping] of Object.entries(cssVarMap)) {
      if (newLine.includes(varName)) {
        newLine = newLine.replaceAll(varName, mapping.replacement);
        replacements.push({
          file: filePath,
          line: lineNum,
          old: varName,
          hex: mapping.hex,
          token: mapping.token,
        });
        totalReplacements++;
        modified = true;
      }
    }

    // Hex replacements (Patterns 1, 2, 3, 5)
    newLine = newLine.replace(HEX_PATTERN, (match, captured) => {
      const norm = normaliseHex(captured);
      const entry = darkToLightMap[norm];
      if (entry) {
        replacements.push({
          file: filePath,
          line: lineNum,
          old: norm,
          hex: entry.replacement,
          token: entry.token,
        });
        totalReplacements++;
        modified = true;
        return entry.replacement;
      }
      // Check if it's dark but unmapped
      if (isDarkBackground(norm)) {
        unmapped.push({ file: filePath, line: lineNum, hex: norm });
      }
      return match;
    });

    return newLine;
  });

  if (modified) {
    fs.writeFileSync(filePath, newLines.join("\n"), "utf8");
    modifiedFiles++;
  }
}

// ── Run ───────────────────────────────────────────────────────────────────

const files = walk(SRC_ROOT);
files.forEach(processFile);

// ── Second-pass: zero dark background check ───────────────────────────────

const remainingDark = [];
walk(SRC_ROOT).forEach(filePath => {
  const lines = fs.readFileSync(filePath, "utf8").split("\n");
  lines.forEach((line, idx) => {
    const matches = [...line.matchAll(HEX_PATTERN)];
    matches.forEach(match => {
      const norm = normaliseHex(match[1]);
      if (isDarkBackground(norm)) {
        remainingDark.push({ file: filePath, line: idx + 1, hex: norm });
      }
    });
  });
});

// ── Print report ─────────────────────────────────────────────────────────

const SEP = "═".repeat(62);
const DIV = "─".repeat(62);

console.log(`\n${SEP}`);
console.log(`CIE v2.3.2 Dark→Light Theme Migration Report`);
console.log(SEP);
console.log(`\nFILES SCANNED:       ${totalFiles}`);
console.log(`FILES MODIFIED:      ${modifiedFiles}`);
console.log(`TOTAL REPLACEMENTS:  ${totalReplacements}`);
console.log(`UNMAPPED (manual):   ${unmapped.length}`);

console.log(`\n${DIV}\n── REPLACEMENTS\n${DIV}`);
replacements.forEach(r => {
  const rel = path.relative(SRC_ROOT, r.file);
  console.log(`${rel}:${r.line}  ${r.old} → ${r.hex}  (token: ${r.token})`);
});

if (unmapped.length > 0) {
  console.log(`\n${DIV}\n── UNMAPPED (manual review required)\n${DIV}`);
  unmapped.forEach(u => {
    const rel = path.relative(SRC_ROOT, u.file);
    console.log(`UNMAPPED  ${rel}:${u.line}  ${u.hex}`);
  });
}

console.log(`\n${DIV}\n── ZERO DARK BACKGROUND CHECK\n${DIV}`);
if (remainingDark.length === 0) {
  console.log(`PASS — no dark backgrounds remain`);
} else {
  console.log(`FAIL — remaining dark values found:`);
  remainingDark.forEach(r => {
    const rel = path.relative(SRC_ROOT, r.file);
    console.log(`  ${rel}:${r.line}  ${r.hex}`);
  });
}
console.log(`${SEP}\n`);

process.exit(remainingDark.length > 0 ? 1 : 0);

