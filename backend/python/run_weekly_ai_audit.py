#!/usr/bin/env python3
"""
CIE v2.3.2 — Weekly AI citation audit entry point (all four core categories).
SOURCE: CIE_Master_Developer_Build_Spec.docx §12.1 — Monday 09:00 UTC via sync.ai_audit_cron_schedule.

Uses same DB connection pattern as run_decay_escalation.py.
"""
import logging
import os
import sys

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

CATEGORIES = ("cables", "lampshades", "bulbs", "pendants")


def get_db():
    try:
        from src.utils.mysql_connect import pymysql_connect_dict_cursor

        return pymysql_connect_dict_cursor()
    except Exception as e:
        logger.error("DB connection failed: %s", e)
        raise


def main():
    from src.ai_audit.weekly_service import run_weekly_audit

    brand = (os.environ.get("CIE_AUDIT_BRAND_NAME") or "CIE").strip() or "CIE"
    db = get_db()
    try:
        for cat in CATEGORIES:
            try:
                summary = run_weekly_audit(db, cat, brand)
                logger.info("Weekly audit category=%s summary=%s", cat, summary.get("status"))
            except Exception as exc:
                logger.exception("Weekly audit failed for category=%s: %s", cat, exc)
    finally:
        db.close()


if __name__ == "__main__":
    main()
