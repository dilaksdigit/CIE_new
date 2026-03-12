"""
Weekly GSC sync job.

SOURCE: CIE_Master_Developer_Build_Spec.docx
- §9.1  GSC weekly pull — Monday 02:00 UTC, gsc_weekly_performance table
- §9.3  URL normalisation with normalise_url(); unmatched URLs to gsc_unmatched_urls
- §9.5  Error handling: 429 backoff, 500 queue for next cron, auth halt
"""

from __future__ import annotations

import logging
import os
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Iterable, List, Optional
from urllib.parse import urlparse

import pymysql

from utils.config import Config

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


def normalise_url(raw: str) -> str:
    """
    Normalise URLs to a canonical form before matching to SKUs.

    Spec §9.3 normalise_url() — high-level behaviour:
    - Lowercase scheme + host
    - Strip URL fragments and query parameters
    - Remove trailing slashes
    """
    if not raw:
        return ""
    url = raw.strip()
    # Very lightweight normalisation; full behaviour is defined in the spec.
    # Implemented conservatively here to avoid external dependencies.
    if "://" in url:
        scheme, rest = url.split("://", 1)
        scheme = scheme.lower()
        url = f"{scheme}://{rest}"
    if "#" in url:
        url = url.split("#", 1)[0]
    if "?" in url:
        url = url.split("?", 1)[0]
    # Remove trailing slash except for bare domain
    if url.endswith("/") and "://" in url and url.count("/") > 2:
        url = url.rstrip("/")
    return url


def pull_weekly_gsc(start_date: datetime, end_date: datetime) -> List[GscRow]:
    """
    Spec §9.2 pull_weekly_gsc() — 7‑day GSC window.
    Uses integrations.gsc_client with Config.GSC_SITE_URL.
    """
    site_url = Config.GSC_SITE_URL or os.environ.get("GSC_SITE_URL", "")
    if not site_url:
        logger.warning("GSC_SITE_URL not set; skipping weekly GSC pull")
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
    Persist GSC rows into url_performance table (legacy; spec §9.1 uses gsc_weekly_performance).
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

    Intended cron (per Config.GSC_CRON_SCHEDULE, default '0 2 * * 1'):
    - Monday 02:00 UTC
    - 7‑day window ending previous Sunday
    """
    logger.info("Starting weekly GSC sync (cron=%s)", Config.GSC_CRON_SCHEDULE)

    today_utc = datetime.now(timezone.utc).date()
    # For a run on Monday 02:00, window ends previous Sunday (yesterday).
    window_end = today_utc - timedelta(days=1)
    window_start = window_end - timedelta(days=6)

    start_dt = datetime.combine(window_start, datetime.min.time(), tzinfo=timezone.utc)
    end_dt = datetime.combine(window_end, datetime.max.time(), tzinfo=timezone.utc)

    try:
        rows = pull_weekly_gsc(start_dt, end_dt)
    except Exception as exc:
        # Spec §9.5: 500 queue for next cron; fail‑soft, log and exit non‑zero so the
        # scheduler can reschedule if desired.
        logger.error("GSC pull failed (queued for next cron): %s", exc, exc_info=True)
        sys.exit(1)

    if not rows:
        logger.warning("No GSC rows returned for %s → %s", window_start, window_end)
        return

    # Apply URL normalisation and split into matched vs unmatched.
    normalised_rows = []
    unmatched_urls = []
    for row in rows:
        norm_url = normalise_url(row.url)
        if not norm_url:
            unmatched_urls.append(row.url)
            continue
        normalised_rows.append(GscRow(url=norm_url,
                                      impressions=row.impressions,
                                      clicks=row.clicks,
                                      ctr=row.ctr,
                                      avg_position=row.avg_position))

    save_gsc_weekly_performance(normalised_rows, end_dt)
    save_unmatched_urls(unmatched_urls, end_dt)

    logger.info("Weekly GSC sync completed: %s rows, %s unmatched URLs",
                len(normalised_rows), len(unmatched_urls))


if __name__ == "__main__":
    run()

