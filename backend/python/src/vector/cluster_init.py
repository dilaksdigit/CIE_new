"""
Backfill cluster centroid vectors from intent text (local dev + ops).

SOURCE: CIE_v232_Hardening_Addendum §1.1 — vector check requires cluster centroid.
SKUs use clusters.id (UUID) as primary_cluster_id; cluster_master.cluster_id is the CLU-* code.
"""
from __future__ import annotations

import json
import logging
import os
from typing import Any, List, Optional, Tuple

from src.utils.db_connect import connect_dict_cursor, cursor_dict
from src.vector.embedding import get_embedding

logger = logging.getLogger(__name__)


def _auto_init_enabled() -> bool:
    raw = (os.environ.get("CIE_AUTO_INIT_CLUSTER_VECTORS") or "").strip().lower()
    if raw in ("1", "true", "yes", "on"):
        return True
    if raw in ("0", "false", "no", "off"):
        return False
    env = (os.environ.get("APP_ENV") or os.environ.get("APP_ENVIRONMENT") or "").strip().lower()
    return env in ("local", "development", "dev", "testing")


def parse_vector_payload(raw: Any) -> Optional[List[float]]:
    if raw is None:
        return None
    if isinstance(raw, str):
        raw = raw.strip()
        if not raw or raw in ("[]", "null"):
            return None
        try:
            raw = json.loads(raw)
        except json.JSONDecodeError:
            return None
    if isinstance(raw, list) and len(raw) > 0:
        try:
            return [float(x) for x in raw]
        except (TypeError, ValueError):
            return None
    return None


def resolve_cluster_context(cluster_id: str) -> Optional[Tuple[str, str, Optional[List[float]]]]:
    """
    Resolve clusters.id (UUID) or cluster_master.cluster_id (CLU-*) to intent text + existing vector.
    Returns (canonical_cluster_uuid_or_id, intent_text, existing_vector).
    """
    cluster_id = (cluster_id or "").strip()
    if not cluster_id:
        return None

    conn = connect_dict_cursor()
    try:
        cur = cursor_dict(conn)
        # 1) Legacy skus.primary_cluster_id → clusters.id (UUID)
        cur.execute(
            """
            SELECT c.id, c.name, c.intent_statement, c.centroid_vector,
                   cm.intent_statement AS cm_intent, cm.intent_vector
            FROM clusters c
            LEFT JOIN cluster_master cm ON cm.cluster_id = c.name
            WHERE c.id = %s
            LIMIT 1
            """,
            (cluster_id,),
        )
        row = cur.fetchone()
        if row:
            intent = (row.get("intent_statement") or row.get("cm_intent") or "").strip()
            vec = parse_vector_payload(row.get("centroid_vector")) or parse_vector_payload(
                row.get("intent_vector")
            )
            return (str(row["id"]), intent, vec)

        # 2) cluster_master.cluster_id (e.g. CLU-CBL-P-E27)
        cur.execute(
            """
            SELECT cluster_id, intent_statement, intent_vector
            FROM cluster_master
            WHERE cluster_id = %s AND (is_active IS NULL OR is_active = TRUE)
            LIMIT 1
            """,
            (cluster_id,),
        )
        row = cur.fetchone()
        if row:
            intent = (row.get("intent_statement") or "").strip()
            vec = parse_vector_payload(row.get("intent_vector"))
            return (str(row["cluster_id"]), intent, vec)

        cur.close()
        conn.commit()
    finally:
        conn.close()
    return None


def persist_cluster_vector(cluster_key: str, vector: List[float], intent_statement: str = "") -> bool:
    """Write vector to clusters (by id or name) and cluster_master; optional Redis cache."""
    payload = json.dumps(vector)
    conn = connect_dict_cursor()
    updated = False
    try:
        cur = conn.cursor()
        cur.execute(
            """
            UPDATE clusters
            SET centroid_vector = %s::json, updated_at = NOW()
            WHERE id = %s OR name = %s
            """,
            (payload, cluster_key, cluster_key),
        )
        if cur.rowcount:
            updated = True
        if intent_statement:
            cur.execute(
                """
                UPDATE clusters
                SET intent_statement = COALESCE(NULLIF(TRIM(intent_statement), ''), %s)
                WHERE (id = %s OR name = %s) AND (intent_statement IS NULL OR TRIM(intent_statement) = '')
                """,
                (intent_statement, cluster_key, cluster_key),
            )
        cur.execute(
            """
            UPDATE cluster_master
            SET intent_vector = %s::json, updated_at = NOW()
            WHERE cluster_id = %s
            """,
            (payload, cluster_key),
        )
        if cur.rowcount:
            updated = True
        conn.commit()
        cur.close()
    finally:
        conn.close()

    try:
        from src.vector.cluster_cache import cache_cluster_vector

        cache_cluster_vector(cluster_key, vector)
    except Exception:
        pass
    return updated


def ensure_cluster_vector(cluster_id: str, *, force: bool = False) -> Optional[List[float]]:
    """Return centroid vector, embedding from intent text when missing (if auto-init enabled)."""
    ctx = resolve_cluster_context(cluster_id)
    if not ctx:
        logger.warning("cluster_init: no cluster row for cluster_id=%s", cluster_id)
        return None
    key, intent, existing = ctx
    if existing and not force:
        return existing
    if not _auto_init_enabled() and not force:
        return existing
    if not intent:
        logger.warning("cluster_init: empty intent_statement for cluster_id=%s", cluster_id)
        return None
    vec = get_embedding(intent)
    if not vec:
        logger.warning("cluster_init: embedding failed for cluster_id=%s", cluster_id)
        return None
    persist_cluster_vector(key, vec, intent)
    # Also cache under the lookup id passed in (UUID vs CLU code)
    if key != cluster_id:
        try:
            from src.vector.cluster_cache import cache_cluster_vector

            cache_cluster_vector(cluster_id, vec)
        except Exception:
            pass
    logger.info("cluster_init: embedded and stored vector for cluster_id=%s", cluster_id)
    return vec


def init_missing_cluster_vectors(*, limit: int = 50, force: bool = False) -> int:
    """Backfill vectors for clusters referenced by SKUs or with empty intent_vector."""
    conn = connect_dict_cursor()
    ids: list[str] = []
    try:
        cur = cursor_dict(conn)
        cur.execute(
            """
            SELECT DISTINCT c.id AS cluster_ref
            FROM clusters c
            INNER JOIN skus s ON s.primary_cluster_id = c.id
            WHERE c.centroid_vector IS NULL
               OR c.centroid_vector::text IN ('[]', 'null')
            LIMIT %s
            """,
            (limit,),
        )
        ids.extend(str(r["cluster_ref"]) for r in cur.fetchall() if r.get("cluster_ref"))

        cur.execute(
            """
            SELECT cluster_id
            FROM cluster_master
            WHERE is_active IS NOT FALSE
              AND (intent_vector IS NULL OR intent_vector::text IN ('[]', 'null'))
            LIMIT %s
            """,
            (limit,),
        )
        for r in cur.fetchall():
            cid = r.get("cluster_id")
            if cid and str(cid) not in ids:
                ids.append(str(cid))
        cur.close()
        conn.commit()
    finally:
        conn.close()

    count = 0
    for cid in ids[:limit]:
        vec = ensure_cluster_vector(cid, force=force)
        if vec:
            count += 1
    return count
