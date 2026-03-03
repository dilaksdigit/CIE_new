"""
Weekly GA4 sync job.

SOURCE: CIE_Master_Developer_Build_Spec.docx
- §10.2  GA4 weekly pull — Monday 03:00 UTC, 24h after GSC
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
class Ga4Row:
    landing_page: str
    sessions: int
    conversions: float
    revenue: float


def pull_weekly_ga4(start_date: datetime, end_date: datetime) -> List[Ga4Row]:
    """
    Spec §10.2: GA4 weekly pull for Organic Search channel only, using landingPage dimension.

    This function is a stub hook for the real GA4 API client; the job's scheduling,
    error handling and persistence are implemented around this.
    """
    # TODO: Implement GA4 API call using landingPage dimension, Organic Search only.
    logger.info("pull_weekly_ga4(%s → %s) stub called; no-op in this environment", start_date.date(), end_date.date())
    return []


def handle_rate_limit_retry(retry_after_seconds: Optional[int] = None) -> None:
    """
    Simple helper mirroring the GSC job's 429 backoff behaviour.
    """
    delay = retry_after_seconds or 60
    logger.warning("GA4 API returned 429 — backing off for %s seconds before retry", delay)
    time.sleep(delay)


def save_ga4_landing_performance(rows: Iterable[Ga4Row], window_end: datetime) -> None:
    """
    Persist GA4 landing performance into ga4_landing_performance table.

    DB integration is environment‑specific; this is a stub for the project's DB layer.
    """
    count = sum(1 for _ in rows)
    logger.info("ga4_landing_performance write stub — %s rows for window ending %s", count, window_end.date())


def run() -> None:
    """
    Entrypoint for the weekly GA4 sync.

    Intended cron (per Config.GA4_CRON_SCHEDULE, default '0 3 * * 1'):
    - Monday 03:00 UTC
    - 7‑day window aligned with previous GSC pull (end on Sunday)
    """
    logger.info("Starting weekly GA4 sync (cron=%s)", Config.GA4_CRON_SCHEDULE)

    today_utc = datetime.now(timezone.utc).date()
    # For a run on Monday, previous Sunday is yesterday.
    window_end = today_utc - timedelta(days=1)
    window_start = window_end - timedelta(days=6)

    start_dt = datetime.combine(window_start, datetime.min.time(), tzinfo=timezone.utc)
    end_dt = datetime.combine(window_end, datetime.max.time(), tzinfo=timezone.utc)

    try:
        rows = pull_weekly_ga4(start_dt, end_dt)
    except Exception as exc:
        # Fail‑soft: log and exit non‑zero so scheduler can requeue; never blocks content ops.
        logger.error("GA4 pull failed (queued for next cron): %s", exc, exc_info=True)
        sys.exit(1)

    if not rows:
        logger.warning("No GA4 rows returned for %s → %s", window_start, window_end)
        return

    # Compute conversion_rate = conversions / sessions (not GA4's own rate).
    out_rows: List[Ga4Row] = []
    for row in rows:
        sessions = int(row.sessions or 0)
        conversions = float(row.conversions or 0.0)
        revenue = float(row.revenue or 0.0)
        if sessions <= 0:
            conv_rate = 0.0
        else:
            conv_rate = conversions / sessions
        out_rows.append(Ga4Row(landing_page=row.landing_page,
                               sessions=sessions,
                               conversions=conv_rate,
                               revenue=revenue))

    save_ga4_landing_performance(out_rows, end_dt)

    logger.info("Weekly GA4 sync completed: %s rows", len(out_rows))


if __name__ == "__main__":
    run()

