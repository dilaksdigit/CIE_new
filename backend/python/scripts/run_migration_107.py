"""
Apply database/migrations/107_add_missing_columns_to_sku_master.sql via PostgreSQL.

SOURCE: operational migration helper — dialect updated for Postgres.
"""

from __future__ import annotations

import os
import sys

_REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)
_PY = os.path.join(_REPO_ROOT, "backend", "python")
if _PY not in sys.path:
    sys.path.insert(0, _PY)

from src.utils.db_connect import connect_dict_cursor  # noqa: E402


def main() -> int:
    sql_path = os.path.join(
        _REPO_ROOT, "database", "migrations", "107_add_missing_columns_to_sku_master.sql"
    )
    if not os.path.isfile(sql_path):
        print(f"Missing: {sql_path}", file=sys.stderr)
        return 1
    with open(sql_path, encoding="utf-8") as f:
        raw = f.read()
    # Strip MySQL-only preamble; statements must be Postgres-compatible or hand-edited.
    statements = [
        s.strip()
        for s in raw.replace("SET NAMES utf8mb4;", "").split(";")
        if s.strip() and not s.strip().startswith("--")
    ]
    conn = connect_dict_cursor()
    try:
        cur = conn.cursor()
        for stmt in statements:
            if not stmt:
                continue
            try:
                cur.execute(stmt)
            except Exception as exc:
                print(f"Skip/fail: {exc}\n  SQL: {stmt[:120]}...", file=sys.stderr)
        conn.commit()
        cur.close()
    finally:
        conn.close()
    print("Migration 107 apply attempted (review errors above).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
