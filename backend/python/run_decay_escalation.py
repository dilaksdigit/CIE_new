#!/usr/bin/env python3
"""
CIE v2.3.2 — Decay escalation scheduler entry point.
Run weekly after AI audit (e.g. cron: 0 6 * * 1 python run_decay_escalation.py).

Uses DATABASE_URL or MySQL env vars to connect; calls run_decay_escalation with
default_brief_generate_hook so week-3 auto-briefs queue the Python worker.
"""
import os
import sys
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Add backend/python to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

def get_db():
    """PEP-249 style connection from env (e.g. MySQL)."""
    try:
        from src.utils.mysql_connect import pymysql_connect_dict_cursor

        return pymysql_connect_dict_cursor()
    except Exception as e:
        logger.error("DB connection failed: %s", e)
        raise


def main():
    from src.ai_audit.decay_cron import run_decay_escalation, default_brief_generate_hook
    db = get_db()
    try:
        actions = run_decay_escalation(db, default_brief_generate_hook)
        logger.info("Decay escalation run complete: %d actions", len(actions))
        for a in actions:
            logger.info("  %s", a)
    finally:
        db.close()


if __name__ == "__main__":
    main()
