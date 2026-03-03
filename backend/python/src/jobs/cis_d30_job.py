"""
CIS D+30 measurement job.

SOURCE: CIE_Master_Developer_Build_Spec.docx — Layer L8 (D+30)
- Runs at D+30 after each content change to capture GSC + GA4 metrics.
- Computes Change Impact Score (CIS) by comparing D+30 vs baseline.
"""

from __future__ import annotations

import logging
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Optional, List

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


@dataclass
class BaselineRow:
    id: int
    sku_id: str
    url: str
    baseline_clicks: Optional[float]
    baseline_conversion_rate: Optional[float]


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
    - baseline_captured_at is exactly 30 days before 'now',
    - and all d30_* columns are NULL.

    NOTE: This is a DB access stub; wire it to your ORM/DB layer.
    """
    logger.info("get_due_baselines(D+30) stub for date=%s", target_date.date())
    return []


def pull_current_gsc(url: str) -> Optional[GscSnapshot]:
    """
    Pull current GSC metrics for the URL.
    """
    logger.info("pull_current_gsc stub for url=%s", url)
    return None


def pull_current_ga4(url: str) -> Optional[Ga4Snapshot]:
    """
    Pull current GA4 metrics for the landing page URL.
    """
    logger.info("pull_current_ga4 stub for url=%s", url)
    return None


def compute_cis_score(baseline_clicks: Optional[float],
                      d30_clicks: Optional[float],
                      baseline_cr: Optional[float],
                      d30_cr: Optional[float]) -> Optional[float]:
    """
    Compute Change Impact Score (CIS) from baseline vs D+30.

    Spec defines CIS in terms of click and conversion rate uplift. A simple
    placeholder is implemented here; adjust to the exact formula from the spec:

        click_delta = (d30_clicks - baseline_clicks) / max(baseline_clicks, 1)
        cr_delta    = (d30_cr - baseline_cr) / max(baseline_cr, 1e-6)
        cis_score   = 0.5 * click_delta + 0.5 * cr_delta

    Returns None if required inputs are missing.
    """
    if baseline_clicks is None or d30_clicks is None or baseline_cr is None or d30_cr is None:
        return None
    try:
        click_delta = (d30_clicks - baseline_clicks) / max(baseline_clicks, 1.0)
        cr_delta = (d30_cr - baseline_cr) / max(baseline_cr, 1e-6)
        return 0.5 * click_delta + 0.5 * cr_delta
    except Exception:
        return None


def update_d30_and_cis(row: BaselineRow,
                       gsc: Optional[GscSnapshot],
                       ga4: Optional[Ga4Snapshot]) -> None:
    """
    Persist D+30 metrics and CIS score into gsc_baselines.

    Fail‑soft: on missing data, mark as 'no_data' via logging and do not raise.
    """
    if gsc is None or ga4 is None:
        logger.warning("D+30 no_data for baseline_id=%s sku_id=%s (gsc=%s ga4=%s)",
                       row.id, row.sku_id, bool(gsc), bool(ga4))
        # TODO: set a status column or metadata flag to 'no_data' when DB layer is wired.
        return

    cis = compute_cis_score(
        baseline_clicks=row.baseline_clicks,
        d30_clicks=gsc.clicks,
        baseline_cr=row.baseline_conversion_rate,
        d30_cr=ga4.conversion_rate,
    )

    logger.info(
        "Updating D+30 metrics for baseline_id=%s sku_id=%s (impr=%.2f clicks=%.2f ctr=%.4f pos=%.2f sess=%d cr=%.4f rev=%.2f cis=%s)",
        row.id,
        row.sku_id,
        gsc.impressions,
        gsc.clicks,
        gsc.ctr,
        gsc.position,
        ga4.sessions,
        ga4.conversion_rate,
        ga4.revenue,
        f"{cis:.4f}" if cis is not None else "None",
    )

    # TODO: UPDATE gsc_baselines SET d30_*=..., cis_score=cis WHERE id=row.id


def write_audit_log(sku_id: str, message: str) -> None:
    """
    Emit an audit_log entry for the SKU.
    """
    logger.info("AUDIT sku_id=%s message=%s", sku_id, message)


def run() -> None:
    """
    Entrypoint for the D+30 CIS measurement job.

    For each baseline whose baseline_captured_at is 30 days ago and whose
    D+30 columns are NULL, pulls fresh GSC + GA4 metrics, writes d30_*, and
    computes cis_score.
    """
    now = datetime.now(timezone.utc)
    target_date = (now - timedelta(days=30)).date()
    logger.info("Starting CIS D+30 job for baseline date=%s", target_date)

    baselines = get_due_baselines(now - timedelta(days=30))
    if not baselines:
        logger.info("No baselines due for D+30 measurement.")
        return

    for row in baselines:
        try:
            gsc = pull_current_gsc(row.url)
            ga4 = pull_current_ga4(row.url)
            update_d30_and_cis(row, gsc, ga4)
            write_audit_log(row.sku_id, f"D+30 CIS metrics captured for baseline_id={row.id}")
        except Exception as exc:
            logger.error("D+30 processing failed for baseline_id=%s sku_id=%s: %s",
                         row.id, row.sku_id, exc, exc_info=True)
            write_audit_log(row.sku_id, f"D+30 CIS metrics failed (no_data) for baseline_id={row.id}")

    logger.info("CIS D+30 job completed.")


if __name__ == "__main__":
    run()

