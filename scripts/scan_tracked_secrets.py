#!/usr/bin/env python3
"""
P0 — Scan git-tracked files for credential patterns (not .env, which is gitignored).
Exits 1 if likely secrets are committed.
"""
from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

PATTERNS = [
    (re.compile(r"shpat_[a-f0-9]{20,}", re.I), "Shopify Admin token"),
    (re.compile(r"sk-[a-zA-Z0-9]{20,}"), "OpenAI API key"),
    (re.compile(r"sk-ant-[a-zA-Z0-9-]{20,}"), "Anthropic API key"),
    (re.compile(r"AKIA[0-9A-Z]{16}"), "AWS access key"),
    (re.compile(r"-----BEGIN (?:RSA |EC )?PRIVATE KEY-----"), "Private key PEM"),
]

SKIP_PREFIXES = (
    "backend/php/vendor/",
    "backend/python/.venv/",
    "node_modules/",
    "frontend/dist/",
    ".pytest_cache/",
)

SKIP_FILES = {
    ".env.example",
    "scripts/scan_tracked_secrets.py",
    "tests/php/Feature/ProductionSecurityTest.php",
    "tests/php/Feature/DemoTokenGateTest.php",
    "docs/API_REFERENCE_COMPLETE.md",
    "IMPLEMENTATION_GUIDE.md",
    "QUICK_START_GUIDE.md",
    "audit.md",
    "WORKFLOW_WIRING_SUMMARY.md",
    "MASTER_SUMMARY.md",
}


def tracked_files() -> list[str]:
    r = subprocess.run(
        ["git", "ls-files"],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=False,
    )
    if r.returncode != 0:
        return []
    return [line.strip() for line in r.stdout.splitlines() if line.strip()]


def main() -> int:
    hits: list[str] = []
    for rel in tracked_files():
        if any(rel.startswith(p) for p in SKIP_PREFIXES):
            continue
        if rel in SKIP_FILES or rel.endswith(".lock"):
            continue
        path = ROOT / rel
        if not path.is_file():
            continue
        try:
            text = path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            continue
        for rx, label in PATTERNS:
            if rx.search(text):
                hits.append(f"{rel}: {label}")
                break

    if hits:
        print("FAIL: possible secrets in tracked files:", file=sys.stderr)
        for h in hits:
            print(f"  {h}", file=sys.stderr)
        return 1

    print("OK: no credential patterns in git-tracked files")
    return 0


if __name__ == "__main__":
    sys.exit(main())
