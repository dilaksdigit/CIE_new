"""
ERP sync job — reads commercial data from the configured ERP connector
(CSV, REST, or ODBC) and pushes it to the PHP /api/v1/erp/sync endpoint
which handles validation, tier recalculation, and audit logging.

Env vars:
  ERP_SYNC_ENABLED         "true" (default) or "false" to skip
  ERP_CONNECTOR            "csv" (default), "rest", or "odbc"
  CIE_CMS_URL              PHP backend base URL (default: http://localhost:8000)
  CIE_INTERNAL_API_KEY     Bearer token for internal API calls

Intended cron (business rule sync.erp_cron_schedule = '0 2 1 * *'):
  1st of every month, 02:00 UTC
Admin can also trigger manually via POST /api/admin/erp-sync.
"""

from __future__ import annotations

import logging
import os
import sys
from datetime import datetime, timezone

import requests

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


def _get_connector():
    """Instantiate the configured ERP connector."""
    connector_type = os.getenv("ERP_CONNECTOR", "csv").lower().strip()

    if connector_type == "rest":
        from src.erp_sync.connectors.rest_connector import RESTConnector
        return RESTConnector()
    elif connector_type == "odbc":
        from src.erp_sync.connectors.odbc_connector import ODBCConnector
        return ODBCConnector()
    else:
        from src.erp_sync.connectors.csv_connector import CSVConnector
        return CSVConnector()


def run() -> None:
    logger.info("Starting nightly ERP sync")

    if os.getenv("ERP_SYNC_ENABLED", "true").lower() == "false":
        logger.info("ERP_SYNC_ENABLED=false — skipping")
        return

    connector = _get_connector()
    logger.info("Using ERP connector: %s", type(connector).__name__)

    rows = connector.fetch()
    if not rows:
        logger.warning("No ERP rows returned — nothing to sync")
        return

    logger.info("Fetched %d SKU rows from ERP connector", len(rows))

    payload = {
        "sync_date": datetime.now(timezone.utc).strftime("%Y-%m-%d"),
        "skus": rows,
    }

    cms_url = os.getenv("CIE_CMS_URL", "http://localhost:8000").rstrip("/")
    erp_url = f"{cms_url}/api/v1/erp/sync"
    api_key = os.getenv("CIE_INTERNAL_API_KEY", "")

    headers = {"Content-Type": "application/json"}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"

    try:
        resp = requests.post(erp_url, json=payload, headers=headers, timeout=120)

        if resp.status_code == 200:
            result = resp.json()
            logger.info(
                "ERP sync complete — processed: %d, tier changes: %d, errors: %d",
                result.get("skus_processed", 0),
                result.get("tier_changes", 0),
                len(result.get("errors", [])),
            )
            for err in result.get("errors", [])[:20]:
                logger.warning("  sync error: %s", err)
        elif resp.status_code == 422:
            logger.error("ERP sync validation failed (422): %s", resp.text[:500])
            sys.exit(1)
        else:
            logger.error("ERP sync API returned %d: %s", resp.status_code, resp.text[:500])
            sys.exit(1)

    except requests.ConnectionError:
        logger.error("Cannot reach CIE PHP backend at %s — is it running?", erp_url)
        sys.exit(1)
    except requests.Timeout:
        logger.error("ERP sync request timed out after 120s")
        sys.exit(1)
    except Exception as exc:
        logger.error("ERP sync failed: %s", exc, exc_info=True)
        sys.exit(1)


if __name__ == "__main__":
    run()
