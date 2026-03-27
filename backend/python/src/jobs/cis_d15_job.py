"""
CIS D+15 measurement job.

SOURCE: CIE_Master_Developer_Build_Spec.docx — Layer L8 (D+15)
- Runs at D+15 after each content change to capture GSC + GA4 metrics.
- Compares against gsc_baselines row created at publish time.
"""

from __future__ import annotations

from . import _bootstrap  # noqa: F401 — sys.path + load repo .env

import logging
import os
from dataclasses import dataclass
from datetime import date, datetime, timedelta, timezone
from typing import List, Optional
from urllib.parse import urlparse

from api.gates_validate import BusinessRules
from utils.business_rules import get_business_rule
from utils.mysql_connect import pymysql_connect_dict_cursor
from utils.url_utils import normalise_url

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
    bounce_rate: float
    conversion_rate: float | None
    revenue: float


def _get_db():
    """PEP-249 connection — same pattern as api.gates_validate."""
    return pymysql_connect_dict_cursor()


def get_due_baselines(target_date: datetime) -> List[BaselineRow]:
    """
    Fetch gsc_baselines rows where:
    - baseline_captured_at date is target_date,
    - all d15_* columns are NULL.
    Join skus to get sku_code; URL = CIE_LANDING_BASE_URL + '/' + sku_code.
    """
    try:
        from utils.config import Config
        base_url = Config.CIE_LANDING_BASE_URL or os.environ.get("CIE_LANDING_BASE_URL", "").rstrip("/")
    except Exception:
        base_url = os.environ.get("CIE_LANDING_BASE_URL", "").rstrip("/")
    if not base_url:
        logger.warning("CIE_LANDING_BASE_URL not set; cannot derive landing URLs for D+15")
        return []
    db = _get_db()
    try:
        cur = db.cursor()
        cur.execute(
            """
            SELECT b.id, b.sku_id, s.sku_code
            FROM gsc_baselines b
            JOIN skus s ON s.id = b.sku_id
            WHERE DATE(b.baseline_captured_at) = %s
              AND b.d15_impressions IS NULL
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
            )
            for row in rows
        ]
    finally:
        db.close()


def pull_current_gsc(url: str) -> Optional[GscSnapshot]:
    """
    Pull current GSC metrics for a single URL (baseline lookback window ending today).

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 sync.baseline_lookback_weeks
    FIX: CIS-02 — lookback from business_rules, not hardcoded 14 days
    """
    try:
        from utils.config import Config
        from integrations.gsc_client import pull_gsc_for_page
        site_url = Config.GSC_PROPERTY or os.environ.get("GSC_PROPERTY", "")
        if not site_url:
            logger.debug("GSC_PROPERTY not set")
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
    Pull current GA4 Organic Search metrics for the landing page (url).

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 sync.baseline_lookback_weeks
    FIX: CIS-02 — lookback from business_rules, not hardcoded 14 days
    """
    try:
        from utils.config import Config
        from integrations.ga4_client import pull_ga4_for_landing_page
        property_id = Config.GA4_PROPERTY_ID or os.environ.get("GA4_PROPERTY_ID", "")
        if not property_id:
            logger.debug("GA4_PROPERTY_ID not set")
            return None
        # SOURCE: CIE_Master_Developer_Build_Spec.docx §9.3
        norm = normalise_url(url)
        parsed = urlparse(norm)
        landing_path = parsed.path if parsed.path else "/"
        end = date.today()
        lookback_weeks = int(get_business_rule("sync.baseline_lookback_weeks", 2))
        start = end - timedelta(days=lookback_weeks * 7)
        return pull_ga4_for_landing_page(property_id, landing_path, start, end)
    except Exception as exc:
        logger.warning("pull_current_ga4 failed for url=%s: %s", url, exc)
        return None


def update_d15_columns(row: BaselineRow,
                       gsc: Optional[GscSnapshot],
                       ga4: Optional[Ga4Snapshot]) -> None:
    """
    Persist D+15 metrics into the gsc_baselines row.
    Fail‑soft: if either source returns no data, log and skip update.
    """
    if gsc is None or ga4 is None:
        logger.warning("D+15 no_data for baseline_id=%s sku_id=%s (gsc=%s ga4=%s)",
                       row.id, row.sku_id, bool(gsc), bool(ga4))
        return
    db = _get_db()
    try:
        cur = db.cursor()
        cur.execute(
            """
            UPDATE gsc_baselines SET
              d15_impressions = %s, d15_clicks = %s, d15_ctr = %s, d15_position = %s,
              d15_organic_sessions = %s, d15_conversion_rate = %s, d15_revenue = %s,
              measurement_status = %s
            WHERE id = %s
            """,
            # SOURCE: CIE_Master_Developer_Build_Spec.docx §6.4
            (
                gsc.impressions, gsc.clicks, gsc.ctr, gsc.position,
                ga4.sessions, ga4.conversion_rate, ga4.revenue,
                "d15_captured",
                row.id,
            ),
        )
        db.commit()
        cur.close()
        logger.info(
            "Updated D+15 for baseline_id=%s sku_id=%s (impr=%.2f clicks=%.2f ctr=%.4f pos=%.2f sess=%d cr=%.4f rev=%.2f)",
            row.id, row.sku_id,
            gsc.impressions, gsc.clicks, gsc.ctr, gsc.position,
            ga4.sessions, ga4.conversion_rate, ga4.revenue,
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
    Entrypoint for the D+15 CIS measurement job.
    For each baseline whose baseline_captured_at is 15 days ago and whose
    D+15 columns are NULL, pulls fresh GSC + GA4 metrics and writes d15_*.
    """
    now = datetime.now(timezone.utc)
    d15_days = int(BusinessRules.get('cis.measurement_window_d15'))
    target_date = (now - timedelta(days=d15_days)).date()
    logger.info("Starting CIS D+15 job for baseline date=%s", target_date)

    baselines = get_due_baselines(now - timedelta(days=d15_days))
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
            logger.error("D+15 processing failed for baseline_id=%s sku_id=%s: %s",
                         row.id, row.sku_id, exc, exc_info=True)
            write_audit_log(row.sku_id, f"D+15 CIS metrics failed (no_data) for baseline_id={row.id}")

    logger.info("CIS D+15 job completed.")


if __name__ == "__main__":
    run()
