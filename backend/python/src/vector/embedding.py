"""
OpenAI text embeddings for CIE semantic validation (env-driven model).
"""
import hashlib
import logging
import os

import numpy as np
from openai import OpenAI

logger = logging.getLogger(__name__)


def _mock_embedding_vector(text: str, target_dim: int = 1536) -> list:
    """Deterministic mock vector for LOCAL_LLM_MODE when embeddings API fails."""
    hash_bytes = hashlib.sha512((text or "").encode("utf-8")).digest()
    mock_vector = [float(b) / 255.0 - 0.5 for b in hash_bytes]
    while len(mock_vector) < target_dim:
        mock_vector.extend(mock_vector)
    mock_vector = mock_vector[:target_dim]
    norm = np.linalg.norm(mock_vector)
    if norm > 0:
        mock_vector = (np.array(mock_vector) / norm).tolist()
    return mock_vector


def get_embedding(text, model=None):
    """
    Embed text with OpenAI (model from OPENAI_EMBEDDING_MODEL or caller model arg).
    Fails soft on timeout/provider errors (warns, returns None).
    In LOCAL_LLM_MODE, uses LOCAL_LLM_BASE_URL; may fall back to a mock vector if
    the local server has no embeddings endpoint (flow testing only — not semantic).
    """
    local_mode = os.environ.get("LOCAL_LLM_MODE", "").strip().lower() == "true"

    if local_mode:
        import openai

        base_url = os.environ.get("LOCAL_LLM_BASE_URL", "http://localhost:1234/v1")
        api_key = (os.environ.get("OPENAI_API_KEY") or "local-dummy-key").strip()
        resolved_model = (
            model or os.environ.get("OPENAI_EMBEDDING_MODEL") or ""
        ).strip() or "Qwen3-Next-dummy-Instruct-dummy"

        client = openai.OpenAI(base_url=base_url, api_key=api_key, timeout=12.0)
        try:
            response = client.embeddings.create(
                model=resolved_model,
                input=[(text or "").replace("\n", " ")],
            )
            vec = response.data[0].embedding
            if isinstance(vec, list) and vec:
                return vec
            logger.warning("Embedding API error (fail-soft): empty embedding vector")
            return None
        except Exception as e:
            logger.warning(
                "LOCAL_LLM_MODE: embeddings failed (%s); using mock vector — "
                "cosine similarity is NOT semantically valid.",
                str(e)[:140],
            )
            return _mock_embedding_vector(text or "")

    api_key = (os.environ.get("OPENAI_API_KEY") or "").strip()
    if not api_key or api_key == "...":
        logger.warning("Embedding API error (fail-soft): OPENAI_API_KEY missing")
        return None

    resolved_model = (model or os.environ.get("OPENAI_EMBEDDING_MODEL") or "").strip()
    if not resolved_model:
        logger.warning("Embedding API error (fail-soft): OPENAI_EMBEDDING_MODEL not set")
        return None

    try:
        client = OpenAI(api_key=api_key, timeout=12.0)
        response = client.embeddings.create(
            model=resolved_model,
            input=[(text or "").replace("\n", " ")],
        )
        vec = response.data[0].embedding
        if isinstance(vec, list) and vec:
            return vec
        logger.warning("Embedding API error (fail-soft): empty embedding vector")
        return None
    except Exception as e:
        logger.warning("Embedding API error (fail-soft): %s", str(e)[:140])
        return None


def cosine_similarity(v1, v2):
    """Cosine similarity between two vectors."""
    return float(np.dot(v1, v2) / (np.linalg.norm(v1) * np.linalg.norm(v2)))
