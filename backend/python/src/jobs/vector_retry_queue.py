"""
CIE v2.3.2 — Vector retry queue processor (cron: every 5 minutes).
SOURCE: CIE_v232_Hardening_Addendum.pdf §1.3
"""
import logging
import os
from datetime import datetime, timedelta
from api.gates_validate import BusinessRules
from src.utils.db_connect import connect_dict_cursor
from src.utils.sql_postgres import SQL_UPSERT_SKU_GATE_STATUS_G4_VECTOR

logger = logging.getLogger(__name__)

BATCH_LIMIT = 50


def _get_db():
    return connect_dict_cursor()


def _get_embedding(text: str):
    """
    Embeddings for retry processing — same behaviour as API path (embedding.py).
    Supports LOCAL_LLM_MODE + LOCAL_LLM_BASE_URL (Ollama / LM Studio); otherwise OpenAI cloud.
    """
    from src.vector.embedding import get_embedding

    vec = get_embedding(text)
    if vec is None:
        raise RuntimeError(
            "Embedding failed: set OPENAI_API_KEY and OPENAI_EMBEDDING_MODEL, "
            "or LOCAL_LLM_MODE=true with LOCAL_LLM_BASE_URL and model env vars."
        )
    return vec


def _cosine_similarity(v1, v2):
    import numpy as np
    return float(np.dot(v1, v2) / (np.linalg.norm(v1) * np.linalg.norm(v2)))


def _get_cluster_vector(cursor, cluster_id: str):
    """Fetch cluster centroid (shared cache + auto-init path)."""
    from src.vector.cluster_cache import get_cluster_vector

    return get_cluster_vector(cluster_id)


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
                threshold = BusinessRules.get('gates.vector_similarity_min')
                gate_status = "pass" if similarity >= threshold else "fail"
                # SOURCE: ENF§Page18 — CIE_VEC_SIMILARITY_LOW
                error_code = "CIE_VEC_SIMILARITY_LOW" if gate_status == "fail" else None

                with conn.cursor() as cursor:
                    cursor.execute(
                        SQL_UPSERT_SKU_GATE_STATUS_G4_VECTOR,
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
                    if gate_status == "pass":
                        cursor.execute(
                            """
                            UPDATE skus
                            SET ai_validation_pending = FALSE,
                                updated_at = NOW()
                            WHERE sku_code = %s OR id::text = %s
                            """,
                            (sku_id, sku_id),
                        )
                    conn.commit()

                # SOURCE: CLAUDE.md §11 — numeric similarity is server-side only; avoid INFO exposure paths.
                # FIX: VEC-03 — Similarity detail at DEBUG only (operational trace, not client-facing).
                logger.debug(
                    "vector_retry: sku_id=%s resolved gate_status=%s similarity=%.4f",
                    sku_id, gate_status, similarity,
                )
                logger.info(
                    "vector_retry: sku_id=%s resolved gate_status=%s",
                    sku_id, gate_status,
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
                        backoff_minutes = min(5 * (2 ** new_count), 60)  # Cap at 60 min (Hardening Addendum Patch 1 §1.3)
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
