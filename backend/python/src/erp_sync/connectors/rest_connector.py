"""
ERP REST Connector — pulls SKU commercial data from a client ERP's REST API.

Spec env vars (canonical):
  ERP_API_URL             Base URL of the ERP API (e.g. https://erp.company.com/api/v1/products)
  ERP_API_KEY             API key / Bearer token for the ERP API
  ERP_REST_SKU_PATH       JSONPath-like dot-notation to the SKU array in the response (default: "data")
  ERP_REST_FIELD_MAP      Field rename map, e.g. "sku_id:sku_code,cppc:cost_per_click"
"""

import logging
import os
from typing import Dict, List, Optional

import requests

logger = logging.getLogger(__name__)


def _resolve_path(obj, path: str):
    """Walk a dot-separated path into a nested dict/list."""
    for key in path.split("."):
        if isinstance(obj, dict):
            obj = obj.get(key)
        elif isinstance(obj, list) and key.isdigit():
            obj = obj[int(key)]
        else:
            return None
    return obj


class RESTConnector:
    def __init__(self):
        self.url = os.getenv("ERP_API_URL", "") or os.getenv("ERP_REST_URL", "")
        self.api_key = os.getenv("ERP_API_KEY", "") or os.getenv("ERP_REST_AUTH_HEADER", "")
        self.sku_path = os.getenv("ERP_REST_SKU_PATH", "data")
        self.field_map = self._load_field_map()

    def _load_field_map(self) -> Dict[str, str]:
        mapping: Dict[str, str] = {}
        env_map = os.getenv("ERP_REST_FIELD_MAP", "")
        if env_map:
            for pair in env_map.split(","):
                if ":" in pair:
                    cie_col, api_col = pair.strip().split(":", 1)
                    mapping[api_col.strip()] = cie_col.strip()
        return mapping

    def _build_headers(self) -> Dict[str, str]:
        headers = {"Accept": "application/json"}
        if self.api_key:
            if self.api_key.lower().startswith(("bearer ", "basic ")):
                headers["Authorization"] = self.api_key
            else:
                headers["Authorization"] = f"Bearer {self.api_key}"
        return headers

    def test_connection(self) -> Dict:
        """
        Spec: verify ERP API link via GET {ERP_API_URL}?limit=1.
        Returns {"ok": bool, "status_code": int, "message": str}.
        """
        if not self.url:
            return {"ok": False, "status_code": 0, "message": "ERP_API_URL not set"}

        separator = "&" if "?" in self.url else "?"
        test_url = f"{self.url}{separator}limit=1"

        try:
            resp = requests.get(test_url, headers=self._build_headers(), timeout=15)
            if resp.status_code == 200:
                return {"ok": True, "status_code": 200, "message": "Connection successful"}
            return {"ok": False, "status_code": resp.status_code, "message": resp.text[:200]}
        except requests.RequestException as exc:
            return {"ok": False, "status_code": 0, "message": str(exc)}

    def fetch(self) -> List[Dict]:
        if not self.url:
            logger.info("ERP_API_URL not set — REST connector disabled")
            return []

        test = self.test_connection()
        if not test["ok"]:
            logger.error("ERP REST test connection failed: %s", test["message"])
            return []

        headers = self._build_headers()

        try:
            resp = requests.get(self.url, headers=headers, timeout=60)
            resp.raise_for_status()
        except requests.RequestException as exc:
            logger.error("ERP REST request failed: %s", exc)
            return []

        body = resp.json()
        raw_rows = _resolve_path(body, self.sku_path)
        if not isinstance(raw_rows, list):
            logger.error("ERP REST: path '%s' did not resolve to a list (got %s)", self.sku_path, type(raw_rows))
            return []

        required = {"sku_id", "contribution_margin_pct", "cppc", "velocity_90d", "return_rate_pct"}
        rows: List[Dict] = []

        for item in raw_rows:
            if not isinstance(item, dict):
                continue
            mapped = {}
            for key, val in item.items():
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
                logger.warning("REST row skipped: %s — %s", mapped.get("sku_id"), exc)

        logger.info("ERP REST: fetched %d valid rows from %s", len(rows), self.url)
        return rows
