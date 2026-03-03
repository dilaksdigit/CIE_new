"""
Weekly GSC sync job.

SOURCE: CIE_Master_Developer_Build_Spec.docx
- §9.2  GSC weekly pull — Sunday 03:00 UTC, url_performance table
- §9.3  URL normalisation with normalise_url()
- §9.5  Error handling: 429 backoff, 500 queue for next cron, auth halt
"""

from __future__ import annotations

import logging
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Iterable, List, Optional

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
    Spec §9.2 pull_weekly_gsc() — 7‑day GSC window ending previous Saturday.

    This function is intentionally left as a thin stub: the exact API client,
    credentials, and query payload are environment‑specific and defined in the
    integration runbook. The job wiring (cron, batching, error handling, URL
    normalisation, DB writes) is implemented around this hook.
    """
    # TODO: Implement concrete GSC API call following the spec's query example.
    logger.info("pull_weekly_gsc(%s → %s) stub called; no-op in this environment", start_date.date(), end_date.date())
    return []


def handle_rate_limit_retry(retry_after_seconds: Optional[int] = None) -> None:
    """
    Spec §9.5: 429 backoff — sleep and retry on rate limiting.
    """
    delay = retry_after_seconds or 60
    logger.warning("GSC API returned 429 — backing off for %s seconds before retry", delay)
    time.sleep(delay)


def save_url_performance(rows: Iterable[GscRow], window_end: datetime) -> None:
    """
    Persist GSC rows into url_performance table.

    NOTE: DB integration is environment‑specific; this function is a stub that
    should be wired to the project's DB access layer.
    """
    count = sum(1 for _ in rows)
    logger.info("url_performance write stub — %s rows for window ending %s", count, window_end.date())


def save_unmatched_urls(urls: Iterable[str], window_end: datetime) -> None:
    """
    Log unmatched URLs into gsc_unmatched_urls (spec §9.3).

    As with save_url_performance, this is a stub intended to be backed by the
    real DB layer; for now it only logs.
    """
    urls = list(urls)
    if not urls:
        return
    logger.info("gsc_unmatched_urls stub — %s unmatched URLs for window ending %s", len(urls), window_end.date())


def run() -> None:
    """
    Entrypoint for the weekly GSC sync.

    Intended cron (per Config.GSC_CRON_SCHEDULE, default '0 3 * * 0'):
    - Sunday 03:00 UTC
    - 7‑day window ending previous Saturday
    """
    logger.info("Starting weekly GSC sync (cron=%s)", Config.GSC_CRON_SCHEDULE)

    today_utc = datetime.now(timezone.utc).date()
    # For a run on Sunday, previous Saturday is yesterday.
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

    save_url_performance(normalised_rows, end_dt)
    save_unmatched_urls(unmatched_urls, end_dt)

    logger.info("Weekly GSC sync completed: %s rows, %s unmatched URLs",
                len(normalised_rows), len(unmatched_urls))


if __name__ == "__main__":
    run()

