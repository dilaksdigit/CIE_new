"""
ERP ODBC Connector — pulls SKU commercial data directly from an ERP database via ODBC.

Env vars:
  ERP_CONNECTION_STRING   ODBC connection string (e.g. "Driver={SQL Server};Server=...;Database=...;")
  ERP_ODBC_QUERY          SQL query that returns sku_id, contribution_margin_pct, cppc, velocity_90d, return_rate_pct
  ERP_ODBC_FIELD_MAP      Optional field rename map, e.g. "sku_id:ItemCode,cppc:CostPerClick"
"""

import logging
import os
from typing import Dict, List

logger = logging.getLogger(__name__)

DEFAULT_QUERY = """
SELECT
    sku_id,
    contribution_margin_pct,
    cppc,
    velocity_90d,
    return_rate_pct
FROM erp_sku_commercial_view
"""


class ODBCConnector:
    def __init__(self):
        self.connection_string = os.getenv("ERP_CONNECTION_STRING", "")
        self.query = os.getenv("ERP_ODBC_QUERY", DEFAULT_QUERY).strip()
        self.field_map = self._load_field_map()

    def _load_field_map(self) -> Dict[str, str]:
        mapping: Dict[str, str] = {}
        env_map = os.getenv("ERP_ODBC_FIELD_MAP", "")
        if env_map:
            for pair in env_map.split(","):
                if ":" in pair:
                    cie_col, db_col = pair.strip().split(":", 1)
                    mapping[db_col.strip()] = cie_col.strip()
        return mapping

    def fetch_sku_data(self) -> List[Dict]:
        if not self.connection_string:
            logger.info("ERP_CONNECTION_STRING not set — ODBC connector disabled")
            return []

        try:
            import pyodbc
        except ImportError:
            logger.error("pyodbc not installed — cannot use ODBC connector (pip install pyodbc)")
            return []

        try:
            conn = pyodbc.connect(self.connection_string, timeout=30)
        except Exception as exc:
            logger.error("ODBC connection failed: %s", exc)
            return []

        required = {"sku_id", "contribution_margin_pct", "cppc", "velocity_90d", "return_rate_pct"}
        rows: List[Dict] = []

        try:
            cursor = conn.cursor()
            cursor.execute(self.query)
            columns = [desc[0] for desc in cursor.description]

            for db_row in cursor.fetchall():
                raw = dict(zip(columns, db_row))

                mapped = {}
                for key, val in raw.items():
                    cie_key = self.field_map.get(key, key)
                    mapped[cie_key] = val

                if not all(mapped.get(f) is not None for f in required):
                    continue

                try:
                    rows.append({
                        "sku_id": str(mapped["sku_id"]).strip(),
                        "contribution_margin_pct": round(float(mapped["contribution_margin_pct"]), 2),
                        "cppc": round(float(mapped["cppc"]), 4),
                        "velocity_90d": round(float(mapped["velocity_90d"]), 2),
                        "return_rate_pct": round(float(mapped["return_rate_pct"]), 2),
                    })
                except (ValueError, TypeError) as exc:
                    logger.warning("ODBC row skipped: %s — %s", mapped.get("sku_id"), exc)

            cursor.close()
        except Exception as exc:
            logger.error("ODBC query failed: %s", exc)
        finally:
            conn.close()

        logger.info("ERP ODBC: fetched %d valid rows", len(rows))
        return rows

    def fetch(self) -> List[Dict]:
        """Alias for fetch_sku_data() to match connector interface."""
        return self.fetch_sku_data()
