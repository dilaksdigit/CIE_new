"""
Google Analytics Data API (GA4) client for CIE.

Uses run_report with landingPage dimension and Organic Search filter.
Credentials via GOOGLE_SERVICE_ACCOUNT_JSON or Config.GOOGLE_SERVICE_ACCOUNT_JSON.
"""

from __future__ import annotations

import logging
import os
from dataclasses import dataclass
from datetime import date, datetime
from typing import List, Optional

logger = logging.getLogger(__name__)


@dataclass
class Ga4Snapshot:
    """Single landing-page GA4 metrics (for CIS D+15/D+30)."""
    sessions: int
    bounce_rate: float
    conversion_rate: float | None
    revenue: float


@dataclass
class Ga4Row:
    """One row for weekly bulk pull (landing_page + metrics)."""
    landing_page: str
    sessions: int
    conversions: float  # count; job may convert to rate
    revenue: float
    bounce_rate: float


def _get_client():
    """Build GA4 Data API client with default credentials."""
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §10.1
    # Same service account as GSC; explicit analytics.readonly scope.
    from google.analytics.data_v1beta import BetaAnalyticsDataClient
    from google.oauth2 import service_account

    creds_path = None
    try:
        from utils.config import Config
        creds_path = Config.GOOGLE_SERVICE_ACCOUNT_JSON
    except Exception:
        pass
    if not creds_path:
        creds_path = os.environ.get("GSC_SERVICE_ACCOUNT_JSON")
    if not creds_path:
        creds_path = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not creds_path:
        creds_path = os.environ.get("GOOGLE_APPLICATION_CREDENTIALS")
    if creds_path and os.path.isfile(creds_path):
        creds = service_account.Credentials.from_service_account_file(
            creds_path,
            scopes=["https://www.googleapis.com/auth/analytics.readonly"],
        )
        return BetaAnalyticsDataClient(credentials=creds)
    return BetaAnalyticsDataClient()


def pull_ga4_for_landing_page(
    property_id: str,
    landing_page_url: str,
    start_date: date,
    end_date: date,
) -> Optional[Ga4Snapshot]:
    """
    Fetch GA4 metrics for a single landing page, Organic Search only.
    Returns None on missing config, API error, or no data.
    """
    if not property_id or not landing_page_url:
        return None
    try:
        from google.analytics.data_v1beta.types import (
            DateRange,
            Dimension,
            Filter,
            FilterExpression,
            FilterExpressionList,
            Metric,
            RunReportRequest,
        )
        client = _get_client()
        prop = f"properties/{property_id}" if not property_id.startswith("properties/") else property_id
        request = RunReportRequest(
            property=prop,
            dimensions=[Dimension(name="landingPage")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="conversions"),
                Metric(name="bounceRate"),
                # SOURCE: CIE_Master_Developer_Build_Spec.docx §10.2 (metric order)
                Metric(name="purchaseRevenue"),
            ],
            date_ranges=[DateRange(start_date=start_date.isoformat(), end_date=end_date.isoformat())],
            dimension_filter=FilterExpression(
                and_group=FilterExpressionList(
                    expressions=[
                        FilterExpression(
                            filter=Filter(
                                field_name="sessionDefaultChannelGroup",
                                string_filter=Filter.StringFilter(
                                    match_type=Filter.StringFilter.MatchType.EXACT,
                                    value="Organic Search",
                                ),
                            )
                        ),
                        FilterExpression(
                            filter=Filter(
                                field_name="landingPage",
                                string_filter=Filter.StringFilter(
                                    match_type=Filter.StringFilter.MatchType.EXACT,
                                    value=landing_page_url,
                                ),
                            )
                        ),
                    ]
                )
            ),
        )
        response = client.run_report(request)
        if not response.rows:
            return None
        row = response.rows[0]
        sessions = int(row.metric_values[0].value or 0)
        conversions = float(row.metric_values[1].value or 0)
        bounce_rate = float(row.metric_values[2].value or 0)
        revenue = float(row.metric_values[3].value or 0)
        conv_rate = round((conversions / sessions), 6) if sessions else None
        return Ga4Snapshot(sessions=sessions, bounce_rate=bounce_rate, conversion_rate=conv_rate, revenue=revenue)
    except Exception as exc:
        logger.warning("GA4 pull_ga4_for_landing_page failed for url=%s: %s", landing_page_url, exc)
        return None


def pull_weekly_ga4_rows(
    property_id: str,
    start_date: datetime,
    end_date: datetime,
) -> List[Ga4Row]:
    """
    Fetch GA4 metrics by landing page for the date range, Organic Search only.
    Returns list of Ga4Row (conversions = count; job may compute rate).
    """
    if not property_id:
        return []
    try:
        from google.analytics.data_v1beta.types import (
            DateRange,
            Dimension,
            Filter,
            FilterExpression,
            Metric,
            RunReportRequest,
        )
        client = _get_client()
        prop = f"properties/{property_id}" if not property_id.startswith("properties/") else property_id
        request = RunReportRequest(
            property=prop,
            dimensions=[Dimension(name="landingPage")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="conversions"),
                Metric(name="bounceRate"),
                # SOURCE: CIE_Master_Developer_Build_Spec.docx §10.2 (metric order)
                Metric(name="purchaseRevenue"),
            ],
            date_ranges=[
                DateRange(
                    start_date=start_date.strftime("%Y-%m-%d"),
                    end_date=end_date.strftime("%Y-%m-%d"),
                )
            ],
            dimension_filter=FilterExpression(
                filter=Filter(
                    field_name="sessionDefaultChannelGroup",
                    string_filter=Filter.StringFilter(
                        match_type=Filter.StringFilter.MatchType.EXACT,
                        value="Organic Search",
                    ),
                )
            ),
            # SOURCE: CIE_Master_Developer_Build_Spec.docx §10.2
            limit=25000,
        )
        logger.info(
            "GA4 RunReport: property=%s start=%s end=%s organic_filter=sessionDefaultChannelGroup EXACT 'Organic Search'",
            prop,
            start_date.strftime("%Y-%m-%d"),
            end_date.strftime("%Y-%m-%d"),
        )
        response = client.run_report(request)
        raw_rows = response.rows or []
        logger.info("GA4 RunReport raw row count: %s", len(raw_rows))
        out = []
        for row in raw_rows:
            landing = (row.dimension_values[0].value or "").strip()
            sessions = int(row.metric_values[0].value or 0)
            conversions = float(row.metric_values[1].value or 0)
            bounce_rate = float(row.metric_values[2].value or 0)
            revenue = float(row.metric_values[3].value or 0)
            out.append(
                Ga4Row(
                    landing_page=landing,
                    sessions=sessions,
                    conversions=conversions,
                    revenue=revenue,
                    bounce_rate=bounce_rate,
                )
            )
        return out
    except Exception as exc:
        logger.warning("GA4 pull_weekly_ga4_rows failed: %s", exc)
        raise
