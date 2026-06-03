#!/usr/bin/env python3
"""
Generate database/postgres/canonical/03_spec_indexes.sql from legacy MySQL migrations.

SOURCE: CIE Master Build Spec §6, Build Pack per-table indexes, Hardening Addendum,
        Semrush CSV Import Spec — indexes stripped by convert_mysql_migrations_to_pg.py
        are restored here as native PostgreSQL DDL (idempotent).
"""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MYSQL_DIR = ROOT / "database" / "migrations"
OUT_FILE = ROOT / "database" / "postgres" / "canonical" / "03_spec_indexes.sql"
EXPECTED_JSON = ROOT / "database" / "postgres" / "canonical" / "expected_indexes.json"

HEADER = """-- =============================================================================
-- CIE v2.3.2 — Spec-native PostgreSQL indexes (idempotent)
-- =============================================================================
-- SOURCE: CIE_Master_Developer_Build_Spec.docx §6
--         CIE_v231_Developer_Build_Pack.pdf (per-table index columns)
--         CIE_v232_Hardening_Addendum.pdf (idx_retry_status)
--         CIE_v232_Semrush_CSV_Import_Spec.docx (semrush_imports indexes)
--
-- Applied on every greenfield init AFTER table migrations:
--   database/postgres/init/03_spec_indexes.sql
--
-- Do NOT rely on convert_mysql_migrations_to_pg.py alone — it strips inline KEY/INDEX.
-- =============================================================================

"""


def mysql_prefix_to_pg(expr: str) -> str:
    """Convert MySQL index column expressions to PostgreSQL."""
    expr = expr.strip()
    # keyword(100) -> keyword (full column; PG btree handles varchar)
    expr = re.sub(r"\((\d+)\)", "", expr)
    expr = expr.replace("`", '"')
    return expr


def disambiguate_index_name(table: str, name: str, used: dict[str, str]) -> str:
    """PostgreSQL index names are unique per schema (unlike MySQL per-table)."""
    if name not in used or used[name] == table:
        used[name] = table
        return name
    alt = f"{table}_{name}"
    if len(alt) > 63:
        alt = alt[:63]
    used[alt] = table
    return alt


def parse_mysql_migrations() -> list[str]:
    """Return CREATE INDEX statements with schema-unique index names."""
    indexes: list[str] = []
    seen: set[str] = set()
    index_names_used: dict[str, str] = {}
    skip_tables = {"validation_retry_queue"}  # replaced by vector_retry_queue in 033

    for path in sorted(MYSQL_DIR.glob("*.sql")):
        text = path.read_text(encoding="utf-8", errors="ignore")
        # Standalone CREATE INDEX
        for m in re.finditer(
            r"CREATE\s+(UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s+ON\s+`?(\w+)`?\s*\(([^)]+)\)",
            text,
            re.IGNORECASE,
        ):
            unique, name, table, cols = m.groups()
            if table in skip_tables:
                continue
            cols_pg = mysql_prefix_to_pg(cols)
            key = f"{table}.{name}"
            if key in seen:
                continue
            seen.add(key)
            name = disambiguate_index_name(table, name, index_names_used)
            if table == "audit_log" and "timestamp" in cols_pg.lower():
                continue  # conditional block (column may not exist on fresh 011)
            if unique:
                stmt = (
                    f"CREATE UNIQUE INDEX IF NOT EXISTS {name} ON {table} ({cols_pg});"
                )
            else:
                stmt = f"CREATE INDEX IF NOT EXISTS {name} ON {table} ({cols_pg});"
            indexes.append(stmt)

        # Inline INDEX / KEY inside CREATE TABLE blocks
        for m in re.finditer(
            r"(?im)^\s*(UNIQUE\s+)?(?:KEY|INDEX)\s+`?(\w+)`?\s*\(([^)]+)\)",
            text,
        ):
            unique, name, cols = m.groups()
            # Skip PRIMARY
            if name.upper() == "PRIMARY":
                continue
            # Find preceding CREATE TABLE table name (best effort)
            before = text[: m.start()]
            tbl_m = re.findall(
                r"(?im)CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?",
                before,
            )
            if not tbl_m:
                continue
            table = tbl_m[-1]
            if table in skip_tables:
                continue
            cols_pg = mysql_prefix_to_pg(cols)
            key = f"{table}.{name}"
            if key in seen:
                continue
            seen.add(key)
            name = disambiguate_index_name(table, name, index_names_used)
            if table == "audit_log" and "timestamp" in cols_pg.lower():
                continue
            if unique:
                # Prefer UNIQUE INDEX for PG (matches MySQL UNIQUE KEY)
                stmt = (
                    f"CREATE UNIQUE INDEX IF NOT EXISTS {name} ON {table} ({cols_pg});"
                )
                indexes.append(stmt)
            else:
                stmt = f"CREATE INDEX IF NOT EXISTS {name} ON {table} ({cols_pg});"
                indexes.append(stmt)

    return indexes


def extra_manual_indexes(index_names_used: dict[str, str]) -> list[str]:
    """Indexes from specs / aliases not always present in MySQL files."""
    extras = [
        ("audit_log", "idx_entity", "entity_type, entity_id"),
        ("audit_log", "idx_user", "user_id"),
        ("audit_log", "idx_created_at", "created_at"),
        ("channel_mappings", "idx_channel_mappings_sku", "sku_id"),
        ("sku_vectors", "idx_sku_vectors_sku", "sku_id"),
        ("cluster_vectors", "idx_cluster_vectors_cluster", "cluster_id"),
        ("semrush_imports", "idx_batch", "import_batch"),
        ("semrush_imports", "idx_batch_id", "import_batch_id"),
    ]
    lines = ["-- Supplemental indexes (spec / FK helpers)"]
    for table, name, cols in extras:
        name = disambiguate_index_name(table, name, index_names_used)
        lines.append(f"CREATE INDEX IF NOT EXISTS {name} ON {table} ({cols});")
    return lines


def conditional_blocks() -> str:
    return """
-- -----------------------------------------------------------------------------
-- Conditional indexes (column may be added in later migrations)
-- -----------------------------------------------------------------------------
DO $cie$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'audit_log' AND column_name = 'timestamp'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_audit_log_timestamp_col ON audit_log ("timestamp");
    CREATE INDEX IF NOT EXISTS idx_audit_time ON audit_log ("timestamp");
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'audit_log' AND column_name = 'actor_id'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_audit_log_actor ON audit_log (actor_id, created_at);
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'audit_log' AND column_name = 'changed_at'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_audit_log_actor_changed ON audit_log (actor_id, changed_at);
    CREATE INDEX IF NOT EXISTS idx_audit_log_entity_changed ON audit_log (entity_type, entity_id, changed_at);
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'skus' AND column_name = 'shopify_product_id'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_skus_shopify_product_id ON skus (shopify_product_id);
  END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'sku_master' AND column_name = 'shopify_url'
  ) THEN
    CREATE INDEX IF NOT EXISTS idx_sku_master_shopify_url ON sku_master (shopify_url);
  END IF;
END
$cie$;
"""


def main() -> int:
    indexes = parse_mysql_migrations()
    index_names_used: dict[str, str] = {}
    for stmt in indexes:
        m = re.search(r"INDEX IF NOT EXISTS (\w+) ON (\w+)", stmt)
        if m:
            index_names_used[m.group(1)] = m.group(2)
    indexes.extend(extra_manual_indexes(index_names_used))

    # Deduplicate preserving order
    deduped: list[str] = []
    seen_stmt: set[str] = set()
    for stmt in indexes:
        norm = stmt.strip()
        if norm in seen_stmt:
            continue
        seen_stmt.add(norm)
        deduped.append(stmt)

    body = HEADER + "\n".join(deduped) + "\n" + conditional_blocks()
    OUT_FILE.parent.mkdir(parents=True, exist_ok=True)
    OUT_FILE.write_text(body, encoding="utf-8")

    import json

    names = []
    for stmt in deduped:
        m = re.search(r"INDEX IF NOT EXISTS (\w+)", stmt)
        if m:
            names.append(m.group(1))
    for name in (
        "idx_audit_log_actor",
        "idx_audit_log_entity_changed",
        "idx_skus_shopify_product_id",
        "idx_sku_master_shopify_url",
    ):
        names.append(name)
    EXPECTED_JSON.write_text(
        json.dumps(sorted(set(names)), indent=2) + "\n", encoding="utf-8"
    )

    print(f"Wrote {len(deduped)} index statements to {OUT_FILE}")
    print(f"Wrote {len(set(names))} expected index names to {EXPECTED_JSON}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
