"""
Build URL → sku_master lookups for GSC/GA4 weekly sync.

SOURCE: CIE_Master_Developer_Build_Spec.docx §6.1 (shopify_url), §9.3 (normalise_url).
"""

from __future__ import annotations

import logging
import os
from dataclasses import dataclass, field
from typing import Dict, Optional, Tuple

from urllib.parse import urlparse

from utils.mysql_connect import pymysql_connect_dict_cursor
from utils.url_utils import normalise_url

logger = logging.getLogger(__name__)


def _resolved_shopify_url(raw: str) -> str:
    raw = (raw or "").strip()
    if not raw:
        return ""
    if raw.startswith("http://") or raw.startswith("https://"):
        return raw
    base = (os.environ.get("CIE_LANDING_BASE_URL") or "").rstrip("/")
    if not base:
        return raw if raw.startswith("/") else f"/{raw}"
    return f"{base}{raw if raw.startswith('/') else '/' + raw}"


def path_key(normalised_full_url: str) -> str:
    """Lowercase path, no trailing slash (for cross-host matching)."""
    if not normalised_full_url:
        return ""
    p = urlparse(normalised_full_url)
    return (p.path or "").lower().rstrip("/")


@dataclass
class SkuUrlLookup:
    """Maps normalised full URLs and path keys to (sku_master.id, tier)."""

    full_url_to_id_tier: Dict[str, Tuple[str, str]] = field(default_factory=dict)
    path_to_id_tier: Dict[str, Tuple[str, str]] = field(default_factory=dict)


def log_sku_master_url_diagnostics() -> None:
    """Log counts and sample shopify_url values (§9.3 / ops visibility)."""
    try:
        db = pymysql_connect_dict_cursor()
        try:
            cur = db.cursor()
            cur.execute(
                """
                SELECT COUNT(*) AS total_skus,
                       SUM(CASE WHEN shopify_url IS NOT NULL AND TRIM(shopify_url) <> '' THEN 1 ELSE 0 END) AS skus_with_url,
                       SUM(CASE WHEN tier IN ('hero','support') THEN 1 ELSE 0 END) AS hero_support_skus
                FROM sku_master
                """
            )
            row = cur.fetchone()
            cur.close()
            if row:
                logger.info(
                    "sku_master diagnostics: total_skus=%s skus_with_shopify_url=%s hero_support_skus=%s",
                    row.get("total_skus"),
                    row.get("skus_with_url"),
                    row.get("hero_support_skus"),
                )
            cur = db.cursor()
            cur.execute(
                """
                SELECT shopify_url FROM sku_master
                WHERE shopify_url IS NOT NULL AND TRIM(shopify_url) <> ''
                LIMIT 3
                """
            )
            samples = [r.get("shopify_url") for r in cur.fetchall()]
            cur.close()
            if samples:
                logger.info("sku_master shopify_url samples (up to 3): %s", samples)
        finally:
            db.close()
    except Exception as exc:
        logger.warning("sku_master diagnostic query failed: %s", exc)


def load_sku_url_lookup() -> SkuUrlLookup:
    lookup = SkuUrlLookup()
    db = pymysql_connect_dict_cursor()
    try:
        cur = db.cursor()
        cur.execute(
            """
            SELECT id, tier, shopify_url
            FROM sku_master
            WHERE shopify_url IS NOT NULL AND TRIM(shopify_url) <> ''
            """
        )
        rows = cur.fetchall()
        cur.close()
        for row in rows:
            internal_id = str(row.get("id") or "")
            tier = (row.get("tier") or "").lower()
            raw = (row.get("shopify_url") or "").strip()
            full = _resolved_shopify_url(raw)
            norm = normalise_url(full)
            if not norm:
                continue
            lookup.full_url_to_id_tier[norm] = (internal_id, tier)
            pk = path_key(norm)
            if pk:
                if pk not in lookup.path_to_id_tier:
                    lookup.path_to_id_tier[pk] = (internal_id, tier)
                else:
                    prev_id, _ = lookup.path_to_id_tier[pk]
                    if prev_id != internal_id:
                        logger.debug(
                            "sku_master path collision for %s (ids %s vs %s); keeping first",
                            pk,
                            prev_id,
                            internal_id,
                        )
        return lookup
    except Exception as exc:
        err_no = getattr(exc, "args", [None])[0]
        msg = str(exc).lower()
        if err_no == 1054 or "shopify_url" in msg and "unknown column" in msg:
            logger.warning(
                "sku_master.shopify_url unavailable (%s). Run migration 107_add_missing_columns_to_sku_master.sql. "
                "URL matching disabled until column exists.",
                exc,
            )
            return lookup
        raise
    finally:
        db.close()


def match_url(norm_api_url: str, lookup: SkuUrlLookup) -> Optional[Tuple[str, str]]:
    """
    Return (sku_master.id, tier) if norm_api_url matches a catalogued shopify_url
    (full normalised URL first, then path-only).
    """
    if not norm_api_url:
        return None
    hit = lookup.full_url_to_id_tier.get(norm_api_url)
    if hit:
        return hit
    pk = path_key(norm_api_url)
    if pk:
        hit = lookup.path_to_id_tier.get(pk)
        if hit:
            return hit
    return None
