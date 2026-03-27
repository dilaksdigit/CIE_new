"""
Weekly GSC sync job.

SOURCE: CIE_Master_Developer_Build_Spec.docx
- §5.3  sync.gsc_cron_schedule — Sunday 03:00 UTC (host/env; see utils.config.Config)
- §9.2  Writes to url_performance (Hero + Support SKUs) and gsc_weekly_performance
- §9.3  URL normalisation; unmatched URLs to gsc_unmatched_urls
- §9.5  Error handling: 429 backoff, 500 queue for next cron, auth halt
"""

from __future__ import annotations

from . import _bootstrap  # noqa: F401 — sys.path + load repo .env

import logging
import os
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Iterable, List, Optional

import pymysql

from utils.config import Config
from utils.mysql_connect import pymysql_connect_dict_cursor
from utils.sku_master_url_lookup import load_sku_url_lookup, log_sku_master_url_diagnostics, match_url
from utils.url_utils import normalise_url

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


@dataclass
class GscRow:
    url: str
    impressions: float
    clicks: float
    ctr: float
    avg_position: float


def _get_db():
    return pymysql_connect_dict_cursor()


def pull_weekly_gsc(start_date: datetime, end_date: datetime) -> List[GscRow]:
    """
    Spec §9.2 pull_weekly_gsc() — 7‑day GSC window.
    Uses integrations.gsc_client with Config.GSC_PROPERTY.
    """
    site_url = Config.GSC_PROPERTY or os.environ.get("GSC_PROPERTY", "")
    if not site_url:
        logger.warning("GSC_PROPERTY not set; skipping weekly GSC pull")
        return []
    from integrations.gsc_client import pull_weekly_gsc_rows

    return pull_weekly_gsc_rows(site_url, start_date, end_date)


def handle_rate_limit_retry(retry_after_seconds: Optional[int] = None) -> None:
    """
    Spec §9.5: 429 backoff — sleep and retry on rate limiting.
    """
    delay = retry_after_seconds or 60
    logger.warning("GSC API returned 429 — backing off for %s seconds before retry", delay)
    time.sleep(delay)


def save_gsc_weekly_performance(rows: Iterable[GscRow], window_end: datetime) -> None:
    """
    Persist GSC rows into gsc_weekly_performance table (spec §9.1).
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
                INSERT INTO gsc_weekly_performance (url, window_end, impressions, clicks, ctr, avg_position)
                VALUES (%s, %s, %s, %s, %s, %s)
                """,
                (row.url, window_date, row.impressions, row.clicks, row.ctr, row.avg_position),
            )
        db.commit()
        cur.close()
        logger.info("gsc_weekly_performance: inserted %s rows for window_end=%s", len(rows_list), window_date)
    finally:
        db.close()


def save_url_performance(rows: Iterable[GscRow], window_end: datetime) -> None:
    """
    Persist GSC rows into url_performance (spec §9.2) — Hero + Support only (Phase 2.2).
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
                INSERT INTO url_performance (url, window_end, impressions, clicks, ctr, avg_position)
                VALUES (%s, %s, %s, %s, %s, %s)
                """,
                (row.url, window_date, row.impressions, row.clicks, row.ctr, row.avg_position),
            )
        db.commit()
        cur.close()
        logger.info("url_performance: inserted %s rows for window_end=%s", len(rows_list), window_date)
    finally:
        db.close()


def save_unmatched_urls(urls: Iterable[str], window_end: datetime) -> None:
    """
    Log unmatched URLs into gsc_unmatched_urls (spec §9.3). Does not raise; unmatched URLs never error the job.
    """
    urls_list = list(urls)
    if not urls_list:
        return
    window_date = window_end.date()
    try:
        db = _get_db()
        try:
            cur = db.cursor()
            for raw_url in urls_list:
                try:
                    cur.execute(
                        """
                        INSERT INTO gsc_unmatched_urls (url, source, window_end)
                        VALUES (%s, %s, %s)
                        """,
                        (raw_url[:1000], "gsc", window_date),
                    )
                except pymysql.err.OperationalError as exc:
                    if exc.args and exc.args[0] != 1054:
                        raise
                    cur.execute(
                        """
                        INSERT INTO gsc_unmatched_urls (url, window_end)
                        VALUES (%s, %s)
                        """,
                        (raw_url[:1000], window_date),
                    )
            db.commit()
            cur.close()
            logger.info("gsc_unmatched_urls: inserted %s rows for window_end=%s", len(urls_list), window_date)
        finally:
            db.close()
    except Exception as exc:
        logger.warning("gsc_unmatched_urls insert failed (non-fatal): %s", exc)


def run() -> None:
    """
    Entrypoint for the weekly GSC sync.

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — intended host schedule from Config.GSC_CRON_SCHEDULE / env
    7‑day window ending the day before the run (UTC).
    """
    logger.info("Starting weekly GSC sync (cron=%s)", Config.GSC_CRON_SCHEDULE)
    log_sku_master_url_diagnostics()

    today_utc = datetime.now(timezone.utc).date()
    window_end = today_utc - timedelta(days=1)
    window_start = window_end - timedelta(days=6)

    start_dt = datetime.combine(window_start, datetime.min.time(), tzinfo=timezone.utc)
    end_dt = datetime.combine(window_end, datetime.max.time(), tzinfo=timezone.utc)

    try:
        rows = pull_weekly_gsc(start_dt, end_dt)
    except Exception as exc:
        logger.error("GSC pull failed (queued for next cron): %s", exc, exc_info=True)
        sys.exit(1)

    if not rows:
        logger.warning("No GSC rows returned for %s → %s", window_start, window_end)
        return

    lookup = load_sku_url_lookup()
    has_catalog = len(lookup.full_url_to_id_tier) > 0

    normalised_rows: List[GscRow] = []
    unmatched_urls: List[str] = []
    url_perf_rows: List[GscRow] = []

    for row in rows:
        norm_url = normalise_url(row.url)
        if not norm_url:
            unmatched_urls.append(row.url)
            continue
        gr = GscRow(
            url=norm_url,
            impressions=row.impressions,
            clicks=row.clicks,
            ctr=row.ctr,
            avg_position=row.avg_position,
        )
        normalised_rows.append(gr)
        matched = match_url(norm_url, lookup)
        if not has_catalog or matched is None:
            unmatched_urls.append(row.url)
            continue
        _sku_id, tier = matched
        if tier in ("hero", "support"):
            url_perf_rows.append(gr)

    save_gsc_weekly_performance(normalised_rows, end_dt)
    save_url_performance(url_perf_rows, end_dt)
    save_unmatched_urls(unmatched_urls, end_dt)

    logger.info(
        "Weekly GSC sync completed: %s gsc_weekly rows, %s url_performance (hero/support), %s unmatched URLs",
        len(normalised_rows),
        len(url_perf_rows),
        len(unmatched_urls),
    )


if __name__ == "__main__":
    run()
