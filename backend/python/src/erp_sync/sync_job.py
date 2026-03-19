"""
ERP sync job — thin wrapper around CSVConnector for direct Python-side sync.

For the canonical nightly cron, use src.jobs.nightly_erp_sync which POSTs to the
PHP /api/v1/erp/sync endpoint (preferred path — gets full validation + tier recalc).

This module is for direct Python-side use (e.g. ad-hoc scripts or the TierRecalculator).
"""

import logging
from typing import List, Dict, Optional

from src.erp_sync.connectors.csv_connector import CSVConnector

logger = logging.getLogger(__name__)


def sync_from_csv(file_path: Optional[str] = None) -> List[Dict]:
    """
    Read ERP data via CSVConnector. If file_path is given, temporarily override
    the CSV directory to read that specific file.
    """
    connector = CSVConnector()
    if file_path:
        import os
        connector.csv_dir = os.path.dirname(file_path) or "."
    rows = connector.fetch()
    logger.info("sync_from_csv: %d rows fetched", len(rows))
    return rows
