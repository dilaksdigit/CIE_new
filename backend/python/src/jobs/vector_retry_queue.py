"""
CIE v2.3.2 — Vector retry queue processor (cron: every 5 minutes).
SOURCE: CIE_v232_Hardening_Addendum.pdf §1.3
"""
import logging
import os
from datetime import datetime, timedelta
from urllib.parse import urlparse

logger = logging.getLogger(__name__)

VECTOR_THRESHOLD = 0.72
BATCH_LIMIT = 50


def _get_db():
    import pymysql
    url = os.environ.get("DATABASE_URL", "")
    if url:
        parsed = urlparse(url)
        return pymysql.connect(
            host=parsed.hostname or os.environ.get("DB_HOST", "localhost"),
            port=parsed.port or 3306,
            user=parsed.username or os.environ.get("DB_USER", "root"),
            password=parsed.password or os.environ.get("DB_PASSWORD", ""),
            database=(parsed.path or "").lstrip("/") or os.environ.get("DB_DATABASE", "cie"),
            cursorclass=pymysql.cursors.DictCursor,
        )
    return pymysql.connect(
        host=os.environ.get("DB_HOST", "localhost"),
        user=os.environ.get("DB_USER", "root"),
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_DATABASE", "cie"),
        cursorclass=pymysql.cursors.DictCursor,
    )


def _get_embedding(text: str):
    """Call OpenAI text-embedding-3-small. Raises on failure."""
    from openai import OpenAI
    client = OpenAI(
        api_key=os.environ.get("OPENAI_API_KEY"),
        timeout=10.0,
    )
    response = client.embeddings.create(
        input=[text.replace("\n", " ")],
        model="text-embedding-3-small",
    )
    return response.data[0].embedding


def _cosine_similarity(v1, v2):
    import numpy as np
    return float(np.dot(v1, v2) / (np.linalg.norm(v1) * np.linalg.norm(v2)))


def _get_cluster_vector(cursor, cluster_id: str):
    """Fetch the cluster centroid vector from the DB."""
    import json
    cursor.execute(
        "SELECT centroid_vector FROM cluster_master WHERE cluster_id = %s",
        (cluster_id,),
    )
    row = cursor.fetchone()
    if row and row.get("centroid_vector"):
        vec = row["centroid_vector"]
        return json.loads(vec) if isinstance(vec, str) else vec
    return None


def notify_content_owner(sku_id: str, similarity: float):
    logger.warning(
        "NOTIFY: sku_id=%s failed vector retry (similarity below threshold). "
        "Content owner should revise description.",
        sku_id,
    )


def alert_admin(sku_id: str, retry_count: int):
    logger.error(
        "ALERT: sku_id=%s exhausted max retries (%d). Admin intervention required.",
        sku_id,
        retry_count,
    )


def process_vector_retry_queue():
    """
    Process queued vector retry items.
    Runs every 5 minutes via cron.
    """
    conn = _get_db()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                "SELECT * FROM vector_retry_queue "
                "WHERE status = 'queued' AND next_retry_at <= NOW() "
                "ORDER BY created_at "
                "LIMIT %s",
                (BATCH_LIMIT,),
            )
            items = cursor.fetchall()

        if not items:
            logger.info("vector_retry_queue: no items to process")
            return

        logger.info("vector_retry_queue: processing %d items", len(items))

        for item in items:
            item_id = item["id"]
            sku_id = item["sku_id"]
            description = item["description"]
            cluster_id = item["cluster_id"]
            retry_count = item["retry_count"]
            max_retries = item["max_retries"]

            with conn.cursor() as cursor:
                cursor.execute(
                    "UPDATE vector_retry_queue SET status = 'processing' WHERE id = %s",
                    (item_id,),
                )
                conn.commit()

            try:
                embedding = _get_embedding(description)

                with conn.cursor() as cursor:
                    cluster_vec = _get_cluster_vector(cursor, cluster_id)

                if cluster_vec is None:
                    logger.warning(
                        "vector_retry: cluster %s has no centroid vector, marking failed",
                        cluster_id,
                    )
                    with conn.cursor() as cursor:
                        cursor.execute(
                            "UPDATE vector_retry_queue "
                            "SET status = 'failed', error_message = 'Cluster centroid not found' "
                            "WHERE id = %s",
                            (item_id,),
                        )
                        conn.commit()
                    continue

                similarity = _cosine_similarity(embedding, cluster_vec)
                gate_status = "pass" if similarity >= VECTOR_THRESHOLD else "fail"
                error_code = "CIE_VEC_LOW" if gate_status == "fail" else None

                with conn.cursor() as cursor:
                    cursor.execute(
                        "INSERT INTO sku_gate_status (sku_id, gate_code, status, error_code, checked_at) "
                        "VALUES (%s, 'G4_VECTOR', %s, %s, NOW()) "
                        "ON DUPLICATE KEY UPDATE status = VALUES(status), "
                        "error_code = VALUES(error_code), checked_at = NOW()",
                        (sku_id, gate_status, error_code),
                    )

                    cursor.execute(
                        "UPDATE sku_content SET vector_similarity = %s "
                        "WHERE sku_id = %s",
                        (similarity, sku_id),
                    )

                    cursor.execute(
                        "UPDATE vector_retry_queue "
                        "SET status = 'resolved', resolved_at = NOW() "
                        "WHERE id = %s",
                        (item_id,),
                    )

                    cursor.execute(
                        "INSERT INTO audit_log (entity_type, entity_id, action, field_name, "
                        "new_value, actor_id, actor_role, created_at) "
                        "VALUES ('sku', %s, 'vector_retry_resolved', 'G4_VECTOR', %s, "
                        "'SYSTEM', 'system', NOW())",
                        (sku_id, gate_status),
                    )
                    conn.commit()

                logger.info(
                    "vector_retry: sku_id=%s resolved gate_status=%s similarity=%.4f",
                    sku_id, gate_status, similarity,
                )

                if gate_status == "fail":
                    notify_content_owner(sku_id, similarity)

            except Exception as e:
                new_count = retry_count + 1
                logger.warning(
                    "vector_retry: sku_id=%s embedding failed (attempt %d): %s",
                    sku_id, new_count, str(e)[:200],
                )

                with conn.cursor() as cursor:
                    if new_count >= max_retries:
                        cursor.execute(
                            "UPDATE vector_retry_queue "
                            "SET status = 'failed', retry_count = %s, "
                            "error_message = %s "
                            "WHERE id = %s",
                            (new_count, str(e)[:500], item_id),
                        )
                        conn.commit()
                        alert_admin(sku_id, new_count)
                    else:
                        backoff_minutes = min(5 * (2 ** new_count), 20)  # Cap at 20 min — KPI #5 (Hardening Addendum §1.3): >95% pending resolved ≤30 min
                        next_retry = datetime.utcnow() + timedelta(minutes=backoff_minutes)
                        cursor.execute(
                            "UPDATE vector_retry_queue "
                            "SET status = 'queued', retry_count = %s, "
                            "next_retry_at = %s, error_message = %s "
                            "WHERE id = %s",
                            (new_count, next_retry, str(e)[:500], item_id),
                        )
                        conn.commit()
    finally:
        conn.close()


def run():
    process_vector_retry_queue()


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    run()
