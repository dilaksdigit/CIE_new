"""
Cluster intent centroid vectors — cached in Redis (v2.3.1 §8.2.1).
Vectors are updated only when the SEO Governor updates a cluster's intent statement.
"""
import json
import logging
import os
from urllib.parse import urlparse

import pymysql
import redis

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


def _get_cluster_vector_from_db(cluster_id):
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = int(os.getenv("DB_PORT", "3306"))
    user = os.getenv("DB_USERNAME") or os.getenv("DB_USER")
    password = os.getenv("DB_PASSWORD")
    database = os.getenv("DB_DATABASE")
    if not (user and database):
        return None
    conn = pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        with conn.cursor() as cursor:
            for query in (
                "SELECT centroid_vector AS vector_payload FROM cluster_master WHERE cluster_id = %s",
                "SELECT intent_vector AS vector_payload FROM cluster_master WHERE cluster_id = %s",
                "SELECT vector AS vector_payload FROM cluster_vectors WHERE cluster_id = %s",
                "SELECT centroid_vector AS vector_payload FROM clusters WHERE id = %s",
                "SELECT cm.intent_vector AS vector_payload FROM clusters c JOIN cluster_master cm ON cm.cluster_id = c.name WHERE c.id = %s",
            ):
                try:
                    cursor.execute(query, (cluster_id,))
                    row = cursor.fetchone()
                    if row and row.get("vector_payload"):
                        vec = row["vector_payload"]
                        return json.loads(vec) if isinstance(vec, str) else vec
                except Exception:
                    continue
    finally:
        conn.close()
    return None


def get_cluster_vector(cluster_id):
    """Load cluster centroid vector from Redis. Returns None if not cached."""
    for redis_url in _redis_urls():
        try:
            client = redis.from_url(redis_url)
            vec = client.get(f"{REDIS_KEY_PREFIX}{cluster_id}")
            if vec:
                return json.loads(vec)
        except Exception as exc:
            logger.warning("Redis unavailable for cluster cache (%s): %s", redis_url, str(exc)[:120])

    # Local fallback for non-Docker runtime: load centroid from DB directly.
    try:
        db_vec = _get_cluster_vector_from_db(cluster_id)
        if db_vec:
            return db_vec
    except Exception as exc:
        logger.warning("DB fallback unavailable for cluster cache: %s", str(exc)[:120])

    return None


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
        logger.warning("Unable to cache cluster vector in Redis for cluster_id=%s", cluster_id)
