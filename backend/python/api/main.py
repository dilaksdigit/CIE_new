"""
CIE Python Worker API — FastAPI (replaces Flask).
Embed + similarity with fail-soft: on embedding API failure, log and return degraded response so save is allowed (v2.3.2).
"""
from dotenv import load_dotenv
import asyncio
import json
import logging
import os

import pymysql.err

# Load .env from project root and backend so GSC/GA4 config is set (backend/.env often holds GA4_PROPERTY_ID, GSC_SITE_URL, GOOGLE_APPLICATION_CREDENTIALS)
_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
load_dotenv(os.path.join(_root, ".env"))
_backend = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))  # api -> python -> backend
load_dotenv(os.path.join(_backend, ".env"))
import sys
import uuid
from typing import Any, Dict, List, Optional

logger = logging.getLogger(__name__)

# Add parent to path for src imports
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from fastapi import BackgroundTasks, FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from pydantic import ValidationError as PydanticValidationError

from src.vector.validation import validate_cluster_match
from src.vector.embedding import get_embedding

# -------- Request body models (same field names as Flask request.json) --------

from pydantic import BaseModel


class EmbedRequest(BaseModel):
    text: str = ""


class SimilarityRequest(BaseModel):
    description: str = ""
    cluster_id: str = ""


class AuditRunRequest(BaseModel):
    category: str = ""
    run_id: Optional[str] = None


class BaselineUrlRequest(BaseModel):
    """Request body for baseline GSC/GA4 metrics — single URL (landing page)."""
    url: str = ""


# -------- App and in-memory queues (unchanged behavior) --------

app = FastAPI(
    title="CIE Python Worker API",
    version="1.0.0",
    description="Unified Python API: embed, similarity, validate, queue (replaces Flask).",
)


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §7.3 — CIE_* codes are gate failures only; transport errors use distinct types
@app.exception_handler(RequestValidationError)
async def request_validation_exception_handler(request: Request, exc: RequestValidationError):
    return JSONResponse(
        status_code=422,
        content={
            "status": "error",
            "error_type": "REQUEST_VALIDATION_ERROR",
            "detail": str(exc),
        },
    )


@app.exception_handler(json.JSONDecodeError)
async def json_decode_exception_handler(request: Request, exc: json.JSONDecodeError):
    return JSONResponse(
        status_code=422,
        content={
            "status": "error",
            "error_type": "INVALID_REQUEST_JSON",
            "detail": "Request body is not valid JSON",
        },
    )


audit_queue: dict[str, Any] = {}
brief_queue: dict[str, Any] = {}

# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 7 — cluster_master is queried per
# request so the validate endpoint always reflects the live DB state.
from api.gates_validate import get_master_cluster_ids, run_all_gates, log_audit_event, BusinessRules
from api.schemas_validate import (
    SkuValidateRequest,
    SkuValidateResponsePass,
    SkuValidateResponseFail,
    FailureItem,
)


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
            "POST /api/v1/sku/suggest",
        ],
    }


# Gemini text-embedding-004 typical dimension
EMBED_DIMENSIONS = 768
EMBED_MODEL = os.environ.get("GEMINI_EMBEDDING_MODEL", "gemini-embedding-001")
PENDING_MESSAGE = (
    "Description validation temporarily unavailable. Your changes are saved "
    "but publishing is paused until validation completes (typically within 30 minutes)."
)

ALLOWED_AUDIT_CATEGORIES = frozenset(
    {"cables", "lampshades", "bulbs", "pendants", "floor_lamps"}
)


def _weekly_audit_background(category: str, run_id: str) -> None:
    """SOURCE: CIE_Master_Developer_Build_Spec.docx §12.1 — delegates to weekly_service.run_weekly_audit."""
    try:
        from src.utils.mysql_connect import pymysql_connect_dict_cursor
        from src.ai_audit.weekly_service import run_weekly_audit

        brand = (os.environ.get("CIE_AUDIT_BRAND_NAME") or "CIE").strip() or "CIE"
        db = pymysql_connect_dict_cursor()
        try:
            run_weekly_audit(db, category, brand, run_id=run_id)
        finally:
            db.close()
    except Exception as exc:
        logger.exception(
            "Background weekly audit failed category=%s run_id=%s: %s",
            category,
            run_id,
            exc,
        )


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — POST /api/v1/sku/embed
@app.post("/api/v1/sku/embed")
def sku_embed(body: EmbedRequest):
    """
    POST /api/v1/sku/embed — generate embedding (Gemini embedding model).
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


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — POST /api/v1/sku/similarity
@app.post("/api/v1/sku/similarity")
def sku_similarity(body: SimilarityRequest):
    """
    POST /api/v1/sku/similarity — cosine similarity vs cluster centroid (cached in Redis).
    Fail-soft (v2.3.2): on embedding API or cluster cache failure, return status 'pending' so save is allowed; do not 500.
    NOTE (Audit #3 Fix 19): Wire contract is pass|fail|pending; POST /sku/validate applies policy-aware warn/degraded
    (Hardening §1.1). Intentional divergence between raw similarity and pipeline.
    """
    description = (body.description or "").strip()
    cluster_id = (body.cluster_id or "").strip()
    if not description or not cluster_id:
        return JSONResponse(status_code=400, content={"error": "description and cluster_id required"})
    try:
        threshold = float(BusinessRules.get('gates.vector_similarity_min'))
        sku_vector = get_embedding(description)
        result = validate_cluster_match(sku_vector, cluster_id, threshold=threshold)
        # SOURCE: CIE_v232_Hardening_Addendum.pdf §1.1 — None embedding → pending from validate_cluster_match
        if result.get("status") == "pending":
            logger.info(
                "AUDIT similarity_check cluster_id=%s status=pending reason=%s",
                cluster_id,
                "embedding_unavailable",
            )
            return {
                "status": "pending",
                "message": PENDING_MESSAGE,
            }
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
        # SOURCE: CLAUDE.md §11, openapi.yaml SimilarityResponse — no numeric score in response
        return {
            "status": status,
            "message": (
                "Your content may not align with the intent. Consider revising."
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


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — POST /api/v1/audit/run
@app.post("/api/v1/audit/run")
def audit_run(body: AuditRunRequest, background_tasks: BackgroundTasks):
    """
    Trigger weekly AI citation audit for a category (async). PHP proxy dispatches here;
    quorum/run_status are persisted on ai_audit_runs by weekly_service (Hardening Patch 2 §2.1).
    """
    cat = (body.category or "").strip().lower()
    if cat not in ALLOWED_AUDIT_CATEGORIES:
        return JSONResponse(
            status_code=400,
            content={"error": "INVALID_CATEGORY", "message": "Invalid category"},
        )
    rid = (body.run_id or "").strip()
    if rid:
        try:
            uuid.UUID(rid)
        except ValueError:
            return JSONResponse(
                status_code=400,
                content={"error": "INVALID_RUN_ID", "message": "run_id must be a UUID"},
            )
    else:
        rid = str(uuid.uuid4())
    background_tasks.add_task(_weekly_audit_background, cat, rid)
    # SOURCE: openapi.yaml AuditRunResponse; CIE_v232_Hardening_Addendum.pdf Patch 2 — placeholders until run completes
    return JSONResponse(
        status_code=202,
        content={
            "run_id": rid,
            "status": "running",
            "estimated_duration_minutes": 15,
            "quorum": 0,
            "run_status": "complete",
        },
    )


def _load_tier_rules_dict() -> Dict[str, Any]:
    """Build openapi TierRules-shaped object from tier_intent_rules + BusinessRules."""
    harvest_ids: List[int] = []
    conn = _db_connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT intent_id FROM tier_intent_rules WHERE LOWER(TRIM(tier)) = %s",
                ("harvest",),
            )
            for row in cur.fetchall() or []:
                harvest_ids.append(int(row["intent_id"]))
    finally:
        conn.close()
    harvest_ids = sorted(set(harvest_ids))
    return {
        "hero": {
            "max_secondary": int(BusinessRules.get("gates.hero_max_secondary")),
            "all_intents": True,
        },
        "support": {
            "max_secondary": int(BusinessRules.get("gates.support_max_secondary")),
            "all_intents": True,
        },
        "harvest": {
            "max_secondary": int(BusinessRules.get("gates.harvest_max_secondary")),
            "allowed_intents": harvest_ids,
        },
        "kill": {"max_secondary": 0, "all_intents": False},
    }


def _build_audit_results_envelope(category: str, run_id: Optional[str] = None) -> Dict[str, Any]:
    """Mirror PHP AuditController::resultsByCategory `data` object (DB-backed)."""
    cat = category.strip().lower()
    citation_rate: Optional[float] = None
    results: List[Dict[str, Any]] = []
    run_date: Optional[str] = None
    latest_run_id: Optional[str] = None
    quorum: Optional[int] = None
    run_status: Optional[str] = None
    decay_alerts: List[Dict[str, Any]] = []

    conn = _db_connect()
    try:
        with conn.cursor() as cur:
            meta = None
            if run_id and str(run_id).strip():
                rid = str(run_id).strip()
                try:
                    cur.execute(
                        """
                        SELECT run_id, run_date, aggregate_citation_rate, pass_fail, engines_available, run_status, status
                        FROM ai_audit_runs
                        WHERE run_id = %s AND category = %s
                        """,
                        (rid, cat),
                    )
                except pymysql.err.OperationalError as exc:
                    if exc.args[0] != 1054:
                        raise
                    cur.execute(
                        """
                        SELECT run_id, run_date, aggregate_citation_rate, pass_fail, engines_available, status
                        FROM ai_audit_runs
                        WHERE run_id = %s AND category = %s
                        """,
                        (rid, cat),
                    )
                meta = cur.fetchone()
            else:
                try:
                    cur.execute(
                        """
                        SELECT run_id, run_date, aggregate_citation_rate, pass_fail, engines_available, run_status, status
                        FROM ai_audit_runs
                        WHERE category = %s AND status = 'completed'
                        ORDER BY run_date DESC, created_at DESC
                        LIMIT 1
                        """,
                        (cat,),
                    )
                except pymysql.err.OperationalError as exc:
                    if exc.args[0] != 1054:
                        raise
                    cur.execute(
                        """
                        SELECT run_id, run_date, aggregate_citation_rate, pass_fail, engines_available, status
                        FROM ai_audit_runs
                        WHERE category = %s AND status = 'completed'
                        ORDER BY run_date DESC, created_at DESC
                        LIMIT 1
                        """,
                        (cat,),
                    )
                meta = cur.fetchone()
            if meta:
                latest_run_id = str(meta.get("run_id") or "")
                rd = meta.get("run_date")
                if rd is not None:
                    run_date = rd.isoformat() if hasattr(rd, "isoformat") else str(rd)
                if meta.get("aggregate_citation_rate") is not None:
                    citation_rate = round(float(meta["aggregate_citation_rate"]), 4)
                if meta.get("engines_available") is not None:
                    quorum = int(meta["engines_available"])
                rs = meta.get("run_status")
                if rs:
                    run_status = str(rs)

            if latest_run_id:
                cur.execute(
                    """
                    SELECT air.question_id, gq.question_text, air.engine, air.score, s.sku_code AS cited_sku
                    FROM ai_audit_results air
                    LEFT JOIN ai_golden_queries gq ON gq.question_id = air.question_id
                    LEFT JOIN skus s ON s.id = air.cited_sku_id
                    WHERE air.run_id = %s
                    ORDER BY air.question_id, air.engine
                    """,
                    (latest_run_id,),
                )
                by_q: Dict[str, Dict[str, Any]] = {}
                for r in cur.fetchall() or []:
                    qid = str(r.get("question_id") or "")
                    if not qid:
                        continue
                    if qid not in by_q:
                        by_q[qid] = {
                            "question_id": qid,
                            "question_text": str(r.get("question_text") or qid),
                            "scores": {},
                            "cited_sku": r.get("cited_sku"),
                        }
                    eng = str(r.get("engine") or "")
                    if eng:
                        sc = r.get("score")
                        by_q[qid]["scores"][eng] = int(sc) if sc is not None else 0
                    if r.get("cited_sku"):
                        by_q[qid]["cited_sku"] = r.get("cited_sku")
                results = list(by_q.values())

            cur.execute(
                """
                SELECT id, decay_status, decay_consecutive_zeros
                FROM skus
                WHERE category = %s AND COALESCE(decay_consecutive_zeros, 0) > 0
                """,
                (cat,),
            )
            valid_decay = {"yellow_flag", "alert", "auto_brief", "escalated"}
            for r in cur.fetchall() or []:
                st = str(r.get("decay_status") or "").lower()
                if st not in valid_decay:
                    continue
                decay_alerts.append(
                    {
                        "sku_id": str(r.get("id")),
                        "decay_status": st,
                        "consecutive_zero_weeks": int(r.get("decay_consecutive_zeros") or 0),
                    }
                )
    finally:
        conn.close()

    from datetime import date

    rd_out = run_date or date.today().isoformat()
    target = float(BusinessRules.get("decay.hero_citation_target"))
    pass_fail: Optional[str]
    if citation_rate is None:
        pass_fail = None
    else:
        pass_fail = "pass" if citation_rate >= target else "fail"

    return {
        "run_id": latest_run_id,
        "category": cat,
        "run_date": rd_out,
        "aggregate_citation_rate": citation_rate,
        "pass_fail": pass_fail,
        "results": results,
        "decay_alerts": decay_alerts,
        "quorum": quorum,
        "run_status": run_status,
    }


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — GET /api/v1/taxonomy/intents
@app.get("/api/v1/taxonomy/intents")
def taxonomy_intents(tier: Optional[str] = None):
    import json as _json

    conn = _db_connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT intent_id, intent_key, label, definition, tier_access FROM intent_taxonomy ORDER BY intent_id"
            )
            rows = cur.fetchall()
    finally:
        conn.close()

    tf = (tier or "").strip().lower() or None
    intents: List[Dict[str, Any]] = []
    for r in rows or []:
        ta = r.get("tier_access")
        if isinstance(ta, (bytes, str)):
            try:
                tier_list = _json.loads(ta)
            except Exception:
                tier_list = []
        elif isinstance(ta, (list, tuple)):
            tier_list = list(ta)
        else:
            tier_list = []
        tier_norm = [str(x).lower() for x in tier_list]
        if tf and tf not in tier_norm:
            continue
        intents.append(
            {
                "intent_id": int(r["intent_id"]),
                "intent_key": r["intent_key"],
                "label": r["label"],
                "definition": r["definition"],
                "tier_access": tier_list,
            }
        )
    return {"intents": intents, "tier_rules": _load_tier_rules_dict()}


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — GET /api/v1/clusters
@app.get("/api/v1/clusters")
def api_v1_clusters(category: Optional[str] = None):
    conn = _db_connect()
    try:
        with conn.cursor() as cur:
            if category and str(category).strip():
                c = str(category).strip().lower()
                cur.execute(
                    """
                    SELECT cluster_id, category, intent_statement, is_active
                    FROM cluster_master
                    WHERE is_active = TRUE AND category = %s
                    ORDER BY cluster_id
                    """,
                    (c,),
                )
            else:
                cur.execute(
                    """
                    SELECT cluster_id, category, intent_statement, is_active
                    FROM cluster_master
                    WHERE is_active = TRUE
                    ORDER BY category, cluster_id
                    """
                )
            rows = cur.fetchall()
    finally:
        conn.close()
    clusters = [
        {
            "cluster_id": r["cluster_id"],
            "category": r["category"],
            "intent_statement": r["intent_statement"],
            "is_active": bool(r.get("is_active", True)),
        }
        for r in (rows or [])
    ]
    return {"clusters": clusters}


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — GET /api/v1/audit/results/{category}
@app.get("/api/v1/audit/results/{category}")
def api_v1_audit_results(category: str, run_id: Optional[str] = None):
    cat = (category or "").strip().lower()
    if cat not in ALLOWED_AUDIT_CATEGORIES:
        return JSONResponse(
            status_code=400,
            content={"error": "INVALID_CATEGORY", "message": "Invalid category"},
        )
    try:
        payload = _build_audit_results_envelope(cat, run_id)
    except Exception as exc:
        logger.warning("audit/results build failed: %s", exc, exc_info=True)
        return JSONResponse(
            status_code=500,
            content={"error": "AUDIT_RESULTS_FAILED", "message": str(exc)[:500]},
        )
    return {"data": payload}


# SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — POST /api/v1/brief/generate (proxy to Laravel CMS)
@app.post("/api/v1/brief/generate")
async def api_v1_brief_generate(request: Request):
    import httpx

    try:
        body = await request.json()
    except Exception:
        return JSONResponse(
            status_code=422,
            content={"error": "INVALID_JSON", "message": "Request body must be JSON"},
        )
    cms = (os.environ.get("CIE_CMS_URL") or os.environ.get("APP_URL") or "").rstrip("/")
    if not cms:
        return JSONResponse(
            status_code=503,
            content={
                "error": "CMS_URL_NOT_CONFIGURED",
                "message": "Set CIE_CMS_URL or APP_URL to forward brief generation to Laravel",
            },
        )
    token = (os.environ.get("CIE_INTERNAL_API_KEY") or "").strip()
    headers = {"Content-Type": "application/json", "Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    url = f"{cms}/api/v1/brief/generate"
    try:
        async with httpx.AsyncClient(timeout=120.0) as client:
            resp = await client.post(url, json=body, headers=headers)
    except Exception as exc:
        logger.exception("brief/generate proxy: %s", exc)
        return JSONResponse(
            status_code=502,
            content={"error": "CMS_UNREACHABLE", "message": str(exc)[:200]},
        )
    try:
        out = resp.json()
    except Exception:
        out = {"message": resp.text[:500]}
    return JSONResponse(status_code=resp.status_code, content=out)


# SOURCE: ENF§2.2 — tier-gate applicability matrix
def get_gate_status_for_tier(gate_key: str, tier: str) -> str:
    """Return not_applicable for gates that don't apply to this tier."""
    suspended = {
        # SOURCE: ENF§2.2 — Harvest G4/G5/G7 suspended; G3 optional (max 1) — status set in build_validation_response from payload
        "harvest": [
            "G4_answer_block",
            "G5_best_not_for",
            "G7_expert_authority",
        ],
        # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.2 — Kill: G1–G5, G7, VEC suspended; G6 + G6.1 enforced.
        # FIX: G1-03 — G1_cluster_id is N/A for Kill when not evaluated; G6_1 failure comes from failure_map (not suspended).
        "kill": [
            "G1_cluster_id",
            "G2_primary_intent",
            "G3_secondary_intents",
            "G4_answer_block",
            "G5_best_not_for",
            "G7_expert_authority",
        ],
    }
    tier_lower = (tier or "").strip().lower()
    if tier_lower in suspended and gate_key in suspended[tier_lower]:
        return "not_applicable"
    return "pass"


# SOURCE: openapi.yaml ValidationResponse, ENF§7.2 — gate keys match spec example:
# G1_cluster_id, G2_primary_intent, G3_secondary_intents, G4_answer_block,
# G5_best_not_for, G6_tier_tag, G6_1_tier_lock, G7_expert_authority
def build_validation_response(
    data, tier: str, failures: list, vector_result: dict, degraded: bool, audit_degraded: bool = False
) -> dict:
    """Build response matching openapi.yaml ValidationResponse schema."""
    tier_lower = (tier or "").strip().lower()
    # GAP_LOG: MASTER§7.1 shows short gate keys (G1, G2...); ENF§7.2 shows long keys
    # (G1_cluster_id, G2_primary_intent...). Current implementation uses long keys per ENF§7.2.
    # Architect decision needed to reconcile. No change applied.
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 vs CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.2
    gate_keys = [
        "G1_cluster_id",
        "G2_primary_intent",
        "G3_secondary_intents",
        "G4_answer_block",
        "G5_best_not_for",
        "G6_tier_tag",
        "G6_1_tier_lock",
        "G7_expert_authority",
    ]
    failure_map = {f.gate: f for f in failures if getattr(f, "gate", None) and f.gate in gate_keys}
    gates = {}
    for key in gate_keys:
        if key in failure_map:
            f = failure_map[key]
            gates[key] = {
                "status": "fail",
                "error_code": f.error_code,
                "detail": f.detail,
                "user_message": f.user_message,
            }
        else:
            gate_status = get_gate_status_for_tier(key, tier)
            gates[key] = {
                "status": gate_status,
                "error_code": None,
                "detail": None,
                "user_message": None,
            }
    vc_status = (vector_result or {}).get("status", "pass")
    # SOURCE: CLAUDE.md §11 — vector_check FailureItem for CIE_VEC_SIMILARITY_LOW on below-threshold is warn-only; do not set overall fail
    vector_blocks_overall = vc_status != "warn" and any(getattr(f, "gate", None) == "vector_check" for f in failures)
    has_fail = any(g["status"] == "fail" for g in gates.values()) or vector_blocks_overall
    has_pending = degraded or vc_status == "pending"
    if has_fail:
        overall = "fail"
    elif has_pending:
        overall = "pending"
    else:
        overall = "pass"
    # SOURCE: CLAUDE.md §11 — warn does not block save but blocks publish
    # SOURCE: ENF§2.1 G6.1, BUILD§Step2 — Kill tier: never publishable regardless of gate surface
    publish_allowed = (
        (tier or "").strip().lower() != "kill"
        and overall == "pass"
        and vc_status == "pass"
    )
    # SOURCE: openapi.yaml ValidationResponse — gate outcomes live under `gates` only (FIX: MF-01).
    if overall == "pass" and vc_status == "warn":
        msg = "Gates passed; vector similarity warning — publishing not allowed until resolved."
    elif overall == "pass":
        msg = "All gates passed."
    elif has_fail:
        msg = "One or more gates failed."
    else:
        msg = "Validation pending."
    # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §2.2 — Harvest G3 is OPTIONAL (max 1); violations must still fail (§7.1)
    if tier_lower == "harvest":
        g3_failures = [f for f in failures if str(getattr(f, "gate", "") or "").startswith("G3")]
        if not g3_failures:
            has_secs = bool([s for s in (data.secondary_intents or []) if s and str(s).strip()])
            if not has_secs:
                gates["G3_secondary_intents"] = {
                    "status": "not_applicable",
                    "error_code": None,
                    "detail": "Harvest tier — secondary intents optional (max 1)",
                    "user_message": None,
                }
            else:
                gates["G3_secondary_intents"] = {
                    "status": "pass",
                    "error_code": None,
                    "detail": None,
                    "user_message": None,
                }
        has_fail = any(g["status"] == "fail" for g in gates.values()) or vector_blocks_overall
        if has_fail:
            overall = "fail"
        elif has_pending:
            overall = "pending"
        else:
            overall = "pass"
        publish_allowed = (
            tier_lower != "kill"
            and overall == "pass"
            and vc_status == "pass"
        )
        if overall == "pass" and vc_status == "warn":
            msg = "Gates passed; vector similarity warning — publishing not allowed until resolved."
        elif overall == "pass":
            msg = "All gates passed."
        elif has_fail:
            msg = "One or more gates failed."
        else:
            msg = "Validation pending."

    # SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf Kill fixture
    # FIX: TS-09 — Kill tier: no content saves (G6.1); API reflects publish + save blocked.
    save_allowed = tier_lower != "kill"

    return {
        "status": overall,
        "gates": gates,
        "vector_check": {
            "status": vector_result.get("status", "pass") if vector_result else "pass",
            "user_message": vector_result.get("user_message") if vector_result else None,
        },
        "degraded_mode": degraded,
        "save_allowed": save_allowed,
        "publish_allowed": publish_allowed,
        "message": msg,
        "audit_degraded": audit_degraded,
    }


@app.post("/api/v1/sku/validate")
async def sku_validate(request: Request):
    """
    Pre-publish validation (G1–G7 + G6.1). CIE v2.3.1 Section 7.2.
    SOURCE: openapi.yaml ValidationResponse — returns full shape: status, gates, vector_check, degraded_mode, save_allowed, publish_allowed.
    """
    try:
        body = await request.json()
    except json.JSONDecodeError:
        return JSONResponse(
            status_code=422,
            content={
                "status": "error",
                "error_type": "INVALID_REQUEST_JSON",
                "detail": "Request body is not valid JSON",
            },
        )
    try:
        data = SkuValidateRequest.model_validate(body)
    except PydanticValidationError as e:
        return JSONResponse(
            status_code=422,
            content={
                "status": "error",
                "error_type": "REQUEST_VALIDATION_ERROR",
                "detail": str(e),
            },
        )
    try:
        master_ids = get_master_cluster_ids()
    except Exception as e:
        return JSONResponse(
            status_code=500,
            content={
                "status": "error",
                "error_type": "INTERNAL_VALIDATION_ERROR",
                "detail": str(e),
                "gates": {},
                "vector_check": {"status": "pass", "user_message": None},
                "degraded_mode": False,
                "save_allowed": True,
                "publish_allowed": False,
                "message": "Validation service error.",
            },
        )
    result = run_all_gates(data, master_ids)
    failures = result["failures"]
    vector_result = result["vector_result"]
    degraded = result["degraded"]
    audit_degraded = bool(result.get("audit_degraded"))
    tier = (data.tier or "").strip().lower()
    response = build_validation_response(data, tier, failures, vector_result, degraded, audit_degraded=audit_degraded)
    # SOURCE: MASTER§17 — audit log must include gate results summary
    gate_summary = {k: v.get("status") for k, v in response.get("gates", {}).items()}
    audit_detail = {
        "action": getattr(data, "action", "save"),
        "tier": tier,
        "status": response["status"],
        "gates": gate_summary,
        "vector_status": response.get("vector_check", {}).get("status"),
        "degraded_mode": degraded,
    }
    detail_str = json.dumps(audit_detail)[:255]
    if not log_audit_event(
        sku_id=data.sku_id,
        event="VALIDATION_COMPLETE",
        detail=detail_str,
    ):
        audit_degraded = True
        response = build_validation_response(data, tier, failures, vector_result, degraded, audit_degraded=audit_degraded)
    # SOURCE: CIE_v232_Hardening_Addendum.pdf §1.1 — pending allows save (200); only fail returns 400.
    # BUILD§Step2 "200 = all pass" predates Hardening. GAP_LOG: BUILD§Step2 text should be updated to "200 for non-blocking outcomes".
    status_code = 400 if response["status"] == "fail" else 200
    return JSONResponse(status_code=status_code, content=response)


# -------- Baseline capture (Section 17 Check 9.3) — GSC/GA4 metrics for a URL --------


@app.post("/api/v1/baseline/gsc-metrics")
def baseline_gsc_metrics(body: BaselineUrlRequest):
    """
    POST /api/v1/baseline/gsc-metrics — return GSC metrics for a landing URL (baseline lookback window).
    Used by PHP to populate gsc_baselines before deploy. Returns empty/null on no data (no 500).

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 sync.baseline_lookback_weeks; §9.4
    FIX: CIS-02 — lookback from business_rules, not hardcoded 14 days
    """
    url = (body.url or "").strip()
    if not url:
        return JSONResponse(status_code=400, content={"error": "url required"})
    try:
        from datetime import date, timedelta
        from src.utils.config import Config
        from src.utils.business_rules import get_business_rule
        from src.integrations.gsc_client import pull_gsc_for_page
        site_url = (Config.GSC_PROPERTY or os.environ.get("GSC_PROPERTY", "") or
                    os.environ.get("GSC_SITE_URL", ""))
        if not site_url:
            return {"impressions": None, "clicks": None, "ctr": None, "avg_position": None}
        end = date.today()
        lookback_weeks = int(get_business_rule("sync.baseline_lookback_weeks", 2))
        start = end - timedelta(days=lookback_weeks * 7)
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
    POST /api/v1/baseline/ga4-metrics — return GA4 metrics for a landing URL (baseline lookback, Organic Search).
    Used by PHP to populate gsc_baselines before deploy. Returns empty/null on no data (no 500).

    SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 sync.baseline_lookback_weeks; §10
    FIX: CIS-02 — lookback from business_rules, not hardcoded 14 days
    """
    url = (body.url or "").strip()
    if not url:
        return JSONResponse(status_code=400, content={"error": "url required"})
    try:
        from urllib.parse import urlparse
        from src.utils.url_utils import normalise_url

        norm_url = normalise_url(url)
        parsed = urlparse(norm_url)
        landing_path = parsed.path if parsed.path else "/"

        # SOURCE: CIE_Master_Developer_Build_Spec.docx §9.4, §10.3
        # Baseline from 2 most recent weekly rows (fail-soft if none).
        conn = _db_connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT organic_sessions, organic_conversion_rate, bounce_rate, revenue_organic
                    FROM ga4_landing_performance
                    WHERE landing_page IN (%s, %s)
                    ORDER BY COALESCE(week_ending, window_end) DESC
                    LIMIT 2
                    """,
                    (landing_path, norm_url),
                )
                rows = cur.fetchall()
        finally:
            conn.close()

        if not rows:
            return {"sessions": None, "bounce_rate": None, "conversion_rate": None, "revenue_organic": None}

        sessions_vals = [float(r.get("organic_sessions") or 0) for r in rows]
        bounce_vals = [float(r.get("bounce_rate") or 0) for r in rows]
        conv_vals = [r.get("organic_conversion_rate") for r in rows]
        rev_vals = [r.get("revenue_organic") for r in rows]

        has_ecommerce = any((v is not None and float(v) > 0) for v in rev_vals) or any(
            (v is not None and float(v) > 0) for v in conv_vals
        )
        conv_clean = [float(v) for v in conv_vals if v is not None]
        rev_clean = [float(v) for v in rev_vals if v is not None]

        sessions = int(round(sum(sessions_vals) / max(len(sessions_vals), 1)))
        bounce_rate = round(sum(bounce_vals) / max(len(bounce_vals), 1), 4)
        conversion_rate = round(sum(conv_clean) / len(conv_clean), 6) if conv_clean and has_ecommerce else 0
        revenue_organic = round(sum(rev_clean) / len(rev_clean), 2) if rev_clean and has_ecommerce else 0

        return {
            "sessions": sessions,
            "bounce_rate": bounce_rate,
            "conversion_rate": conversion_rate,
            "revenue_organic": revenue_organic,
        }
    except Exception as e:
        logger.warning("baseline GA4 metrics failed for url=%s: %s", url, e, exc_info=True)
        return {"sessions": None, "bounce_rate": None, "conversion_rate": None, "revenue_organic": None}


# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 9.1 (auth)
# SOURCE: CIE_Master_Developer_Build_Spec.docx Phase 2 Item 2.1
#   "returns 200 with verified property list using service account credentials"
def _db_connect():
    from src.utils.mysql_connect import pymysql_connect_dict_cursor

    return pymysql_connect_dict_cursor()


def _gsc_verify_last_sync_date() -> Optional[str]:
    """Most recent weekly sync marker from url_performance (COALESCE(week_ending, window_end))."""
    try:
        conn = _db_connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT MAX(COALESCE(week_ending, window_end)) AS d
                    FROM url_performance
                    """
                )
                row = cur.fetchone()
                d = row.get("d") if row else None
                if d is None:
                    return None
                if hasattr(d, "isoformat"):
                    return d.isoformat()
                return str(d)
        finally:
            conn.close()
    except Exception as exc:
        logger.warning("gsc/verify last_sync query failed: %s", exc)
        return None


def _ga4_verify_last_sync_date() -> Optional[str]:
    try:
        conn = _db_connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT MAX(COALESCE(week_ending, window_end)) AS d
                    FROM ga4_landing_performance
                    """
                )
                row = cur.fetchone()
                d = row.get("d") if row else None
                if d is None:
                    return None
                if hasattr(d, "isoformat"):
                    return d.isoformat()
                return str(d)
        finally:
            conn.close()
    except Exception as exc:
        logger.warning("ga4/health last_sync query failed: %s", exc)
        return None


@app.get("/api/v1/ga4/health")
def ga4_health():
    """
    SOURCE: CIE_Master_Developer_Build_Spec.docx §10.3, §15
    Internal engine route for GA4 connectivity and ecommerce tracking detection.
    """
    try:
        from datetime import date, timedelta
        from src.utils.config import Config
        from src.integrations.ga4_client import _get_client
        from google.analytics.data_v1beta.types import DateRange, Metric, RunReportRequest

        property_id = Config.GA4_PROPERTY_ID or os.environ.get("GA4_PROPERTY_ID", "")
        if not property_id:
            return JSONResponse(status_code=200, content={
                "connected": False,
                "ecommerce_tracking_detected": False,
                "last_sync_date": _ga4_verify_last_sync_date(),
            })

        client = _get_client()
        prop = f"properties/{property_id}" if not property_id.startswith("properties/") else property_id
        end = date.today()
        start = end - timedelta(days=7)
        req = RunReportRequest(
            property=prop,
            metrics=[Metric(name="purchaseRevenue")],
            date_ranges=[DateRange(start_date=start.isoformat(), end_date=end.isoformat())],
            limit=1,
        )
        resp = client.run_report(req)
        revenue = 0.0
        if resp.rows:
            revenue = float(resp.rows[0].metric_values[0].value or 0)
        return JSONResponse(status_code=200, content={
            "connected": True,
            "ecommerce_tracking_detected": revenue > 0,
            "last_sync_date": _ga4_verify_last_sync_date(),
        })
    except Exception as exc:
        logger.warning("ga4/health failed: %s", exc, exc_info=True)
        return JSONResponse(status_code=200, content={
            "connected": False,
            "ecommerce_tracking_detected": False,
            "last_sync_date": _ga4_verify_last_sync_date(),
        })


@app.get("/api/v1/gsc/verify")
def gsc_verify():
    """
    Internal engine route: GSC auth + sites.list + last url_performance sync date.
    Used by PHP GET /api/gsc/status. Always returns 200 with a JSON body (fail-soft).
    """
    try:
        from src.integrations.gsc_client import get_gsc_service

        service = get_gsc_service()
        resp = service.sites().list().execute()
        entries = resp.get("siteEntry") or []
        verified = [e.get("siteUrl") for e in entries if e.get("siteUrl")]
        last_sync = _gsc_verify_last_sync_date()
        return JSONResponse(
            status_code=200,
            content={
                "connected": True,
                "verified_properties": verified,
                "last_sync_date": last_sync,
            },
        )
    except Exception as exc:
        err_type = "unknown"
        try:
            from google.auth.exceptions import DefaultCredentialsError
            from googleapiclient.errors import HttpError

            if isinstance(exc, DefaultCredentialsError):
                err_type = "auth_error"
            elif isinstance(exc, HttpError):
                err_type = "api_error"
        except Exception:
            pass
        msg = str(exc).lower()
        if "credential" in msg or "authentication" in msg or "invalid_grant" in msg:
            err_type = "auth_error"
        logger.warning("gsc/verify failed: %s", exc, exc_info=True)
        return JSONResponse(
            status_code=200,
            content={
                "connected": False,
                "verified_properties": [],
                "last_sync_date": None,
                "error": err_type,
            },
        )


# SOURCE: CIE_Master_Developer_Build_Spec.docx §4.4
# FIX: AI-08 — Python handler for content pre-fill (called by PHP POST /api/v1/sku/{sku_id}/suggest)
@app.post("/api/v1/sku/suggest")
async def suggest_content(request: Request):
    sku_data = await request.json()
    from src.utils.prompts import build_suggest_user_prompt, build_standard_system_prompt
    from src.utils.ai_agent import ai_agent_call

    system_prompt = build_standard_system_prompt()
    user_message = build_suggest_user_prompt(sku_data)
    sid = sku_data.get("sku_id") or sku_data.get("sku_code")

    try:
        raw = await asyncio.to_thread(
            lambda: ai_agent_call(
                system_prompt,
                user_message,
                1000,
                str(sid) if sid else None,
                "content_suggest",
            )
        )
        suggestion = json.loads(raw)
        return JSONResponse(content=suggestion)
    except json.JSONDecodeError:
        return JSONResponse(
            content={
                "error": "AI response was not valid JSON",
                "fields_editable": True,
            },
            status_code=200,
        )
    except Exception:
        return JSONResponse(
            content={
                "error": "AI suggestions unavailable — enter manually.",
                "fields_editable": True,
            },
            status_code=200,
        )


if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("PORT", 8000))
    uvicorn.run(app, host="0.0.0.0", port=port)
