"""
Google Search Console API client for CIE.

Uses Search Console API (webmasters v3) searchAnalytics.query.
Credentials via GOOGLE_SERVICE_ACCOUNT_JSON or Config.GOOGLE_SERVICE_ACCOUNT_JSON.
"""

from __future__ import annotations

import logging
from dataclasses import dataclass
from datetime import date, datetime
from typing import List, Optional

logger = logging.getLogger(__name__)


@dataclass
class GscSnapshot:
    """Single-URL GSC metrics (for CIS D+15/D+30)."""
    impressions: float
    clicks: float
    ctr: float
    position: float


@dataclass
class GscRow:
    """One row for weekly bulk pull (url + metrics)."""
    url: str
    impressions: float
    clicks: float
    ctr: float
    avg_position: float


def _get_service():
    """Build Search Console API service with default credentials."""
    import os
    from google.oauth2 import service_account
    from googleapiclient.discovery import build

    creds_path = None
    try:
        from utils.config import Config
        creds_path = Config.GOOGLE_SERVICE_ACCOUNT_JSON
    except Exception:
        pass
    if not creds_path:
        creds_path = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not creds_path:
        creds_path = os.environ.get("GOOGLE_APPLICATION_CREDENTIALS")
    if creds_path and os.path.isfile(creds_path):
        credentials = service_account.Credentials.from_service_account_file(creds_path)
    else:
        import google.auth
        credentials, _ = google.auth.default(scopes=["https://www.googleapis.com/auth/webmasters.readonly"])
    return build("webmasters", "v3", credentials=credentials, cache_discovery=False)


def pull_gsc_for_page(
    site_url: str,
    page_url: str,
    start_date: date,
    end_date: date,
) -> Optional[GscSnapshot]:
    """
    Fetch GSC metrics for a single page URL over the given date range.
    Returns None on missing config, API error, or no data.
    """
    if not site_url or not page_url:
        return None
    try:
        service = _get_service()
        body = {
            "startDate": start_date.isoformat(),
            "endDate": end_date.isoformat(),
            "dimensions": ["page"],
            "dimensionFilterGroups": [
                {
                    "filters": [
                        {
                            "dimension": "page",
                            "operator": "equals",
                            "expression": page_url,
                        }
                    ]
                }
            ],
        }
        response = service.searchanalytics().query(siteUrl=site_url, body=body).execute()
        rows = response.get("rows") or []
        if not rows:
            return None
        row = rows[0]
        impressions = float(row.get("impressions", 0))
        clicks = float(row.get("clicks", 0))
        ctr = float(row.get("ctr", 0))
        position = float(row.get("position", 0))
        return GscSnapshot(impressions=impressions, clicks=clicks, ctr=ctr, position=position)
    except Exception as exc:
        logger.warning("GSC pull_gsc_for_page failed for url=%s: %s", page_url, exc)
        return None


def pull_weekly_gsc_rows(
    site_url: str,
    start_date: datetime,
    end_date: datetime,
) -> List[GscRow]:
    """
    Fetch GSC metrics for all pages in the date range (7-day window).
    Returns list of GscRow; empty list on missing config or API error.
    """
    if not site_url:
        return []
    try:
        service = _get_service()
        body = {
            "startDate": start_date.strftime("%Y-%m-%d"),
            "endDate": end_date.strftime("%Y-%m-%d"),
            "dimensions": ["page"],
            "rowLimit": 25000,
        }
        response = service.searchanalytics().query(siteUrl=site_url, body=body).execute()
        rows = response.get("rows") or []
        out = []
        for row in rows:
            keys = row.get("keys") or []
            page_url = keys[0] if keys else ""
            if not page_url:
                continue
            out.append(
                GscRow(
                    url=page_url,
                    impressions=float(row.get("impressions", 0)),
                    clicks=float(row.get("clicks", 0)),
                    ctr=float(row.get("ctr", 0)),
                    avg_position=float(row.get("position", 0)),
                )
            )
        return out
    except Exception as exc:
        logger.warning("GSC pull_weekly_gsc_rows failed: %s", exc)
        return []
