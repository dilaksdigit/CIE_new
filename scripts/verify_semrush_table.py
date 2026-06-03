#!/usr/bin/env python3
# SOURCE: Production Roadmap — verify semrush_imports exists after migration 166
"""
Check PostgreSQL for semrush_imports table. Requires psql or DATABASE_URL.

Usage:
  export DATABASE_URL=postgresql://user:pass@host:5432/cie_v232
  python scripts/verify_semrush_table.py

Or:
  export PGHOST=localhost PGUSER=postgres PGDATABASE=cie_v232 PGPASSWORD=...
  python scripts/verify_semrush_table.py
"""
from __future__ import annotations

import os
import subprocess
import sys


def main() -> int:
    database_url = os.environ.get("DATABASE_URL", "").strip()
    if not database_url:
        host = os.environ.get("PGHOST", os.environ.get("DB_HOST", "localhost"))
        port = os.environ.get("PGPORT", os.environ.get("DB_PORT", "5432"))
        user = os.environ.get("PGUSER", os.environ.get("DB_USERNAME", "postgres"))
        password = os.environ.get("PGPASSWORD", os.environ.get("DB_PASSWORD", ""))
        db = os.environ.get("PGDATABASE", os.environ.get("DB_DATABASE", "cie_v232"))
        if password:
            os.environ["PGPASSWORD"] = password
        cmd = [
            "psql",
            "-h", host,
            "-p", str(port),
            "-U", user,
            "-d", db,
            "-tAc",
            "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='semrush_imports');",
        ]
    else:
        cmd = [
            "psql",
            database_url,
            "-tAc",
            "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='semrush_imports');",
        ]

    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=False)
    except FileNotFoundError:
        print("psql not found — install PostgreSQL client or set DATABASE_URL and psql on PATH", file=sys.stderr)
        return 2

    if result.returncode != 0:
        print(result.stderr or result.stdout, file=sys.stderr)
        return result.returncode

    exists = result.stdout.strip().lower() in ("t", "true", "1")
    if exists:
        print("OK: semrush_imports table exists")
        return 0

    print(
        "FAIL: semrush_imports missing — apply database/postgres/migrations/166_ensure_semrush_imports_table.sql",
        file=sys.stderr,
    )
    return 1


if __name__ == "__main__":
    sys.exit(main())
