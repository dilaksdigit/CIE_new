"""
Read business_rules from MySQL for Python workers (cron jobs, FastAPI).

SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2–§5.3
"""

from __future__ import annotations

import logging
import os
from typing import Any, Optional
from urllib.parse import urlparse

import pymysql

logger = logging.getLogger(__name__)


def _connect():
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — same env contract as other Python DB helpers
    url = os.environ.get("DATABASE_URL", "")
    if url:
        parsed = urlparse(url)
        return pymysql.connect(
            host=parsed.hostname or os.environ.get("DB_HOST", "localhost"),
            port=parsed.port or 3306,
            user=parsed.username or os.environ.get("DB_USER", "root"),
            password=parsed.password or os.environ.get("DB_PASSWORD", ""),
            database=(parsed.path or "").lstrip("/") or os.environ.get("DB_DATABASE", "cie"),
            cursorclass=pymysql.cursors.DictCursor,
        )
    return pymysql.connect(
        host=os.environ.get("DB_HOST", "localhost"),
        user=os.environ.get("DB_USER", "root"),
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_DATABASE", "cie"),
        cursorclass=pymysql.cursors.DictCursor,
    )


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
