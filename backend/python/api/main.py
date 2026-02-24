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
from src.title.validation import validate_title as validate_title_rules, suggest_title as suggest_title_from_attrs

# -------- Request body models (same field names as Flask request.json) --------

from pydantic import BaseModel


class EmbedRequest(BaseModel):
    text: str = ""


class SimilarityRequest(BaseModel):
    description: str = ""
    cluster_id: str = ""


class ValidateVectorRequest(BaseModel):
    description: Optional[str] = None
    cluster_id: Optional[str] = None
    sku_id: str = "unknown"
    threshold: Optional[float] = None  # from BusinessRules.get('gates.vector_similarity_min'); default 0.72


class QueueAuditRequest(BaseModel):
    sku_id: Optional[str] = None


class QueueBriefRequest(BaseModel):
    sku_id: Optional[str] = None
    title: Optional[str] = None


class TitleValidateRequest(BaseModel):
    title: str = ""
    primary_intent: str = ""
    cluster_id: str = ""


class TitleSuggestRequest(BaseModel):
    cluster_id: str = ""
    primary_intent: str = ""
    attributes: dict[str, Any] = {}


# -------- App and in-memory queues (unchanged behavior) --------

app = FastAPI(
    title="CIE Python Worker API",
    version="1.0.0",
    description="Unified Python API: embed, similarity, validate, queue (replaces Flask).",
)

audit_queue: dict[str, Any] = {}
brief_queue: dict[str, Any] = {}

# Load master cluster list once for validate endpoint
from api.gates_validate import get_master_cluster_ids, run_all_gates
from api.schemas_validate import SkuValidateRequest, SkuValidateResponsePass, SkuValidateResponseFail

MASTER_CLUSTER_IDS = get_master_cluster_ids()


# -------- Routes (paths and response structure identical to Flask) --------

@app.get("/")
def index():
    """Root endpoint — same JSON as Flask."""
    return {
        "service": "CIE Python Worker API",
        "status": "running",
        "version": "1.0.0",
        "endpoints": [
            "/health",
            "/validate-vector",
            "/api/v1/sku/embed",
            "/api/v1/sku/similarity",
            "/api/v1/sku/validate",
            "/api/v1/title/validate",
            "/api/v1/title/suggest",
            "/queue/audit",
            "/queue/brief-generation",
        ],
    }


@app.get("/health")
def health():
    """Health check — same JSON as Flask."""
    return {"status": "healthy", "service": "python-worker"}


# OpenAI text-embedding-3-small dimension (v2.3.1 §8.1)
EMBED_DIMENSIONS = 1536
EMBED_MODEL = "text-embedding-3-small"
SIMILARITY_THRESHOLD = 0.72
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
        sku_vector = get_embedding(description)
        result = validate_cluster_match(sku_vector, cluster_id)
        sim = result.get("similarity", 0.0)
        reason = result.get("reason") or ""
        # Cluster not in Redis = validation unavailable; fail-soft to pending so save is allowed
        if sim == 0.0 and "not initialized" in reason.lower():
            logger.warning("Similarity unavailable (cluster not in cache): cluster_id=%s", cluster_id)
            return {
                "cosine_similarity": 0.0,
                "threshold": SIMILARITY_THRESHOLD,
                "status": "pending",
                "message": PENDING_MESSAGE,
                "degraded_mode": True,
            }
        return {
            "cosine_similarity": round(float(sim), 4),
            "threshold": SIMILARITY_THRESHOLD,
            "status": "pass" if sim >= SIMILARITY_THRESHOLD else "fail",
            "message": reason if sim < SIMILARITY_THRESHOLD else None,
        }
    except Exception as e:
        logger.warning(
            "Similarity validation unavailable (fail-soft): cluster_id=%s error=%s",
            cluster_id, e, exc_info=True,
        )
        return {
            "cosine_similarity": 0.0,
            "threshold": SIMILARITY_THRESHOLD,
            "status": "pending",
            "message": PENDING_MESSAGE,
            "degraded_mode": True,
        }


@app.post("/api/v1/title/validate")
def title_validate(body: TitleValidateRequest):
    """
    Validate product title against CIE rules: pipe separator, intent before pipe, attributes after, no brand first, G2 no colour/material/dimension before pipe, max 250 chars (6.1).
    Returns { valid, issues[], suggested_fix }.
    """
    result = validate_title_rules(body.title, body.primary_intent, body.cluster_id)
    return result


@app.post("/api/v1/title/suggest")
def title_suggest(body: TitleSuggestRequest):
    """
    Generate a CIE-compliant title from cluster_id + primary_intent + attributes (product_type, use_case, size, colour, fitting, etc.).
    User can accept or edit.
    """
    suggested = suggest_title_from_attrs(body.cluster_id, body.primary_intent, body.attributes or {})
    return {"suggested_title": suggested, "max_length": 250}


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
    failures = run_all_gates(data, MASTER_CLUSTER_IDS)
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


@app.post("/validate-vector")
def validate_vector(body: ValidateVectorRequest):
    """Validate SKU description against cluster vectors. Threshold from request (BusinessRules) or default 0.72. Fail-soft: return 200 with degraded on error (no 500)."""
    description = body.description or ""
    cluster_id = body.cluster_id or ""
    sku_id = body.sku_id or "unknown"
    threshold = body.threshold if body.threshold is not None else SIMILARITY_THRESHOLD
    if not description or not cluster_id:
        return JSONResponse(
            status_code=400,
            content={"valid": False, "similarity": 0.0, "reason": "description and cluster_id required"},
        )
    try:
        sku_vector = get_embedding(description)
        result = validate_cluster_match(sku_vector, cluster_id, threshold=threshold)
        return result
    except Exception as e:
        logger.warning("validate-vector fail-soft: %s", e)
        # Return 200 with degraded so clients don't treat as server error; save allowed, publish blocked
        return JSONResponse(
            status_code=200,
            content={
                "valid": False,
                "similarity": 0.0,
                "reason": "Vector validation temporarily unavailable. Save allowed, publish blocked.",
                "degraded": True,
                "error_message": str(e),
            },
        )


@app.post("/queue/audit")
def queue_audit(body: QueueAuditRequest):
    """Queue an AI audit job — same JSON as Flask."""
    sku_id = body.sku_id
    if not sku_id:
        return JSONResponse(status_code=400, content={"error": "sku_id required"})
    audit_id = str(uuid.uuid4())
    audit_queue[audit_id] = {
        "sku_id": sku_id,
        "status": "queued",
        "audit_id": audit_id,
    }
    return JSONResponse(
        status_code=202,
        content={
            "queued": True,
            "audit_id": audit_id,
            "message": "Audit job queued",
        },
    )


@app.post("/queue/brief-generation")
def queue_brief_generation(body: QueueBriefRequest):
    """Queue a brief generation job — same JSON as Flask."""
    sku_id = body.sku_id
    title = body.title
    if not sku_id or not title:
        return JSONResponse(status_code=400, content={"error": "sku_id and title required"})
    brief_id = str(uuid.uuid4())
    brief_queue[brief_id] = {
        "sku_id": sku_id,
        "title": title,
        "status": "queued",
        "brief_id": brief_id,
    }
    return JSONResponse(
        status_code=202,
        content={
            "queued": True,
            "brief_id": brief_id,
            "message": "Brief generation job queued",
        },
    )


@app.get("/audits/{audit_id}")
def get_audit_result(audit_id: str):
    """Get audit result (polling) — same JSON as Flask."""
    if audit_id in audit_queue:
        return audit_queue[audit_id]
    return JSONResponse(status_code=202, content={"status": "pending"})


@app.get("/briefs/{brief_id}")
def get_brief_result(brief_id: str):
    """Get brief generation result (polling) — same JSON as Flask."""
    if brief_id in brief_queue:
        return brief_queue[brief_id]
    return JSONResponse(status_code=202, content={"status": "pending"})


if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("PORT", 8000))
    uvicorn.run(app, host="0.0.0.0", port=port)
