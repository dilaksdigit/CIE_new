"""
Standalone GA4 connection test — invoked by: php artisan test:gsc-ga4-live

Reads GOOGLE_SERVICE_ACCOUNT_JSON (inline JSON or path to JSON file) and GA4_PROPERTY_ID.
Prints PASS|... or FAIL|... on stdout; exit code 0 on PASS, 1 on FAIL.
"""

from __future__ import annotations

import json
import os
import sys

try:
    from google.analytics.data_v1beta import BetaAnalyticsDataClient
    from google.analytics.data_v1beta.types import (
        DateRange,
        Dimension,
        Filter,
        FilterExpression,
        Metric,
        RunReportRequest,
    )
    from google.oauth2 import service_account
except ImportError as e:
    print(f"FAIL|import_error:{e}")
    sys.exit(1)


def _load_credentials():
    raw = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON") or ""
    raw = raw.strip()
    if not raw:
        raise RuntimeError("GOOGLE_SERVICE_ACCOUNT_JSON is not set")
    if raw.startswith("{"):
        info = json.loads(raw)
    elif os.path.isfile(raw):
        with open(raw, encoding="utf-8") as f:
            info = json.load(f)
    else:
        raise RuntimeError("GOOGLE_SERVICE_ACCOUNT_JSON is not valid JSON and not a file path")
    return service_account.Credentials.from_service_account_info(
        info,
        scopes=["https://www.googleapis.com/auth/analytics.readonly"],
    )


def main() -> None:
    prop = (os.environ.get("GA4_PROPERTY_ID") or "").strip()
    if not prop:
        print("FAIL|GA4_PROPERTY_ID unset")
        sys.exit(1)
    prop_api = f"properties/{prop}" if not prop.startswith("properties/") else prop

    try:
        creds = _load_credentials()
        client = BetaAnalyticsDataClient(credentials=creds)
        request = RunReportRequest(
            property=prop_api,
            date_ranges=[DateRange(start_date="7daysAgo", end_date="yesterday")],
            dimensions=[Dimension(name="landingPage")],
            metrics=[Metric(name="sessions")],
            dimension_filter=FilterExpression(
                filter=Filter(
                    field_name="sessionDefaultChannelGroup",
                    string_filter=Filter.StringFilter(
                        match_type=Filter.StringFilter.MatchType.EXACT,
                        value="Organic Search",
                    ),
                )
            ),
            limit=5,
        )
        response = client.run_report(request=request)
    except Exception as e:
        print(f"FAIL|{e!s}")
        sys.exit(1)

    rows = list(response.rows or [])
    row_count = int(response.row_count or len(rows))
    if row_count <= 0 and not rows:
        print("FAIL|no_organic_data")
        sys.exit(1)

    total_sessions = 0
    for row in rows:
        if row.metric_values:
            try:
                total_sessions += int(row.metric_values[0].value or 0)
            except (TypeError, ValueError):
                pass

    if total_sessions <= 0:
        print("FAIL|organic_sessions_zero")
        sys.exit(1)

    print(f"PASS|rows={row_count}|organic_sessions={total_sessions}")


if __name__ == "__main__":
    main()
