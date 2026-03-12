"""
CIE Python Worker API — FastAPI (replaces Flask).
Embed + similarity with fail-soft: on embedding API failure, log and return degraded response so save is allowed (v2.3.2).
"""
from dotenv import load_dotenv
load_dotenv()

import logging
import os
import sys
import uuid
from typing import Any, Optional

logger = logging.getLogger(__name__)

# Add parent to path for src imports
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

from src.vector.validation import validate_cluster_match
from src.vector.embedding import get_embedding

# -------- Request body models (same field names as Flask request.json) --------

from pydantic import BaseModel


class EmbedRequest(BaseModel):
    text: str = ""


class SimilarityRequest(BaseModel):
    description: str = ""
    cluster_id: str = ""


class BaselineUrlRequest(BaseModel):
    """Request body for baseline GSC/GA4 metrics — single URL (landing page)."""
    url: str = ""


# -------- App and in-memory queues (unchanged behavior) --------

app = FastAPI(
    title="CIE Python Worker API",
    version="1.0.0",
    description="Unified Python API: embed, similarity, validate, queue (replaces Flask).",
)

audit_queue: dict[str, Any] = {}
brief_queue: dict[str, Any] = {}

# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 7 — cluster_master is queried per
# request so the validate endpoint always reflects the live DB state.
from api.gates_validate import get_master_cluster_ids, run_all_gates, BusinessRules
from api.schemas_validate import SkuValidateRequest, SkuValidateResponsePass, SkuValidateResponseFail


# -------- Routes (paths and response structure identical to Flask) --------


@app.get("/")
@app.get("/api/")
@app.get("/api")
def health_check():
    """Health-check / root endpoint so GET / and GET /api/ don't 404."""
    return {
        "status": "ok",
        "service": "CIE Python Worker API",
        "version": "1.0.0",
        "endpoints": [
            "POST /api/v1/sku/embed",
            "POST /api/v1/sku/similarity",
            "POST /api/v1/sku/validate",
        ],
    }


# OpenAI text-embedding-3-small dimension (v2.3.1 §8.1)
EMBED_DIMENSIONS = 1536
EMBED_MODEL = "text-embedding-3-small"
PENDING_MESSAGE = (
    "Description validation temporarily unavailable. Your changes are saved "
    "but publishing is paused until validation completes (typically within 30 minutes)."
)


@app.post("/api/v1/sku/embed")
def sku_embed(body: EmbedRequest):
    """
    POST /api/v1/sku/embed — generate embedding (OpenAI text-embedding-3-small, 1536 dims).
    Fail-soft (v2.3.2): on API failure, log and return degraded response; do not hard-block saves.
    """
    text = (body.text or "").strip()
    if not text:
        return JSONResponse(status_code=400, content={"error": "text required"})
    try:
        vector = get_embedding(text)
        dims = len(vector) if isinstance(vector, (list, tuple)) else EMBED_DIMENSIONS
        return {
            "vector": vector if isinstance(vector, list) else list(vector),
            "model": EMBED_MODEL,
            "dimensions": dims,
        }
    except Exception as e:
        logger.warning("Embedding API unavailable (fail-soft): %s", e, exc_info=True)
        return {
            "vector": None,
            "model": EMBED_MODEL,
            "dimensions": EMBED_DIMENSIONS,
            "degraded": True,
            "error_message": str(e),
        }


@app.post("/api/v1/sku/similarity")
def sku_similarity(body: SimilarityRequest):
    """
    POST /api/v1/sku/similarity — cosine similarity vs cluster centroid (cached in Redis).
    Fail-soft (v2.3.2): on embedding API or cluster cache failure, return status 'pending' so save is allowed; do not 500.
    """
    description = (body.description or "").strip()
    cluster_id = (body.cluster_id or "").strip()
    if not description or not cluster_id:
        return JSONResponse(status_code=400, content={"error": "description and cluster_id required"})
    try:
        threshold = float(BusinessRules.get('gates.vector_similarity_min'))
        sku_vector = get_embedding(description)
        result = validate_cluster_match(sku_vector, cluster_id)
        sim = result.get("similarity", 0.0)
        reason = result.get("reason") or ""
        # Cluster not in Redis = validation unavailable; fail-soft to pending so save is allowed
        if sim == 0.0 and "not initialized" in reason.lower():
            logger.warning("Similarity unavailable (cluster not in cache): cluster_id=%s", cluster_id)
            logger.info(
                "AUDIT similarity_check cluster_id=%s status=pending reason=%s",
                cluster_id,
                "cluster_not_initialized",
            )
            return {
                "status": "pending",
                "message": PENDING_MESSAGE,
            }

        status = "pass" if sim >= threshold else "fail"
        logger.info(
            "AUDIT similarity_check cluster_id=%s status=%s",
            cluster_id,
            status,
        )
        return {
            "status": status,
            "similarity": sim,
            "message": (
                "Content semantic mismatch. Consider revising your description."
            ) if status == "fail" else None,
        }
    except Exception as e:
        logger.warning(
            "Similarity validation unavailable (fail-soft): cluster_id=%s error=%s",
            cluster_id, e, exc_info=True,
        )
        logger.info(
            "AUDIT similarity_check cluster_id=%s status=pending reason=%s",
            cluster_id,
            "engine_unavailable",
        )
        return {
            "status": "pending",
            "message": PENDING_MESSAGE,
        }


@app.post("/api/v1/sku/validate")
async def sku_validate(request: Request):
    """
    Pre-publish validation (G1–G7 + G6.1). CIE v2.3.1 Section 7.2.
    200 + status:pass if all pass; 400 + status:fail for invalid body or gate failures.
    """
    try:
        body = await request.json()
    except Exception as e:
        return JSONResponse(
            status_code=400,
            content={
                "status": "fail",
                "failures": [
                    {"error_code": "INVALID_JSON", "detail": str(e), "user_message": "Request body must be valid JSON."}
                ],
                "message": "Invalid request body.",
            },
        )
    try:
        data = SkuValidateRequest.model_validate(body)
    except Exception as e:
        return JSONResponse(
            status_code=400,
            content={
                "status": "fail",
                "failures": [
                    {"error_code": "VALIDATION_ERROR", "detail": str(e), "user_message": "Request fields did not match the required schema."}
                ],
                "message": "Validation error.",
            },
        )
    failures = run_all_gates(data, get_master_cluster_ids())
    if not failures:
        return JSONResponse(
            status_code=200,
            content=SkuValidateResponsePass(message="All gates passed.").model_dump(),
        )
    return JSONResponse(
        status_code=400,
        content=SkuValidateResponseFail(
            failures=failures,
            message="One or more gates failed.",
        ).model_dump(),
    )


# -------- Baseline capture (Section 17 Check 9.3) — GSC/GA4 metrics for a URL --------


@app.post("/api/v1/baseline/gsc-metrics")
def baseline_gsc_metrics(body: BaselineUrlRequest):
    """
    POST /api/v1/baseline/gsc-metrics — return GSC metrics for a landing URL (14-day window).
    Used by PHP to populate gsc_baselines before deploy. Returns empty/null on no data (no 500).
    """
    url = (body.url or "").strip()
    if not url:
        return JSONResponse(status_code=400, content={"error": "url required"})
    try:
        from datetime import date, timedelta
        from src.utils.config import Config
        from src.integrations.gsc_client import pull_gsc_for_page
        site_url = Config.GSC_SITE_URL or os.environ.get("GSC_SITE_URL", "")
        if not site_url:
            return {"impressions": None, "clicks": None, "ctr": None, "avg_position": None}
        end = date.today()
        start = end - timedelta(days=14)
        snapshot = pull_gsc_for_page(site_url, url, start, end)
        if snapshot is None:
            return {"impressions": None, "clicks": None, "ctr": None, "avg_position": None}
        return {
            "impressions": snapshot.impressions,
            "clicks": snapshot.clicks,
            "ctr": snapshot.ctr,
            "avg_position": snapshot.position,
        }
    except Exception as e:
        logger.warning("baseline GSC metrics failed for url=%s: %s", url, e, exc_info=True)
        return {"impressions": None, "clicks": None, "ctr": None, "avg_position": None}


@app.post("/api/v1/baseline/ga4-metrics")
def baseline_ga4_metrics(body: BaselineUrlRequest):
    """
    POST /api/v1/baseline/ga4-metrics — return GA4 metrics for a landing URL (14-day, Organic Search).
    Used by PHP to populate gsc_baselines before deploy. Returns empty/null on no data (no 500).
    """
    url = (body.url or "").strip()
    if not url:
        return JSONResponse(status_code=400, content={"error": "url required"})
    try:
        from datetime import date, timedelta
        from src.utils.config import Config
        from src.integrations.ga4_client import pull_ga4_for_landing_page
        property_id = Config.GA4_PROPERTY_ID or os.environ.get("GA4_PROPERTY_ID", "")
        if not property_id:
            return {"sessions": None, "conversion_rate": None, "revenue": None}
        end = date.today()
        start = end - timedelta(days=14)
        snapshot = pull_ga4_for_landing_page(property_id, url, start, end)
        if snapshot is None:
            return {"sessions": None, "conversion_rate": None, "revenue": None}
        return {
            "sessions": snapshot.sessions,
            "conversion_rate": snapshot.conversion_rate,
            "revenue": snapshot.revenue,
        }
    except Exception as e:
        logger.warning("baseline GA4 metrics failed for url=%s: %s", url, e, exc_info=True)
        return {"sessions": None, "conversion_rate": None, "revenue": None}


if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("PORT", 8000))
    uvicorn.run(app, host="0.0.0.0", port=port)
