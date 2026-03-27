"""
Gemini text embeddings for CIE semantic validation.
"""
import logging
import os

import numpy as np
import requests

logger = logging.getLogger(__name__)


def _gemini_model_name(model: str | None) -> str:
    raw = (model or os.getenv("GEMINI_EMBEDDING_MODEL") or "gemini-embedding-001").strip()
    if raw.startswith("models/"):
        return raw
    return f"models/{raw}"


def _embedding_endpoints(model_name: str, api_key: str):
    base = "https://generativelanguage.googleapis.com"
    return [
        f"{base}/v1beta/{model_name}:embedContent?key={api_key}",
        f"{base}/v1/{model_name}:embedContent?key={api_key}",
    ]


def get_embedding(text, model=None):
    """
    Embed text with Gemini embeddings.
    Fails soft on timeout/provider errors (warns, returns None).
    """
    # Normalize env value to avoid fail-soft false negatives from accidental spaces in .env values.
    api_key = (os.getenv("GEMINI_API_KEY") or "").strip()
    if not api_key or api_key == "...":
        logger.warning("Embedding API error (fail-soft): GEMINI_API_KEY missing")
        return None

    model_name = _gemini_model_name(model)
    payload = {
        "model": model_name,
        "content": {"parts": [{"text": (text or "").replace("\n", " ")}]},
        "taskType": "SEMANTIC_SIMILARITY",
    }

    last_error = None
    for endpoint in _embedding_endpoints(model_name, api_key):
        try:
            resp = requests.post(endpoint, json=payload, timeout=12)
            resp.raise_for_status()
            data = resp.json() if resp.content else {}
            values = data.get("embedding", {}).get("values")
            if isinstance(values, list) and values:
                return values
            last_error = "missing embedding values"
        except Exception as e:
            last_error = str(e)[:140]
            continue

    logger.warning(f"Embedding API error (fail-soft): {last_error}")
    return None


def cosine_similarity(v1, v2):
    """Cosine similarity between two vectors."""
    return float(np.dot(v1, v2) / (np.linalg.norm(v1) * np.linalg.norm(v2)))