"""
Read business_rules from PostgreSQL for Python workers (cron jobs, FastAPI).

SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2–§5.3
"""

from __future__ import annotations

import logging
from typing import Any, Optional, Tuple

from .db_connect import connect_dict_cursor, cursor_dict

logger = logging.getLogger(__name__)

_COLUMN_CACHE: Optional[Tuple[str, str]] = None


def _coerce_rule_value(raw: Any, vtype: str) -> Any:
    vtype = (vtype or "string").lower()
    if vtype == "integer":
        return int(raw)
    if vtype in ("float", "decimal"):
        return float(raw)
    if vtype == "boolean":
        return str(raw).lower() in ("true", "1", "yes")
    return raw


def _resolve_columns(conn) -> Tuple[str, str]:
    """Return (value_column, type_column) — canonical or legacy MySQL-style names."""
    global _COLUMN_CACHE
    if _COLUMN_CACHE is not None:
        return _COLUMN_CACHE

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'business_rules'
            """
        )
        cols = {row[0] for row in cur.fetchall()}

    if "rule_value" in cols:
        _COLUMN_CACHE = ("rule_value", "data_type")
    elif "value" in cols:
        _COLUMN_CACHE = ("value", "value_type")
    else:
        raise RuntimeError(
            "business_rules table missing value columns (expected rule_value or value)"
        )
    return _COLUMN_CACHE


def load_all_business_rules() -> dict[str, Any]:
    """Load full business_rules table into a typed dict (PHP BusinessRulesService parity)."""
    conn = _connect()
    try:
        value_col, type_col = _resolve_columns(conn)
        cur = cursor_dict(conn)
        cur.execute(
            f"SELECT rule_key, {value_col}, {type_col} FROM business_rules"
        )
        out: dict[str, Any] = {}
        for row in cur.fetchall():
            key = row.get("rule_key")
            if not key:
                continue
            out[key] = _coerce_rule_value(row.get(value_col), row.get(type_col))
        cur.close()
        conn.commit()
        return out
    finally:
        conn.close()


def _connect():
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — same env contract as other Python DB helpers
    return connect_dict_cursor()


def get_business_rule(key: str, default: Optional[Any] = None) -> Any:
    """
    Return a single business_rules value by rule_key, coerced by data_type.

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — sync.baseline_lookback_weeks, cis.* windows, etc.
    """
    try:
        db = _connect()
        try:
            value_col, type_col = _resolve_columns(db)
            cur = cursor_dict(db)
            cur.execute(
                f"SELECT {value_col}, {type_col} FROM business_rules WHERE rule_key = %s LIMIT 1",
                (key,),
            )
            row = cur.fetchone()
            cur.close()
            if not row:
                return default
            return _coerce_rule_value(row.get(value_col), row.get(type_col))
        finally:
            db.close()
    except Exception as exc:
        logger.warning("get_business_rule(%s) failed: %s", key, exc)
        return default
