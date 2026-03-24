import logging
import os
from . import cluster_cache
from . import embedding

logger = logging.getLogger(__name__)


class ConfigurationError(Exception):
    """SOURCE: CIE_Master_Developer_Build_Spec.docx §5, §16 Rule 5 — vector threshold must not use silent numeric fallbacks."""


def validate_cluster_match(request_vector, cluster_id, threshold=None):
    """
    Validate request vector against cluster centroid.
    SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1; CLAUDE.md Section 18 DECISION-005.
    threshold: pass from caller, or resolve via BusinessRules then ENV; else raise (MASTER§5.2).
    Below threshold = WARNING only (valid=False, status='warn'); score never returned to writer-facing API.
    """
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5, §16 Rule 5 — threshold from BusinessRules (DB) or VECTOR_SIMILARITY_THRESHOLD only; no raw literals
    if threshold is None:
        try:
            from api.gates_validate import BusinessRules

            threshold = float(BusinessRules.get("gates.vector_similarity_min"))
        except (RuntimeError, TypeError, ValueError):
            threshold = None
        if threshold is None:
            env_raw = os.environ.get("VECTOR_SIMILARITY_THRESHOLD")
            if env_raw is not None and str(env_raw).strip() != "":
                try:
                    threshold = float(env_raw)
                except (TypeError, ValueError):
                    threshold = None
        if threshold is None:
            raise ConfigurationError(
                "Vector similarity threshold not configured. "
                "Set 'gates.vector_similarity_min' in business_rules table "
                "or VECTOR_SIMILARITY_THRESHOLD environment variable."
            )

    cluster_vec = cluster_cache.get_cluster_vector(cluster_id)
    if not cluster_vec:
        logger.warning("Cluster %s vectors not initialized", cluster_id)
        return {
            "valid": False,
            "similarity": 0.0,
            "reason": f"Cluster {cluster_id} vectors not initialized. Run cluster embedding initialization first.",
        }

    # SOURCE: CIE_v232_Hardening_Addendum.pdf §1.1 — embedding unavailable = pending, not pass (save allowed, publish blocked)
    # SOURCE: CIE_v232_Hardening_Addendum.pdf §1.3 — queued for retry when embedding service unavailable
    if request_vector is None:
        logger.warning("Request embedding failed (timeout) — fail-soft pending (not pass)")
        return {
            "valid": False,
            "status": "pending",
            "similarity": None,
            "threshold": threshold,
            "reason": "Embedding service unavailable. Validation pending.",
            "degraded": True,
        }

    try:
        similarity = embedding.cosine_similarity(request_vector, cluster_vec)
    except Exception as e:
        logger.error("Cosine similarity computation failed: %s", e)
        return {
            "valid": False,
            "similarity": 0.0,
            "reason": f"Similarity computation error: {str(e)}",
        }

    # SOURCE: CLAUDE.md §11 — no numeric similarity at INFO; DEBUG only
    passed_vec = bool(similarity >= threshold)
    logger.info("AUDIT: cluster_id=%s vector_status=%s", cluster_id, "pass" if passed_vec else "fail")
    logger.debug(
        "AUDIT: cluster_id=%s similarity=%s threshold=%s",
        cluster_id,
        similarity,
        threshold,
    )

    # SOURCE: CLAUDE.md §11, DECISION-005
    if similarity < threshold:
        return {
            "valid": False,
            "status": "warn",
            "similarity": similarity,
            "reason": "cosine_similarity_below_threshold",
        }

    return {"valid": True, "similarity": similarity, "reason": "Passed validation"}
