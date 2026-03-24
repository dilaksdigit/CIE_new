"""
CIS D+30 measurement job.

SOURCE: CIE_Master_Developer_Build_Spec.docx — Layer L8 (D+30)
- Runs at D+30 after each content change to capture GSC + GA4 metrics.
- Computes Change Impact Score (CIS) by comparing D+30 vs baseline.
"""

from __future__ import annotations

import logging
import os
from dataclasses import dataclass
from datetime import date, datetime, timedelta, timezone
from typing import List, Optional
from urllib.parse import urlparse

import pymysql

from utils.business_rules import get_business_rule

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


@dataclass
class BaselineRow:
    id: int
    sku_id: str
    url: str
    baseline_avg_position: Optional[float]
    baseline_ctr: Optional[float]
    baseline_impressions: Optional[float]
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
    bounce_rate: float
    conversion_rate: float
    revenue: float


def _get_db():
    """PEP-249 connection — same pattern as api.gates_validate."""
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


def get_due_baselines(target_date: datetime) -> List[BaselineRow]:
    """
    Fetch gsc_baselines rows where:
    - baseline_captured_at date is 30 days before today (target_date),
    - all d30_* columns are NULL.
    Include baseline_* for CIS computation.
    """
    try:
        from utils.config import Config
        base_url = Config.CIE_LANDING_BASE_URL or os.environ.get("CIE_LANDING_BASE_URL", "").rstrip("/")
    except Exception:
        base_url = os.environ.get("CIE_LANDING_BASE_URL", "").rstrip("/")
    if not base_url:
        logger.warning("CIE_LANDING_BASE_URL not set; cannot derive landing URLs for D+30")
        return []
    db = _get_db()
    try:
        cur = db.cursor()
        cur.execute(
            """
            SELECT b.id, b.sku_id, s.sku_code,
                   b.baseline_avg_position, b.baseline_ctr, b.baseline_impressions, b.baseline_conversion_rate
            FROM gsc_baselines b
            JOIN skus s ON s.id = b.sku_id
            WHERE DATE(b.baseline_captured_at) = %s
              AND b.d30_impressions IS NULL
            """,
            (target_date.date(),),
        )
        rows = cur.fetchall()
        cur.close()
        return [
            BaselineRow(
                id=row["id"],
                sku_id=row["sku_id"],
                url=f"{base_url}/{row['sku_code']}",
                baseline_avg_position=float(row["baseline_avg_position"]) if row.get("baseline_avg_position") is not None else None,
                baseline_ctr=float(row["baseline_ctr"]) if row.get("baseline_ctr") is not None else None,
                baseline_impressions=float(row["baseline_impressions"]) if row.get("baseline_impressions") is not None else None,
                baseline_conversion_rate=float(row["baseline_conversion_rate"]) if row.get("baseline_conversion_rate") is not None else None,
            )
            for row in rows
        ]
    finally:
        db.close()


def pull_current_gsc(url: str) -> Optional[GscSnapshot]:
    """
    Pull current GSC metrics for the URL (baseline lookback window ending today).

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 sync.baseline_lookback_weeks
    FIX: CIS-02 — lookback from business_rules, not hardcoded 14 days
    """
    try:
        from utils.config import Config
        from integrations.gsc_client import pull_gsc_for_page
        site_url = Config.GSC_PROPERTY or os.environ.get("GSC_PROPERTY", "")
        if not site_url:
            return None
        end = date.today()
        lookback_weeks = int(get_business_rule("sync.baseline_lookback_weeks", 2))
        start = end - timedelta(days=lookback_weeks * 7)
        return pull_gsc_for_page(site_url, url, start, end)
    except Exception as exc:
        logger.warning("pull_current_gsc failed for url=%s: %s", url, exc)
        return None


def pull_current_ga4(url: str) -> Optional[Ga4Snapshot]:
    """
    Pull current GA4 metrics for the landing page URL.

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 sync.baseline_lookback_weeks
    FIX: CIS-02 — lookback from business_rules, not hardcoded 14 days
    """
    try:
        from utils.config import Config
        from integrations.ga4_client import pull_ga4_for_landing_page
        property_id = Config.GA4_PROPERTY_ID or os.environ.get("GA4_PROPERTY_ID", "")
        if not property_id:
            return None
        end = date.today()
        lookback_weeks = int(get_business_rule("sync.baseline_lookback_weeks", 2))
        start = end - timedelta(days=lookback_weeks * 7)
        return pull_ga4_for_landing_page(property_id, url, start, end)
    except Exception as exc:
        logger.warning("pull_current_ga4 failed for url=%s: %s", url, exc)
        return None


def compute_cis_score(baseline_avg_position: Optional[float],
                      d30_avg_position: Optional[float],
                      baseline_ctr: Optional[float],
                      d30_ctr: Optional[float],
                      baseline_impressions: Optional[float],
                      d30_impressions: Optional[float],
                      baseline_conversion_rate: Optional[float],
                      d30_conversion_rate: Optional[float]) -> Optional[float]:
    """
    Compute Change Impact Score (CIS) from baseline vs D+30.
    SOURCE: CIE_Master_Developer_Build_Spec.docx §13.1
    """
    from api.gates_validate import BusinessRules

    if (baseline_avg_position is None and baseline_ctr is None
            and baseline_impressions is None and baseline_conversion_rate is None):
        return None

    score = 0

    if baseline_avg_position is not None and d30_avg_position is not None:
        pos_large_min = int(BusinessRules.get('cis.position_improvement_large_min'))
        pos_improvement = baseline_avg_position - d30_avg_position
        if pos_improvement >= pos_large_min:
            score += int(BusinessRules.get('cis.position_improvement_large_pts'))
        elif pos_improvement >= 1:
            score += int(BusinessRules.get('cis.position_improvement_small_pts'))

    if d30_ctr and baseline_ctr and d30_ctr > baseline_ctr:
        score += int(BusinessRules.get('cis.ctr_improvement_pts'))

    if d30_impressions and baseline_impressions and d30_impressions > baseline_impressions:
        score += int(BusinessRules.get('cis.impressions_improvement_pts'))

    if d30_conversion_rate and baseline_conversion_rate:
        if d30_conversion_rate > baseline_conversion_rate:
            score += int(BusinessRules.get('cis.conversion_rate_improvement_pts'))

    return score


def update_d30_and_cis(row: BaselineRow,
                       gsc: Optional[GscSnapshot],
                       ga4: Optional[Ga4Snapshot]) -> None:
    """
    Persist D+30 metrics and CIS score into gsc_baselines.
    Fail‑soft: on missing data, log and skip update.
    """
    if gsc is None or ga4 is None:
        logger.warning("D+30 no_data for baseline_id=%s sku_id=%s (gsc=%s ga4=%s)",
                       row.id, row.sku_id, bool(gsc), bool(ga4))
        return

    cis = compute_cis_score(
        baseline_avg_position=row.baseline_avg_position,
        d30_avg_position=gsc.position if gsc else None,
        baseline_ctr=row.baseline_ctr,
        d30_ctr=gsc.ctr if gsc else None,
        baseline_impressions=row.baseline_impressions,
        d30_impressions=gsc.impressions if gsc else None,
        baseline_conversion_rate=row.baseline_conversion_rate,
        d30_conversion_rate=ga4.conversion_rate if ga4 else None,
    )

    db = _get_db()
    try:
        cur = db.cursor()
        cur.execute(
            """
            UPDATE gsc_baselines SET
              d30_impressions = %s, d30_clicks = %s, d30_ctr = %s, d30_position = %s,
              d30_sessions = %s, d30_conversion_rate = %s, d30_revenue = %s,
              cis_score = %s, cis_status = %s
            WHERE id = %s
            """,
            (
                gsc.impressions, gsc.clicks, gsc.ctr, gsc.position,
                ga4.sessions, ga4.conversion_rate, ga4.revenue,
                cis, 'complete',
                row.id,
            ),
        )
        db.commit()
        cur.close()
        logger.info(
            "Updated D+30 for baseline_id=%s sku_id=%s (impr=%.2f clicks=%.2f ctr=%.4f pos=%.2f sess=%d cr=%.4f rev=%.2f cis=%s)",
            row.id, row.sku_id,
            gsc.impressions, gsc.clicks, gsc.ctr, gsc.position,
            ga4.sessions, ga4.conversion_rate, ga4.revenue,
            f"{cis:.4f}" if cis is not None else "None",
        )
    finally:
        db.close()


def write_audit_log(sku_id: str, message: str) -> None:
    """Emit an audit_log entry for the SKU."""
    try:
        db = _get_db()
        cur = db.cursor()
        cur.execute(
            "INSERT INTO audit_log (entity_type, entity_id, action, timestamp, created_at) "
            "VALUES (%s, %s, %s, NOW(), NOW())",
            ("gate_status", sku_id, message[:255]),
        )
        db.commit()
        cur.close()
        db.close()
    except Exception as exc:
        logger.debug("audit_log write failed: %s", exc)


def run() -> None:
    """
    Entrypoint for the D+30 CIS measurement job.
    For each baseline whose baseline_captured_at is 30 days ago and whose
    D+30 columns are NULL, pulls fresh GSC + GA4 metrics, writes d30_*, and
    computes cis_score.

    SOURCE: CIE_Master_Developer_Build_Spec.docx §11 step 7; §5.3 cis.measurement_window_d30
    FIX: CIS-01 — D+30 offset from business_rules (not hardcoded 30)
    NOTE: cis_status column vs spec measurement_status — see GAP_LOG GAP-P6-2
    """
    now = datetime.now(timezone.utc)
    d30_days = int(get_business_rule("cis.measurement_window_d30", 30))
    target_date = (now - timedelta(days=d30_days)).date()
    logger.info("Starting CIS D+30 job for baseline date=%s (cis.measurement_window_d30=%s)", target_date, d30_days)

    baselines = get_due_baselines(now - timedelta(days=d30_days))
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
