"""
OpenAI text-embedding-3-small (1536 dims) for CIE semantic validation (v2.3.1 §8.1).
"""
import os
import logging

import numpy as np

logger = logging.getLogger(__name__)
_client = None


def _get_client():
    global _client
    if _client is None:
        from openai import OpenAI
        api_key = os.getenv('OPENAI_API_KEY')
        if not api_key or api_key == 'sk-...':
            raise RuntimeError(
                "OPENAI_API_KEY is not configured. "
                "Set it in your .env file or as an environment variable."
            )
        _client = OpenAI(api_key=api_key, timeout=3.0)  # v2.3.2: 3s timeout for fail-soft
    return _client


def get_embedding(text, model="text-embedding-3-small"):
    """
    Embed text with OpenAI text-embedding-3-small (1536 dimensions).
    Fails soft on timeout (warns, returns None).
    """
    text = text.replace("\n", " ")
    try:
        response = _get_client().embeddings.create(input=[text], model=model)
        return response.data[0].embedding
    except Exception as e:
        # Fail-soft: log warning, return None (don't block request)
        logger.warning(f"Embedding API error (fail-soft): {str(e)[:100]}")
        return None


def cosine_similarity(v1, v2):
    """Cosine similarity between two vectors."""
    return float(np.dot(v1, v2) / (np.linalg.norm(v1) * np.linalg.norm(v2)))