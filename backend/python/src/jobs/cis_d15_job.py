"""
CIS D+15 measurement job.

SOURCE: CIE_Master_Developer_Build_Spec.docx — Layer L8 (D+15)
- Runs at D+15 after each content change to capture GSC + GA4 metrics.
- Compares against gsc_baselines row created at publish time.
"""

from __future__ import annotations

import logging
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Iterable, List, Optional

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


@dataclass
class BaselineRow:
    id: int
    sku_id: str
    url: str  # landing URL associated with this SKU/baseline


@dataclass
class GscSnapshot:
    impressions: float
    clicks: float
    ctr: float
    position: float


@dataclass
class Ga4Snapshot:
    sessions: int
    conversion_rate: float
    revenue: float


def get_due_baselines(target_date: datetime) -> List[BaselineRow]:
    """
    Fetch gsc_baselines rows where:
    - baseline_captured_at is exactly 15 days before 'now' (date match),
    - and all d15_* columns are NULL.

    NOTE: This is a DB access stub; wire it to your ORM/DB layer.
    """
    logger.info("get_due_baselines(D+15) stub for date=%s", target_date.date())
    return []


def pull_current_gsc(url: str) -> Optional[GscSnapshot]:
    """
    Pull current GSC metrics for a single URL.

    NOTE: Integration with the actual GSC API is environment‑specific and
    intentionally left as a stub here.
    """
    logger.info("pull_current_gsc stub for url=%s", url)
    return None


def pull_current_ga4(url: str) -> Optional[Ga4Snapshot]:
    """
    Pull current GA4 Organic Search metrics for the landing page (url).

    NOTE: Integration with GA4 is environment‑specific and intentionally left
    as a stub here.
    """
    logger.info("pull_current_ga4 stub for url=%s", url)
    return None


def update_d15_columns(row: BaselineRow,
                       gsc: Optional[GscSnapshot],
                       ga4: Optional[Ga4Snapshot]) -> None:
    """
    Persist D+15 metrics into the gsc_baselines row.

    Fail‑soft rule: if either source returns no data, mark as 'no_data' via
    logging / status fields and do not raise.
    """
    # TODO: Replace this stub with real UPDATE gsc_baselines ... SET d15_* WHERE id = row.id
    if gsc is None or ga4 is None:
        logger.warning("D+15 no_data for baseline_id=%s sku_id=%s (gsc=%s ga4=%s)",
                       row.id, row.sku_id, bool(gsc), bool(ga4))
        return

    logger.info(
        "Updating D+15 metrics for baseline_id=%s sku_id=%s (impr=%.2f clicks=%.2f ctr=%.4f pos=%.2f sess=%d cr=%.4f rev=%.2f)",
        row.id,
        row.sku_id,
        gsc.impressions,
        gsc.clicks,
        gsc.ctr,
        gsc.position,
        ga4.sessions,
        ga4.conversion_rate,
        ga4.revenue,
    )


def write_audit_log(sku_id: str, message: str) -> None:
    """
    Emit an audit_log entry for the SKU.

    NOTE: This is a stub placeholder; wire it to the backend API or DB layer.
    """
    logger.info("AUDIT sku_id=%s message=%s", sku_id, message)


def run() -> None:
    """
    Entrypoint for the D+15 CIS measurement job.

    For each baseline whose baseline_captured_at is 15 days ago and whose
    D+15 columns are NULL, pulls fresh GSC + GA4 metrics and writes d15_*.
    """
    now = datetime.now(timezone.utc)
    target_date = (now - timedelta(days=15)).date()
    logger.info("Starting CIS D+15 job for baseline date=%s", target_date)

    baselines = get_due_baselines(now - timedelta(days=15))
    if not baselines:
        logger.info("No baselines due for D+15 measurement.")
        return

    for row in baselines:
        try:
            gsc = pull_current_gsc(row.url)
            ga4 = pull_current_ga4(row.url)

            update_d15_columns(row, gsc, ga4)
            write_audit_log(row.sku_id, f"D+15 CIS metrics captured for baseline_id={row.id}")
        except Exception as exc:
            # Fail‑soft: log error, mark as no_data, continue.
            logger.error("D+15 processing failed for baseline_id=%s sku_id=%s: %s",
                         row.id, row.sku_id, exc, exc_info=True)
            write_audit_log(row.sku_id, f"D+15 CIS metrics failed (no_data) for baseline_id={row.id}")

    logger.info("CIS D+15 job completed.")


if __name__ == "__main__":
    run()

