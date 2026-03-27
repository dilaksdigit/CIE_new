"""
Weekly GA4 sync job.

SOURCE: CIE_Master_Developer_Build_Spec.docx
- §10.2  GA4 weekly pull — Monday 03:00 UTC, 24h after GSC
"""

from __future__ import annotations

from . import _bootstrap  # noqa: F401 — sys.path + load repo .env

import logging
import os
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Iterable, List

import pymysql

from utils.config import Config
from utils.business_rules import get_business_rule
from utils.mysql_connect import pymysql_connect_dict_cursor
from utils.sku_master_url_lookup import load_sku_url_lookup, log_sku_master_url_diagnostics, match_url
from utils.url_utils import normalise_url

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


@dataclass
class Ga4DbRow:
    """Row persisted to ga4_landing_performance (after SKU match)."""

    sku_id: str | None
    landing_page: str
    sessions: int
    organic_conversions: float
    conversion_rate: float | None
    revenue: float
    bounce_rate: float


def _get_db():
    return pymysql_connect_dict_cursor()


def pull_weekly_ga4(start_date: datetime, end_date: datetime):
    """
    Spec §10.2: GA4 weekly pull for Organic Search channel only, using landingPage dimension.
    """
    property_id = Config.GA4_PROPERTY_ID or os.environ.get("GA4_PROPERTY_ID", "")
    if not property_id:
        logger.warning("GA4_PROPERTY_ID not set; skipping weekly GA4 pull")
        return []
    from integrations.ga4_client import pull_weekly_ga4_rows

    return pull_weekly_ga4_rows(property_id, start_date, end_date)


def _upsert_sync_status(service: str, status: str, last_error: str | None = None, success: bool = False) -> None:
    """
    OPERATIONAL (non–spec §6): optional sync_status table for dashboard health.
    Missing table must not emit WARNING (migration 137_create_sync_status_table.sql).
    """
    db = _get_db()
    try:
        cur = db.cursor()
        if success:
            cur.execute(
                """
                INSERT INTO sync_status (service, status, last_success_at, last_error, last_error_at)
                VALUES (%s, %s, NOW(), NULL, NULL)
                ON DUPLICATE KEY UPDATE status=VALUES(status), last_success_at=VALUES(last_success_at), last_error=NULL, last_error_at=NULL
                """,
                (service, status),
            )
        else:
            cur.execute(
                """
                INSERT INTO sync_status (service, status, last_error, last_error_at)
                VALUES (%s, %s, %s, NOW())
                ON DUPLICATE KEY UPDATE status=VALUES(status), last_error=VALUES(last_error), last_error_at=VALUES(last_error_at)
                """,
                (service, status, (last_error or "")[:1000]),
            )
        db.commit()
        cur.close()
    except Exception as exc:
        err_no = getattr(exc, "args", [None])[0]
        if err_no == 1146:
            logger.debug("sync_status table not present — skipping operational status row: %s", exc)
        else:
            logger.warning("sync_status update failed (service=%s): %s", service, exc)
    finally:
        db.close()


def _alert_admin(message: str) -> None:
    logger.error("ADMIN ALERT: %s", message)


def _alert_dev_lead(message: str) -> None:
    logger.critical("DEV LEAD ALERT: %s", message)


def _save_unmatched_urls(urls: Iterable[str], week_ending: datetime) -> None:
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §9.3
    urls_list = [u for u in urls if u]
    if not urls_list:
        return
    db = _get_db()
    window_date = week_ending.date()
    try:
        cur = db.cursor()
        for url in urls_list:
            try:
                cur.execute(
                    """
                    INSERT INTO gsc_unmatched_urls (url, source, window_end)
                    VALUES (%s, %s, %s)
                    """,
                    (url[:1000], "ga4", window_date),
                )
            except pymysql.err.OperationalError as exc:
                if exc.args and exc.args[0] != 1054:
                    raise
                cur.execute(
                    """
                    INSERT INTO gsc_unmatched_urls (url, window_end)
                    VALUES (%s, %s)
                    """,
                    (url[:1000], window_date),
                )
        db.commit()
        cur.close()
    finally:
        db.close()


def _compute_conversion_rate(conversions: float, sessions: int) -> float | None:
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §10.2 — NULL if sessions is 0
    if not sessions:
        return None
    return round(conversions / sessions, 6)


def save_ga4_landing_performance(rows: Iterable[Ga4DbRow], window_end: datetime) -> None:
    """
    Persist GA4 landing performance into ga4_landing_performance table.
    """
    rows_list = list(rows)
    if not rows_list:
        return
    db = _get_db()
    try:
        cur = db.cursor()
        window_date = window_end.date()
        for row in rows_list:
            cur.execute(
                """
                INSERT INTO ga4_landing_performance (
                    sku_id, landing_page, window_end, week_ending,
                    sessions, conversion_rate, revenue, bounce_rate,
                    organic_sessions, organic_conversions, organic_conversion_rate, revenue_organic
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    row.sku_id,
                    row.landing_page,
                    window_date,
                    window_date,
                    row.sessions,
                    row.conversion_rate,
                    row.revenue,
                    row.bounce_rate,
                    row.sessions,
                    row.organic_conversions,
                    row.conversion_rate,
                    row.revenue,
                ),
            )
        db.commit()
        cur.close()
        logger.info("ga4_landing_performance: inserted %s rows for window_end=%s", len(rows_list), window_date)
    finally:
        db.close()


def run() -> None:
    """
    Monday 03:00 UTC — 7-day window ending previous Sunday (spec §10.2).
    """
    ga4_cron = get_business_rule("sync.ga4_cron_schedule", "0 3 * * 1")
    logger.info("Starting weekly GA4 sync (cron=%s)", ga4_cron)
    log_sku_master_url_diagnostics()

    today_utc = datetime.now(timezone.utc).date()
    # Monday run: window ends previous calendar Sunday (today − 1 day when today is Monday).
    window_end = today_utc - timedelta(days=1)
    window_start = window_end - timedelta(days=6)

    start_dt = datetime.combine(window_start, datetime.min.time(), tzinfo=timezone.utc)
    end_dt = datetime.combine(window_end, datetime.max.time(), tzinfo=timezone.utc)

    prop = Config.GA4_PROPERTY_ID or os.environ.get("GA4_PROPERTY_ID", "")
    prop_log = prop if (prop or "").startswith("properties/") else f"properties/{prop}" if prop else "(unset)"
    logger.info(
        "GA4 weekly pull request params: property=%s date_start=%s date_end=%s filter=sessionDefaultChannelGroup EXACT 'Organic Search'",
        prop_log,
        start_dt.strftime("%Y-%m-%d"),
        end_dt.strftime("%Y-%m-%d"),
    )

    api_rows: List = []
    backoff = [30, 120, 600]
    for idx, delay in enumerate(backoff, start=1):
        try:
            api_rows = pull_weekly_ga4(start_dt, end_dt)
            break
        except Exception as exc:
            exc_name = exc.__class__.__name__.lower()
            msg = str(exc)
            if "resourceexhausted" in exc_name or "429" in msg:
                logger.warning("GA4 429 retry %s/%s in %ss: %s", idx, len(backoff), delay, exc)
                time.sleep(delay)
                continue
            if ("refresherror" in exc_name or "permissiondenied" in exc_name or
                    "unauthenticated" in exc_name or "invalid_grant" in msg.lower()):
                _alert_dev_lead(f"GA4 auth failure: {exc}")
                _upsert_sync_status("ga4", "disconnected", last_error=str(exc), success=False)
                sys.exit(2)
            if ("internalservererror" in exc_name or "serviceunavailable" in exc_name or
                    "503" in msg or "500" in msg):
                _alert_admin(f"GA4 server error; queued for next cron: {exc}")
                _upsert_sync_status("ga4", "delayed", last_error=str(exc), success=False)
                sys.exit(1)
            _upsert_sync_status("ga4", "delayed", last_error=str(exc), success=False)
            logger.error("GA4 pull failed (queued for next cron): %s", exc, exc_info=True)
            sys.exit(1)
    else:
        _upsert_sync_status("ga4", "delayed", last_error="429 retry exhausted", success=False)
        _alert_admin("GA4 429 retry exhausted; queued for next cron")
        sys.exit(1)

    logger.info("GA4 API returned %s landingPage rows (before SKU URL matching)", len(api_rows))

    if not api_rows:
        logger.warning("No GA4 rows returned for %s → %s", window_start, window_end)
        return

    lookup = load_sku_url_lookup()
    base_url = (Config.CIE_LANDING_BASE_URL or os.environ.get("CIE_LANDING_BASE_URL", "")).rstrip("/")

    out_rows: List[Ga4DbRow] = []
    unmatched: List[str] = []

    for row in api_rows:
        raw_landing = (row.landing_page or "").strip()
        if raw_landing.startswith("http://") or raw_landing.startswith("https://"):
            full_url = raw_landing
        elif base_url:
            full_url = f"{base_url}{raw_landing if raw_landing.startswith('/') else '/' + raw_landing}"
        else:
            full_url = raw_landing if raw_landing.startswith("/") else f"/{raw_landing}"

        normalised = normalise_url(full_url)
        matched = match_url(normalised, lookup) if normalised else None
        if matched is None:
            unmatched.append(normalised or raw_landing)
            continue
        internal_id, _tier = matched
        sessions = int(row.sessions or 0)
        conversions = float(row.conversions or 0.0)
        revenue = float(row.revenue or 0.0)
        conv_rate = _compute_conversion_rate(conversions, sessions)
        out_rows.append(
            Ga4DbRow(
                sku_id=internal_id,
                landing_page=normalised,
                sessions=sessions,
                organic_conversions=conversions,
                conversion_rate=conv_rate,
                revenue=revenue,
                bounce_rate=float(row.bounce_rate or 0.0),
            )
        )

    save_ga4_landing_performance(out_rows, end_dt)
    _save_unmatched_urls(unmatched, end_dt)
    _upsert_sync_status("ga4", "ok", success=True)

    logger.info(
        "Weekly GA4 sync completed: %s ga4_landing_performance rows, %s unmatched URLs (API had %s rows)",
        len(out_rows),
        len(unmatched),
        len(api_rows),
    )


if __name__ == "__main__":
    run()
