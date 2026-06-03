"""
Cluster intent centroid vectors — cached in Redis (v2.3.1 §8.2.1).
Vectors are updated only when the SEO Governor updates a cluster's intent statement.
"""
import json
import logging
import os
from urllib.parse import urlparse

import redis

from src.utils.db_connect import connect_dict_cursor, cursor_dict
from src.vector.cluster_init import ensure_cluster_vector, parse_vector_payload

logger = logging.getLogger(__name__)
REDIS_KEY_PREFIX = "cluster:"


def _redis_urls():
    configured = (os.getenv("REDIS_URL") or "").strip()
    urls = []
    if configured:
        urls.append(configured)
        try:
            parsed = urlparse(configured)
            if parsed.hostname == "redis":
                local = configured.replace("redis://redis", "redis://localhost")
                urls.append(local)
        except Exception:
            pass
    if not urls:
        urls.append("redis://localhost:6379/0")
    if "redis://localhost:6379/0" not in urls:
        urls.append("redis://localhost:6379/0")
    return urls


def _row_vector(row: dict, *keys: str):
    for key in keys:
        if key in row:
            vec = parse_vector_payload(row.get(key))
            if vec:
                return vec
    return None


def _get_cluster_vector_from_db(cluster_id: str):
    """Load centroid by clusters.id (UUID), cluster_master.cluster_id, or cluster_vectors."""
    user = os.getenv("DB_USERNAME") or os.getenv("DB_USER")
    database = os.getenv("DB_DATABASE")
    if not (user and database):
        return None

    cluster_id = (cluster_id or "").strip()
    if not cluster_id:
        return None

    conn = connect_dict_cursor()
    try:
        cur = cursor_dict(conn)
        # SKUs.primary_cluster_id → clusters.id (most common in CMS)
        cur.execute(
            """
            SELECT c.centroid_vector, cm.intent_vector
            FROM clusters c
            LEFT JOIN cluster_master cm ON cm.cluster_id = c.name
            WHERE c.id = %s
            LIMIT 1
            """,
            (cluster_id,),
        )
        row = cur.fetchone()
        if row:
            vec = _row_vector(row, "centroid_vector", "intent_vector")
            if vec:
                return vec

        cur.execute(
            """
            SELECT intent_vector
            FROM cluster_master
            WHERE cluster_id = %s
            LIMIT 1
            """,
            (cluster_id,),
        )
        row = cur.fetchone()
        if row:
            vec = _row_vector(row, "intent_vector")
            if vec:
                return vec

        for query in (
            "SELECT vector AS vector_payload FROM cluster_vectors WHERE cluster_id = %s",
            "SELECT centroid_vector AS vector_payload FROM cluster_master WHERE cluster_id = %s",
        ):
            try:
                cur.execute(query, (cluster_id,))
                row = cur.fetchone()
                if row:
                    vec = parse_vector_payload(row.get("vector_payload"))
                    if vec:
                        return vec
            except Exception:
                continue

        cur.close()
        conn.commit()
    except Exception as exc:
        logger.warning("DB fallback unavailable for cluster cache: %s", str(exc)[:120])
    finally:
        conn.close()
    return None


def get_cluster_vector(cluster_id: str):
    """Load cluster centroid vector from Redis, then DB; optional auto-init for local dev."""
    cluster_id = (cluster_id or "").strip()
    if not cluster_id:
        return None

    for redis_url in _redis_urls():
        try:
            client = redis.from_url(redis_url)
            vec = client.get(f"{REDIS_KEY_PREFIX}{cluster_id}")
            if vec:
                parsed = parse_vector_payload(json.loads(vec))
                if parsed:
                    return parsed
        except Exception as exc:
            logger.warning(
                "Redis unavailable for cluster cache (%s): %s",
                redis_url,
                str(exc)[:120],
            )

    db_vec = _get_cluster_vector_from_db(cluster_id)
    if db_vec:
        return db_vec

    return ensure_cluster_vector(cluster_id)


def cache_cluster_vector(cluster_id, vector):
    """Store cluster centroid vector in Redis. Call when SEO Governor updates cluster intent."""
    cached = False
    for redis_url in _redis_urls():
        try:
            client = redis.from_url(redis_url)
            client.set(f"{REDIS_KEY_PREFIX}{cluster_id}", json.dumps(vector))
            cached = True
            break
        except Exception:
            continue
    if not cached:
        logger.debug("Unable to cache cluster vector in Redis for cluster_id=%s", cluster_id)


def get_all_cluster_vectors():
    """
    Return dict of cluster_id -> centroid vector list from cluster_master + clusters.
    """
    user = os.getenv("DB_USERNAME") or os.getenv("DB_USER")
    database = os.getenv("DB_DATABASE")
    if not (user and database):
        return {}

    conn = connect_dict_cursor()
    vecs: dict = {}
    try:
        cur = cursor_dict(conn)
        for sql in (
            """
            SELECT c.id AS cluster_id, c.centroid_vector, cm.intent_vector
            FROM clusters c
            LEFT JOIN cluster_master cm ON cm.cluster_id = c.name
            WHERE c.centroid_vector IS NOT NULL OR cm.intent_vector IS NOT NULL
            """,
            """
            SELECT cluster_id, intent_vector
            FROM cluster_master
            WHERE is_active IS NOT FALSE AND intent_vector IS NOT NULL
            """,
        ):
            try:
                cur.execute(sql)
                for row in cur.fetchall():
                    cid = row.get("cluster_id")
                    if not cid:
                        continue
                    vec = _row_vector(row, "centroid_vector", "intent_vector")
                    if vec:
                        vecs[str(cid)] = vec
            except Exception:
                continue
        cur.close()
        conn.commit()
    finally:
        conn.close()
    return vecs
