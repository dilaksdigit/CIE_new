"""
Shopify Admin product pull — populate sku_master.shopify_url / shopify_product_id
and backfill NULL sku_content fields from Shopify (read-mostly seed).

SOURCE: CIE_Master_Developer_Build_Spec.docx §6.1 (shopify_url, shopify_product_id)
SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §6.1 — SHOPIFY_STORE_DOMAIN, SHOPIFY_ADMIN_ACCESS_TOKEN, API 2024-01
SOURCE: CIE_v232_Developer_Self_Validation_Pack.docx Stage 1A — content parity fields where NULL only

Run:  python -m src.jobs.shopify_product_sync
"""

from __future__ import annotations

from . import _bootstrap  # noqa: F401 — sys.path + .env

import html
import json
import logging
import os
import re
import time
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Set, Tuple
from urllib.parse import urlparse

import pymysql
import requests

from utils.mysql_connect import pymysql_connect_dict_cursor

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")

API_VERSION = "2024-01"
MAX_PAGES = 200
REQUEST_INTERVAL_SEC = 0.51  # ~2 req/s per CLAUDE.md Shopify limit


def _store_domain() -> str:
    raw = (os.environ.get("SHOPIFY_STORE_DOMAIN") or "").strip()
    raw = re.sub(r"^https?://", "", raw, flags=re.I)
    return raw.rstrip("/")


def _stored_product_url_base() -> str:
    """
    Public product URL prefix for shopify_url column — align with GSC/GA4 when possible.

    If GSC_PROPERTY is set, use its scheme + host (same origin as Search Console URLs).
    Otherwise https://{SHOPIFY_STORE_DOMAIN} (no path).
    """
    gsc = (os.environ.get("GSC_PROPERTY") or "").strip()
    if gsc:
        if gsc.lower().startswith("sc-domain:"):
            host = gsc.split(":", 1)[-1].strip().rstrip("/")
            if host:
                return f"https://{host}"
        if "://" not in gsc:
            gsc = f"https://{gsc.lstrip('/')}"
        parsed = urlparse(gsc)
        if parsed.scheme and parsed.netloc:
            return f"{parsed.scheme}://{parsed.netloc}".rstrip("/")
    dom = _store_domain()
    return f"https://{dom}" if dom else ""


def _product_url(base: str, handle: Optional[str]) -> str:
    if not base or not handle:
        return ""
    return f"{base.rstrip('/')}/products/{handle.strip()}"


def _access_token() -> str:
    return (os.environ.get("SHOPIFY_ADMIN_ACCESS_TOKEN") or "").strip()


def _strip_html(body: Optional[str], max_len: Optional[int] = None) -> str:
    if not body:
        return ""
    text = re.sub(r"<[^>]+>", " ", body)
    text = html.unescape(text)
    text = re.sub(r"\s+", " ", text).strip()
    if max_len is not None and len(text) > max_len:
        text = text[: max_len - 3].rstrip() + "..."
    return text


def _parse_next_page_info(link_header: Optional[str]) -> Optional[str]:
    if not link_header:
        return None
    m = re.search(r"<[^>]+[?&]page_info=([^&>\"']+)[^>]*>;\s*rel=\"next\"", link_header)
    if m:
        return m.group(1)
    m = re.search(r"page_info=([^&\s\"']+)", link_header)
    if m:
        return m.group(1)
    return None


def _respect_shopify_rate_limit(response_headers: Dict[str, str]) -> None:
    """Leaky bucket: X-Shopify-Shop-Api-Call-Limit is typically used/max for the window."""
    raw = response_headers.get("X-Shopify-Shop-Api-Call-Limit") or response_headers.get(
        "x-shopify-shop-api-call-limit"
    )
    if not raw:
        time.sleep(REQUEST_INTERVAL_SEC)
        return
    m = re.match(r"^\s*(\d+)\s*/\s*(\d+)\s*$", str(raw))
    if not m:
        time.sleep(REQUEST_INTERVAL_SEC)
        return
    used, limit = int(m.group(1)), int(m.group(2))
    if limit <= 0:
        time.sleep(REQUEST_INTERVAL_SEC)
        return
    if used >= max(1, int(limit * 0.85)):
        time.sleep(2.0)
    else:
        time.sleep(REQUEST_INTERVAL_SEC)


# First page only: Shopify cursor pagination allows only `limit` + `page_info` on later pages.
_PRODUCT_FIELDS = (
    "id,handle,title,body_html,variants,images,"
    "metafields_global_title_tag,metafields_global_description_tag"
)


def _fetch_products_page(
    domain: str,
    token: str,
    page_info: Optional[str],
    limit: int = 250,
) -> Tuple[List[Dict[str, Any]], Optional[str], int, Dict[str, str]]:
    base = f"https://{domain}/admin/api/{API_VERSION}/products.json"
    lim = min(250, max(1, limit))
    if page_info:
        # Shopify: with page_info, only `limit` may accompany it.
        params: Dict[str, Any] = {"limit": lim, "page_info": page_info}
    else:
        params = {"limit": lim, "fields": _PRODUCT_FIELDS}
    headers = {
        "X-Shopify-Access-Token": token,
        "Content-Type": "application/json",
    }
    r = requests.get(base, params=params, headers=headers, timeout=60)
    rh = {k: v for k, v in r.headers.items()}
    link = r.headers.get("Link") or r.headers.get("link")
    next_info = _parse_next_page_info(link)
    if r.status_code != 200:
        logger.error("Shopify products request failed: HTTP %s", r.status_code)
        return [], None, r.status_code, rh
    data = r.json() or {}
    products = data.get("products") or []
    return products, next_info, r.status_code, rh


def _first_image_alt(product: Dict[str, Any]) -> str:
    images = product.get("images") or []
    if images and isinstance(images[0], dict):
        alt = images[0].get("alt")
        if alt:
            return str(alt).strip()[:200]
    img = product.get("image")
    if isinstance(img, dict) and img.get("alt"):
        return str(img["alt"]).strip()[:200]
    return ""


def _table_columns(table: str) -> Set[str]:
    db = pymysql_connect_dict_cursor()
    try:
        cur = db.cursor()
        cur.execute(
            """
            SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
            """,
            (table,),
        )
        rows = cur.fetchall()
        cur.close()
        return {r["COLUMN_NAME"] for r in rows}
    finally:
        db.close()


def _upsert_sync_timestamp(service: str = "shopify_product_pull") -> None:
    db = pymysql_connect_dict_cursor()
    try:
        cur = db.cursor()
        cur.execute(
            """
            INSERT INTO sync_status (service, status, last_success_at, last_error, last_error_at)
            VALUES (%s, 'ok', NOW(), NULL, NULL)
            ON DUPLICATE KEY UPDATE status='ok', last_success_at=NOW(), last_error=NULL, last_error_at=NULL
            """,
            (service,),
        )
        db.commit()
        cur.close()
    except Exception as exc:
        logger.warning(
            "sync_status update failed: %s. Writing fallback to audit_log.",
            exc,
        )
        try:
            db.rollback()
        except Exception:
            pass
        try:
            detail = json.dumps(
                {
                    "status": "success",
                    "service": service,
                    "synced_at": datetime.now(timezone.utc).isoformat(),
                    "error": str(exc),
                }
            )
            cur_fb = db.cursor()
            cur_fb.execute(
                """
                INSERT INTO audit_log (
                    entity_type, entity_id, action, field_name, old_value, new_value,
                    actor_id, actor_role, `timestamp`, user_id
                )
                VALUES (
                    'system', UUID(), 'shopify_product_sync_completed', NULL, NULL, %s,
                    NULL, 'system', UTC_TIMESTAMP(), NULL
                )
                """,
                (detail,),
            )
            db.commit()
            cur_fb.close()
        except Exception as fallback_err:
            logger.error("audit_log fallback also failed: %s", fallback_err)
    finally:
        db.close()


def run_shopify_product_sync() -> None:
    domain = _store_domain()
    token = _access_token()
    if not domain or not token:
        logger.error("SHOPIFY_STORE_DOMAIN and SHOPIFY_ADMIN_ACCESS_TOKEN must be set")
        return

    url_base = _stored_product_url_base()
    if not url_base:
        logger.error("Could not derive public URL base (set GSC_PROPERTY or SHOPIFY_STORE_DOMAIN)")
        return
    logger.info(
        "shopify_url will use base %s (from GSC_PROPERTY netloc if set, else SHOPIFY_STORE_DOMAIN)",
        url_base,
    )

    sm_cols = _table_columns("sku_master")
    if (
        "shopify_url" not in sm_cols
        or "shopify_product_id" not in sm_cols
        or "sku_code" not in sm_cols
    ):
        logger.error(
            "sku_master missing shopify_url, shopify_product_id, and/or sku_code — run "
            "database/migrations/107_add_missing_columns_to_sku_master.sql and "
            "database/migrations/139_add_sku_code_to_sku_master.sql"
        )
        return

    sc_cols = _table_columns("sku_content")
    can_meta_title = "meta_title" in sc_cols
    can_meta_desc = "meta_description" in sc_cols
    can_prod_desc = "product_description" in sc_cols
    can_alt = "alt_text" in sc_cols
    can_url_slug = "url_slug" in sc_cols

    total_shopify = 0
    matched = 0
    updated_url = 0
    content_backfilled = 0
    unmatched_products = 0

    page_info: Optional[str] = None
    pages = 0
    last_headers: Dict[str, str] = {}

    db = pymysql_connect_dict_cursor()
    try:
        while pages < MAX_PAGES:
            _respect_shopify_rate_limit(last_headers)
            products, next_page, status, last_headers = _fetch_products_page(domain, token, page_info)
            if status != 200:
                break
            pages += 1

            for product in products:
                total_shopify += 1
                handle = (product.get("handle") or "").strip() or None
                pid = product.get("id")
                title_raw = (product.get("title") or "").strip()
                body_html = product.get("body_html")
                variants = product.get("variants") or []
                public_url = _product_url(url_base, handle)
                shopify_product_id_str = str(pid) if pid is not None else ""

                seo_title = (product.get("metafields_global_title_tag") or "").strip()
                seo_desc = (product.get("metafields_global_description_tag") or "").strip()

                variant_skus = sorted({(v.get("sku") or "").strip() for v in variants if (v.get("sku") or "").strip()})
                product_matched_any = False
                has_variant_sku = bool(variant_skus)

                for v in variants:
                    variant_sku = (v.get("sku") or "").strip()
                    if not variant_sku:
                        continue
                    try:
                        cur = db.cursor()
                        # Match Shopify variant.sku to sku_master.sku_code (Master Build Spec §6.1).
                        cur.execute(
                            """
                            SELECT sku_id FROM sku_master
                            WHERE LOWER(TRIM(sku_code)) = LOWER(%s)
                            LIMIT 1
                            """,
                            (variant_sku,),
                        )
                        row = cur.fetchone()
                        cur.close()
                        if not row:
                            continue

                        sku_key = row["sku_id"]
                        product_matched_any = True
                        matched += 1

                        url_val = public_url[:1000]
                        pid_val = shopify_product_id_str[:50]
                        cur = db.cursor()
                        cur.execute(
                            """
                            UPDATE sku_master
                            SET shopify_url = %s,
                                shopify_product_id = %s,
                                updated_at = NOW()
                            WHERE sku_id = %s
                              AND (
                                shopify_url IS NULL OR shopify_url != %s
                                OR shopify_product_id IS NULL OR shopify_product_id != %s
                              )
                            """,
                            (url_val, pid_val, sku_key, url_val, pid_val),
                        )
                        if cur.rowcount:
                            updated_url += cur.rowcount
                        cur.close()

                        meta_title = (seo_title or title_raw)[:100] if (seo_title or title_raw) else ""
                        meta_description = seo_desc[:300] if seo_desc else ""
                        product_description = _strip_html(body_html) if body_html else ""
                        alt_text_val = _first_image_alt(product)
                        url_slug_val = (handle or "")[:255] if handle else ""

                        cur = db.cursor()
                        cur.execute("SELECT id FROM sku_content WHERE sku_id = %s LIMIT 1", (sku_key,))
                        if not cur.fetchone():
                            cur.close()
                            logger.info(
                                "sku_content row missing for sku_id=%s; skipping content backfill",
                                sku_key,
                            )
                            db.commit()
                            continue
                        cur.close()

                        if can_meta_title and meta_title:
                            cur = db.cursor()
                            cur.execute(
                                """
                                UPDATE sku_content SET meta_title = %s, updated_at = NOW()
                                WHERE sku_id = %s AND meta_title IS NULL
                                """,
                                (meta_title, sku_key),
                            )
                            content_backfilled += cur.rowcount
                            cur.close()
                        if can_meta_desc and meta_description:
                            cur = db.cursor()
                            cur.execute(
                                """
                                UPDATE sku_content SET meta_description = %s, updated_at = NOW()
                                WHERE sku_id = %s AND meta_description IS NULL
                                """,
                                (meta_description, sku_key),
                            )
                            content_backfilled += cur.rowcount
                            cur.close()
                        if can_prod_desc and product_description:
                            cur = db.cursor()
                            cur.execute(
                                """
                                UPDATE sku_content SET product_description = %s, updated_at = NOW()
                                WHERE sku_id = %s AND product_description IS NULL
                                """,
                                (product_description, sku_key),
                            )
                            content_backfilled += cur.rowcount
                            cur.close()
                        if can_alt and alt_text_val:
                            cur = db.cursor()
                            cur.execute(
                                """
                                UPDATE sku_content SET alt_text = %s, updated_at = NOW()
                                WHERE sku_id = %s AND alt_text IS NULL
                                """,
                                (alt_text_val[:200], sku_key),
                            )
                            content_backfilled += cur.rowcount
                            cur.close()
                        if can_url_slug and url_slug_val:
                            cur = db.cursor()
                            cur.execute(
                                """
                                UPDATE sku_content SET url_slug = %s, updated_at = NOW()
                                WHERE sku_id = %s AND url_slug IS NULL
                                """,
                                (url_slug_val, sku_key),
                            )
                            content_backfilled += cur.rowcount
                            cur.close()

                        db.commit()

                    except Exception as exc:
                        logger.warning("Sync failed for Shopify variant sku=%s: %s", variant_sku, exc)
                        db.rollback()

                if not product_matched_any and has_variant_sku:
                    unmatched_products += 1
                    logger.info(
                        "Unmatched Shopify product: '%s' (id=%s, variant_skus=%s) — "
                        "no matching sku_code in sku_master",
                        ((product.get("title") or "")[:200]),
                        pid,
                        variant_skus,
                    )

            page_info = next_page
            if not page_info or not products:
                break
    finally:
        db.close()

    logger.info("Fetched %s products from Shopify (%s pages)", total_shopify, pages)
    _upsert_sync_timestamp()
    logger.info(
        "Shopify product sync completed: "
        "%s products fetched, "
        "%s matched to sku_master, "
        "%s shopify_url populated, "
        "%s sku_content fields backfilled, "
        "%s unmatched Shopify products",
        total_shopify,
        matched,
        updated_url,
        content_backfilled,
        unmatched_products,
    )


# Backward-compatible name for callers
run = run_shopify_product_sync


if __name__ == "__main__":
    run_shopify_product_sync()
