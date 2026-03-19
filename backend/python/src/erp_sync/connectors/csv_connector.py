"""
ERP CSV Connector — reads ERP export CSVs, maps columns, validates, and archives.

Env vars:
  ERP_CSV_DIRECTORY       Directory to scan for *.csv files (default: storage/erp_imports)
  ERP_CSV_COLUMN_MAP      Optional column rename map, e.g. "sku_id:SKU Code,cppc:CostPerClick"
"""

import glob
import logging
import os
import shutil
from datetime import datetime
from typing import Dict, List

import pandas as pd

logger = logging.getLogger(__name__)

REQUIRED_FIELDS = ["sku_id", "contribution_margin_pct", "cppc", "velocity_90d", "return_rate_pct"]

DEFAULT_COLUMN_MAP = {
    "sku_id": "sku_id",
    "contribution_margin_pct": "contribution_margin_pct",
    "cppc": "cppc",
    "velocity_90d": "velocity_90d",
    "return_rate_pct": "return_rate_pct",
}


class CSVConnector:
    def __init__(self):
        self.csv_dir = os.getenv("ERP_CSV_DIRECTORY", "storage/erp_imports")
        self.archive_dir = os.path.join(self.csv_dir, "processed")
        self.column_map = self._load_column_map()

    def _load_column_map(self) -> Dict[str, str]:
        """Build {cie_field: csv_header} from env override, falling back to defaults."""
        mapping = dict(DEFAULT_COLUMN_MAP)
        env_map = os.getenv("ERP_CSV_COLUMN_MAP", "")
        if env_map:
            for pair in env_map.split(","):
                if ":" in pair:
                    cie_col, csv_col = pair.strip().split(":", 1)
                    mapping[cie_col.strip()] = csv_col.strip()
        return mapping

    def fetch(self) -> List[Dict]:
        """
        Read the most recent CSV from ERP_CSV_DIRECTORY, parse and validate rows,
        archive the file, and return a list of dicts matching the /erp/sync payload schema.
        """
        if not os.path.isdir(self.csv_dir):
            logger.warning("ERP CSV directory does not exist: %s", self.csv_dir)
            return []

        csv_files = sorted(glob.glob(os.path.join(self.csv_dir, "*.csv")))
        csv_files = [f for f in csv_files if not f.startswith(self.archive_dir)]
        if not csv_files:
            logger.info("No CSV files found in %s", self.csv_dir)
            return []

        latest = csv_files[-1]
        logger.info("Reading ERP CSV: %s", latest)

        try:
            df = pd.read_csv(latest, dtype=str)
        except Exception as exc:
            logger.error("Failed to read CSV %s: %s", latest, exc)
            return []

        df.columns = df.columns.str.strip()

        reverse_map = {csv_col: cie_col for cie_col, csv_col in self.column_map.items()}
        rename = {csv_h: cie_f for csv_h, cie_f in reverse_map.items() if csv_h in df.columns and csv_h != cie_f}
        if rename:
            df = df.rename(columns=rename)

        missing = [c for c in REQUIRED_FIELDS if c not in df.columns]
        if missing:
            logger.error(
                "CSV missing required columns after mapping: %s  (available: %s)",
                missing,
                list(df.columns),
            )
            return []

        rows: List[Dict] = []
        skipped = 0

        for idx, row in df.iterrows():
            sku_id = str(row.get("sku_id", "")).strip()
            if not sku_id:
                skipped += 1
                continue
            try:
                margin = float(row["contribution_margin_pct"])
                cppc = float(row["cppc"])
                velocity = float(row["velocity_90d"])
                return_rate = float(row["return_rate_pct"])
            except (ValueError, TypeError) as exc:
                logger.warning("Row %d: non-numeric value for SKU %s — %s", idx, sku_id, exc)
                skipped += 1
                continue

            if margin < -100 or margin > 100:
                logger.warning("Row %d: margin %.2f out of range for SKU %s", idx, margin, sku_id)
                skipped += 1
                continue
            if cppc < 0 or velocity < 0 or return_rate < 0:
                logger.warning("Row %d: negative value for SKU %s", idx, sku_id)
                skipped += 1
                continue

            rows.append({
                "sku_id": sku_id,
                "contribution_margin_pct": round(margin, 2),
                "cppc": round(cppc, 4),
                "velocity_90d": round(velocity, 2),
                "return_rate_pct": round(return_rate, 2),
            })

        self._archive(latest)
        logger.info("Parsed %d valid rows, skipped %d from %s", len(rows), skipped, os.path.basename(latest))
        return rows

    def _archive(self, file_path: str) -> None:
        """Move processed CSV into the archive subdirectory with a timestamp prefix."""
        try:
            os.makedirs(self.archive_dir, exist_ok=True)
            ts = datetime.utcnow().strftime("%Y%m%dT%H%M%S")
            dest = os.path.join(self.archive_dir, f"{ts}_{os.path.basename(file_path)}")
            shutil.move(file_path, dest)
            logger.info("Archived %s → %s", file_path, dest)
        except Exception as exc:
            logger.warning("Failed to archive %s: %s", file_path, exc)
