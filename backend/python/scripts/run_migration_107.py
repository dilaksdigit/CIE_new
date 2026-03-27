"""
Apply database/migrations/107_add_missing_columns_to_sku_master.sql via PyMySQL.

SOURCE: operational helper — not an OpenAPI surface.
Run from repo:  python scripts/run_migration_107.py
"""
from __future__ import annotations

import os
import sys
from pathlib import Path

# backend/python as cwd
ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(ROOT / "src"))

from dotenv import load_dotenv

load_dotenv(ROOT.parent.parent / ".env")
load_dotenv(ROOT / ".env")

import pymysql
from pymysql.constants import CLIENT

MIGRATION = ROOT.parent.parent / "database" / "migrations" / "107_add_missing_columns_to_sku_master.sql"


def main() -> None:
    sql = MIGRATION.read_text(encoding="utf-8")
    conn = pymysql.connect(
        host=os.environ.get("DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("DB_PORT", "3306")),
        user=os.environ.get("DB_USER") or os.environ.get("DB_USERNAME", "root"),
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_DATABASE", "cie_v232"),
        client_flag=CLIENT.MULTI_STATEMENTS,
    )
    try:
        with conn.cursor() as cur:
            cur.execute(sql)
            while cur.nextset():
                pass
        conn.commit()
        print("Migration 107 applied OK.")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
