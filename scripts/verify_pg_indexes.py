#!/usr/bin/env python3
"""
Verify PostgreSQL schema has spec-expected indexes (post init 03_spec_indexes).

Usage:
  python scripts/verify_pg_indexes.py
  DATABASE_URL=postgresql://user:pass@host:5432/db python scripts/verify_pg_indexes.py
"""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EXPECTED = ROOT / "database" / "postgres" / "canonical" / "expected_indexes.json"


def main() -> int:
    if not EXPECTED.is_file():
        print(f"Missing {EXPECTED}; run: python scripts/generate_pg_spec_indexes.py", file=sys.stderr)
        return 2

    expected = set(json.loads(EXPECTED.read_text(encoding="utf-8")))

    try:
        import psycopg2
    except ImportError:
        print("psycopg2 required: pip install psycopg2-binary", file=sys.stderr)
        return 2

    dsn = (
        os.environ.get("DATABASE_URL")
        or os.environ.get("DB_URL")
        or _dsn_from_db_env()
    )
    if not dsn:
        print("Set DATABASE_URL or DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD", file=sys.stderr)
        return 2

    conn = psycopg2.connect(dsn)
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT indexname
                FROM pg_indexes
                WHERE schemaname = 'public'
                """
            )
            present = {row[0] for row in cur.fetchall()}
    finally:
        conn.close()

    missing = sorted(expected - present)
    extra = sorted(present & expected)  # noqa: F841 — informational only

    if missing:
        print(f"FAIL: {len(missing)} expected index(es) missing:")
        for name in missing[:40]:
            print(f"  - {name}")
        if len(missing) > 40:
            print(f"  ... and {len(missing) - 40} more")
        return 1

    print(f"OK: all {len(expected)} spec indexes present in public schema")
    return 0


def _dsn_from_db_env() -> str | None:
    host = os.environ.get("DB_HOST")
    db = os.environ.get("DB_DATABASE")
    user = os.environ.get("DB_USERNAME")
    password = os.environ.get("DB_PASSWORD", "")
    port = os.environ.get("DB_PORT", "5432")
    if not all([host, db, user]):
        return None
    return f"postgresql://{user}:{password}@{host}:{port}/{db}"


if __name__ == "__main__":
    raise SystemExit(main())
