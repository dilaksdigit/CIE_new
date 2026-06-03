#!/usr/bin/env python3
"""
Fail if operator-facing docs still instruct MySQL setup (DECISION-013).

Checks curated files only — not GAP_LOG, audit.md body, or vendor/.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

OPERATOR_DOCS = [
    ROOT / "README.md",
    ROOT / "QUICK_START_GUIDE.md",
    ROOT / "IMPLEMENTATION_GUIDE.md",
    ROOT / "ARCHITECTURE.md",
    ROOT / "DEPLOYMENT_RUNBOOK.md",
    ROOT / "CLAUDE.md",
    ROOT / ".env.example",
    ROOT / "docker-compose.yml",
    ROOT / "database/postgres/README.md",
    ROOT / "MASTER_SUMMARY.md",
    ROOT / "WORKFLOW_WIRING_SUMMARY.md",
    ROOT / "SYSTEM_ARCHITECTURE_COMPLETE.md",
    ROOT / "docs/API_REFERENCE_COMPLETE.md",
    ROOT / "scripts/README_WORKFLOW_CHECK.md",
    ROOT / "backend/python/CRON_SCHEDULER.md",
]

# Patterns that misconfigure operators (not allowed in operator docs)
FORBIDDEN = [
    (re.compile(r"mysql-service", re.I), "mysql-service (use Docker service `db`)"),
    (re.compile(r"DB_CONNECTION\s*=\s*mysql", re.I), "DB_CONNECTION=mysql"),
    (re.compile(r"DB_PORT\s*=\s*3306", re.I), "DB_PORT=3306"),
    (re.compile(r"localhost:3306", re.I), "localhost:3306"),
    (re.compile(r"db:3306", re.I), "db:3306"),
    (re.compile(r"docker-compose exec db mysql\b", re.I), "mysql client via docker"),
    (re.compile(r"\bpymysql==", re.I), "pymysql dependency in docs"),
    (re.compile(r"🐘 MySQL 8\.0", re.I), "MySQL 8.0 in architecture diagram"),
]

# Lines may mention legacy MySQL as reference
ALLOW_SUBSTRINGS = [
    "legacy",
    "reference",
    "MySQL-dialect",
    "MySQL reference",
    "convert_mysql",
    "not used",
    "pre-migration",
    "superseded",
]


def line_allowed(line: str) -> bool:
    lower = line.lower()
    return any(s.lower() in lower for s in ALLOW_SUBSTRINGS)


def main() -> int:
    failures: list[str] = []
    for path in OPERATOR_DOCS:
        if not path.is_file():
            failures.append(f"MISSING expected doc: {path.relative_to(ROOT)}")
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        for i, line in enumerate(text.splitlines(), 1):
            if line_allowed(line):
                continue
            for pattern, label in FORBIDDEN:
                if pattern.search(line):
                    failures.append(
                        f"{path.relative_to(ROOT)}:{i}: {label} → {line.strip()[:100]}"
                    )
                    break

    if failures:
        print("FAIL: PostgreSQL documentation drift detected:", file=sys.stderr)
        for f in failures:
            print(f"  {f}", file=sys.stderr)
        return 1

    print(f"OK: {len(OPERATOR_DOCS)} operator docs aligned with PostgreSQL (DECISION-013)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
