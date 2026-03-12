import logging
import os
from . import cluster_cache
from . import embedding

logger = logging.getLogger(__name__)


def validate_cluster_match(request_vector, cluster_id, threshold=None):
    """
    Validate request vector against cluster centroid.
    SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1; CLAUDE.md Section 18 DECISION-005.
    threshold: from request (BusinessRules.get('gates.vector_similarity_min') in PHP). If missing, use env VECTOR_SIMILARITY_THRESHOLD (default 0.72).
    Below threshold = WARNING only (valid=False, status='warn'); score never returned to writer-facing API.
    """
    if threshold is None:
        threshold = float(os.environ.get('VECTOR_SIMILARITY_THRESHOLD', '0.72'))
    # Check if cluster vector exists
    cluster_vec = cluster_cache.get_cluster_vector(cluster_id)
    if not cluster_vec:
        logger.warning(f"Cluster {cluster_id} vectors not initialized")
        return {
            'valid': False,
            'similarity': 0.0,
            'reason': f'Cluster {cluster_id} vectors not initialized. Run cluster embedding initialization first.'
        }
    
    # Fail-soft: if request vector is None (embedding timeout), allow through
    if request_vector is None:
        logger.warning(f"Request embedding failed (timeout) - bypassing validation (fail-soft)")
        return {
            'valid': True,  # ALLOW REQUEST (fail-soft)
            'similarity': None,
            'reason': 'Embedding API timeout - validation bypassed (fail-soft mode)'
        }
    
    # Compute similarity
    try:
        similarity = embedding.cosine_similarity(request_vector, cluster_vec)
    except Exception as e:
        logger.error(f"Cosine similarity computation failed: {e}")
        return {
            'valid': False,
            'similarity': 0.0,
            'reason': f'Similarity computation error: {str(e)}'
        }
    
    # Audit log (required by CIE v2.3.2)
    logger.info(f"AUDIT: cluster_id={cluster_id} similarity={similarity:.4f}")
    
    # SOURCE: CLAUDE.md §11, DECISION-005
    # API available + similarity below threshold = WARNING, not block
    if similarity < threshold:
        return {
            'valid': False,
            'status': 'warn',
            'similarity': similarity,
            'reason': 'cosine_similarity_below_threshold'
        }
    
    return {'valid': True, 'similarity': similarity, 'reason': 'Passed validation'}