"""
Read business_rules from MySQL for Python workers (cron jobs, FastAPI).

SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2–§5.3
"""

from __future__ import annotations

import logging
from typing import Any, Optional

from .mysql_connect import pymysql_connect_dict_cursor

logger = logging.getLogger(__name__)


def _connect():
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — same env contract as other Python DB helpers
    return pymysql_connect_dict_cursor()


def get_business_rule(key: str, default: Optional[Any] = None) -> Any:
    """
    Return a single business_rules.value by rule_key, coerced by value_type.

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — sync.baseline_lookback_weeks, cis.* windows, etc.
    """
    try:
        db = _connect()
        try:
            cur = db.cursor()
            cur.execute(
                "SELECT `value`, `value_type` FROM business_rules WHERE rule_key = %s LIMIT 1",
                (key,),
            )
            row = cur.fetchone()
            cur.close()
            if not row:
                return default
            raw = row["value"]
            vtype = (row.get("value_type") or "string").lower()
            if vtype == "integer":
                return int(raw)
            if vtype == "float" or vtype == "decimal":
                return float(raw)
            if vtype == "boolean":
                return str(raw).lower() in ("true", "1", "yes")
            return raw
        finally:
            db.close()
    except Exception as exc:
        logger.warning("get_business_rule(%s) failed: %s", key, exc)
        return default
