"""
Bridge data from skus -> sku_master / sku_content using internal DB joins.

Run:
    python -m src.jobs.skus_to_sku_master_bridge
"""

from __future__ import annotations

from . import _bootstrap  # noqa: F401

import json
import logging
import os
import re
import sys
from datetime import datetime, timezone
from html import unescape
from urllib.parse import urlparse

from utils.mysql_connect import pymysql_connect_dict_cursor

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


def _derive_base_url() -> str | None:
    gsc_property = (os.environ.get("GSC_PROPERTY") or "").strip().rstrip("/")
    if gsc_property:
        if gsc_property.startswith("sc-domain:"):
            domain = gsc_property.replace("sc-domain:", "", 1).strip().rstrip("/")
            return f"https://{domain}" if domain else None

        parsed = urlparse(gsc_property)
        if parsed.scheme and parsed.netloc:
            return f"{parsed.scheme}://{parsed.netloc}".rstrip("/")

        # Accept host-only values in case env omits scheme.
        host_only = gsc_property.lstrip("/")
        return f"https://{host_only}" if host_only else None

    shopify_domain = (os.environ.get("SHOPIFY_STORE_DOMAIN") or "").strip().rstrip("/")
    if shopify_domain:
        shopify_domain = re.sub(r"^https?://", "", shopify_domain, flags=re.I)
        return f"https://{shopify_domain}" if shopify_domain else None
    return None


def _strip_html_to_text(body_html: str) -> str:
    plain = re.sub(r"<[^>]+>", " ", body_html)
    plain = unescape(plain)
    plain = re.sub(r"\s+", " ", plain).strip()
    return plain


def _insert_audit_log(summary_json: str) -> None:
    db = pymysql_connect_dict_cursor()
    try:
        cur = db.cursor()
        try:
            cur.execute(
                """
                INSERT INTO audit_log (entity_type, entity_id, action, actor_role, detail)
                VALUES ('system', UUID(), 'skus_to_sku_master_bridge', 'system', %s)
                """,
                (summary_json,),
            )
        except Exception:
            # Fallback for deployments where audit_log uses alternate columns.
            cur.execute(
                """
                INSERT INTO audit_log (
                    entity_type, entity_id, action, field_name, old_value, new_value,
                    actor_id, actor_role, `timestamp`, user_id
                )
                VALUES (
                    'system', UUID(), 'skus_to_sku_master_bridge', NULL, NULL, %s,
                    NULL, 'system', UTC_TIMESTAMP(), NULL
                )
                """,
                (summary_json,),
            )
        db.commit()
        cur.close()
    finally:
        db.close()


def run_skus_to_sku_master_bridge() -> None:
    base_url = _derive_base_url()
    if not base_url:
        logger.error(
            "Cannot determine base URL: neither GSC_PROPERTY nor SHOPIFY_STORE_DOMAIN is set"
        )
        sys.exit(1)

    logger.info("Using base URL for shopify_url: %s", base_url)

    url_count = 0
    title_count = 0
    desc_count = 0
    prodesc_count = 0

    db = pymysql_connect_dict_cursor()
    try:
        cur = db.cursor()

        cur.execute(
            """
            UPDATE sku_master sm
            INNER JOIN skus s ON LOWER(TRIM(sm.sku_code)) = LOWER(TRIM(s.sku_code))
            SET
                sm.shopify_url = CONCAT(%s, '/products/', s.shopify_handle),
                sm.shopify_product_id = LEFT(s.shopify_product_id, 50),
                sm.updated_at = NOW()
            WHERE s.shopify_handle IS NOT NULL
              AND s.shopify_handle != ''
              AND (sm.shopify_url IS NULL OR sm.shopify_url = '')
            """,
            (base_url,),
        )
        url_count = cur.rowcount
        logger.info("sku_master: %s rows updated with shopify_url", url_count)

        cur.execute(
            """
            UPDATE sku_content sc
            INNER JOIN sku_master sm ON sc.sku_id = sm.sku_id
            INNER JOIN skus s ON LOWER(TRIM(sm.sku_code)) = LOWER(TRIM(s.sku_code))
            SET
                sc.meta_title = LEFT(COALESCE(NULLIF(s.meta_title, ''), s.shopify_title), 100),
                sc.updated_at = NOW()
            WHERE sc.meta_title IS NULL
              AND COALESCE(NULLIF(s.meta_title, ''), NULLIF(s.shopify_title, '')) IS NOT NULL
            """
        )
        title_count = cur.rowcount
        logger.info("sku_content.meta_title: %s rows backfilled", title_count)

        cur.execute(
            """
            UPDATE sku_content sc
            INNER JOIN sku_master sm ON sc.sku_id = sm.sku_id
            INNER JOIN skus s ON LOWER(TRIM(sm.sku_code)) = LOWER(TRIM(s.sku_code))
            SET
                sc.meta_description = LEFT(s.meta_description, 300),
                sc.updated_at = NOW()
            WHERE sc.meta_description IS NULL
              AND s.meta_description IS NOT NULL
              AND s.meta_description != ''
            """
        )
        desc_count = cur.rowcount
        logger.info("sku_content.meta_description: %s rows backfilled", desc_count)

        cur.execute(
            """
            SELECT sm.sku_id, s.shopify_body_html
            FROM sku_content sc
            INNER JOIN sku_master sm ON sc.sku_id = sm.sku_id
            INNER JOIN skus s ON LOWER(TRIM(sm.sku_code)) = LOWER(TRIM(s.sku_code))
            WHERE sc.product_description IS NULL
              AND s.shopify_body_html IS NOT NULL
              AND s.shopify_body_html != ''
            """
        )
        rows = cur.fetchall()
        for row in rows:
            sku_id = row["sku_id"]
            body_html = row["shopify_body_html"] or ""
            plain = _strip_html_to_text(str(body_html))
            if not plain:
                continue
            cur.execute(
                """
                UPDATE sku_content
                SET product_description = %s, updated_at = NOW()
                WHERE sku_id = %s AND product_description IS NULL
                """,
                (plain, sku_id),
            )
            prodesc_count += cur.rowcount
        logger.info("sku_content.product_description: %s rows backfilled", prodesc_count)

        db.commit()
        cur.close()
    except Exception:
        db.rollback()
        raise
    finally:
        db.close()

    summary = {
        "shopify_url_updated": url_count,
        "meta_title_backfilled": title_count,
        "meta_description_backfilled": desc_count,
        "product_description_backfilled": prodesc_count,
        "alt_text_backfilled": 0,
        "alt_text_note": "skipped (no source column in skus)",
        "synced_at": datetime.now(timezone.utc).isoformat(),
    }
    _insert_audit_log(json.dumps(summary))

    logger.info(
        "skus→sku_master bridge completed: %s shopify_url updated, %s meta_title backfilled, "
        "%s meta_description backfilled, %s product_description backfilled, "
        "alt_text skipped (no source column in skus)",
        url_count,
        title_count,
        desc_count,
        prodesc_count,
    )


if __name__ == "__main__":
    run_skus_to_sku_master_bridge()
