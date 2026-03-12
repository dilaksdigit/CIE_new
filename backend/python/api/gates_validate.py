"""
CIE v2.3.1 Gate validation for SKU validate endpoint.
8 gates IN ORDER: G1, G2, G3, G4, G5, G6, G6.1, G7.
Harvest: G4, G5, G7 SUSPENDED. Kill: only G1 and G6.
"""
from __future__ import annotations

import logging
import os
from typing import Any

from .schemas_validate import (
    SkuValidateRequest,
    FailureItem,
    VALID_PRIMARY_INTENTS,
    VALID_PRIMARY_INTENTS_NORM,
)

_logger = logging.getLogger(__name__)


class BusinessRules:
    """Read-through cache for the business_rules table (mirrors PHP BusinessRules facade)."""
    _cache: dict[str, Any] | None = None

    @classmethod
    def get(cls, key: str, default=None):
        if cls._cache is None:
            cls._load()
        val = cls._cache.get(key)
        if val is None:
            if default is not None:
                return default
            raise RuntimeError(f"Business rule key not found: {key}")
        return val

    @classmethod
    def _load(cls):
        cls._cache = {}
        try:
            db = _get_db()
            cur = db.cursor()
            cur.execute("SELECT rule_key, value, value_type FROM business_rules")
            for row in cur.fetchall():
                raw = row["value"]
                vtype = (row.get("value_type") or "string").lower()
                if vtype == "integer":
                    raw = int(raw)
                elif vtype == "float":
                    raw = float(raw)
                elif vtype == "boolean":
                    raw = raw.lower() in ("true", "1", "yes")
                cls._cache[row["rule_key"]] = raw
            cur.close()
            db.close()
        except Exception as exc:
            _logger.warning("BusinessRules: could not load from DB: %s", exc)

    @classmethod
    def invalidate(cls):
        cls._cache = None


def get_vector_similarity(description: str, cluster_id: str) -> float:
    """Call the vector similarity service (same process) and return the cosine score."""
    from src.vector.embedding import get_embedding
    from src.vector.validation import validate_cluster_match

    sku_vector = get_embedding(description)
    result = validate_cluster_match(sku_vector, cluster_id)
    return result.get("similarity", 0.0)


def log_audit_event(sku_id=None, event=None):
    """Persist a fail-soft audit event to the audit_log table."""
    _logger.warning("AUDIT %s sku_id=%s", event, sku_id)
    try:
        db = _get_db()
        cur = db.cursor()
        cur.execute(
            "INSERT INTO audit_log (entity_type, entity_id, action, timestamp, created_at) "
            "VALUES (%s, %s, %s, NOW(), NOW())",
            ("gate_status", sku_id, event),
        )
        db.commit()
        cur.close()
        db.close()
    except Exception:
        pass

# Primary intent -> keyword that must appear in answer_block (stemmed)
INTENT_KEYWORDS = {
    "compatibility": "compat",
    "comparison": "compar",
    "installation": "install",
    "inspiration": "inspir",
    "problem_solving": "solut",
    "safety_compliance": "safe",
    "replacement": "replac",
    "specification": "spec",
    "bulk_trade": ["bulk", "trade", "wholesale", "quantity", "pack"],
}


def _norm_intent(s: str | None) -> str:
    if not s or not s.strip():
        return ""
    return s.strip().lower().replace(" ", "_").replace("-", "_")


def _primary_intent_valid(primary: str | None) -> bool:
    if not primary:
        return False
    n = _norm_intent(primary)
    if n in VALID_PRIMARY_INTENTS_NORM:
        return True
    # Allow label match
    for v in VALID_PRIMARY_INTENTS:
        if _norm_intent(v) == n:
            return True
    return False


def _get_db():
    """PEP-249 connection — same pattern as run_decay_escalation.py."""
    import os
    import pymysql
    from urllib.parse import urlparse
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


def get_master_cluster_ids() -> set:
    """
    SOURCE: CIE_Master_Developer_Build_Spec.docx Section 7 (G1 Gate).
    SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.2 (cluster_master).
    Authoritative source for G1 validation is the cluster_master table,
    is_active = TRUE rows only. Env var approach is retired — DB is the
    single source of truth, consistent with the PHP layer.
    Raises RuntimeError if table is empty or unreachable.
    """
    try:
        db = _get_db()
        cur = db.cursor()
        cur.execute("SELECT cluster_id FROM cluster_master WHERE is_active = TRUE")
        rows = cur.fetchall()
        cur.close()
        db.close()
    except Exception as e:
        raise RuntimeError(
            f"G1 gate cannot run: cluster_master table is unreachable. "
            f"Original error: {e}"
        ) from e

    ids = {row["cluster_id"] for row in rows}

    if not ids:
        raise RuntimeError(
            "G1 gate cannot run: cluster_master table contains zero active "
            "clusters. Seed or activate at least one cluster before "
            "validation can proceed."
        )

    return ids


def run_g1(data: SkuValidateRequest, master_ids: set[str]) -> FailureItem | None:
    """G1: cluster_id exists in master cluster list."""
    cid = (data.cluster_id or "").strip()
    if not cid:
        return FailureItem(
            error_code="CIE_G1_CLUSTER_REQUIRED",
            detail="cluster_id is missing or empty.",
            user_message="Cluster assignment is required. Please select a cluster from the master list.",
        )
    # SOURCE: CIE_v2_3_Enforcement_Edition.pdf Section 1.1
    # get_master_cluster_ids() now raises if master list is unavailable.
    # No silent bypass permitted. Validate unconditionally.
    if cid not in master_ids:
        return FailureItem(
            error_code="CIE_G1_INVALID_CLUSTER",
            detail=f"cluster_id '{cid}' is not in the master cluster list.",
            user_message="Selected cluster is not in the master list. Please choose a valid cluster.",
        )
    return None


def run_g2(data: SkuValidateRequest) -> FailureItem | None:
    """G2: primary_intent is one of exactly 9 valid enums."""
    p = data.primary_intent
    # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 2.1 — "Exactly 1 Primary Intent"
    if isinstance(p, (list, tuple)):
        if len(p) > 1:
            return FailureItem(
                error_code="G2_DUPLICATE_PRIMARY_INTENT",
                detail=f"primary_intent contains {len(p)} values; exactly 1 is required.",
                user_message="Only 1 main search intent is allowed. Remove the extra intent.",
            )
        p = p[0] if len(p) == 1 else None
    if not p or not str(p).strip():
        return FailureItem(
            error_code="G2_PRIMARY_INTENT_REQUIRED",
            detail="primary_intent is required.",
            user_message="Primary intent must be selected from the locked 9-intent taxonomy.",
        )
    if not _primary_intent_valid(p):
        return FailureItem(
            error_code="G2_INVALID_INTENT",
            detail=f"primary_intent '{p}' is not in the locked 9-intent taxonomy.",
            user_message="Primary intent must be one of: Compatibility, Comparison, Problem-Solving, Inspiration, Specification, Installation, Safety/Compliance, Replacement, Bulk/Trade.",
        )
    return None


def run_g3(data: SkuValidateRequest) -> FailureItem | dict | None:
    """G3: 1-3 secondary_intents, all different from primary, all valid enums.
    Harvest: SUSPENDED — returns N/A."""
    tier = (data.tier or "").strip().lower()

    # Harvest tier: G3 is OPTIONAL/SUSPENDED — return N/A immediately.
    if tier == "harvest":
        return {"status": "N/A", "message": None}

    primary = _norm_intent(data.primary_intent)
    secondaries = [s for s in (data.secondary_intents or []) if s and str(s).strip()]

    for s in secondaries:
        if _norm_intent(s) == primary:
            return FailureItem(
                error_code="G3_SECONDARY_MATCHES_PRIMARY",
                detail="A secondary intent cannot match the primary intent.",
                user_message="Secondary intents must all be different from the primary intent.",
            )
        if not _primary_intent_valid(s):
            return FailureItem(
                error_code="G3_INVALID_SECONDARY_INTENT",
                detail=f"Secondary intent '{s}' is not in the 9-intent taxonomy.",
                user_message="Each secondary intent must be from the locked 9-intent taxonomy.",
            )

    count = len(secondaries)
    if tier == "kill":
        if count > 0:
            return FailureItem(
                error_code="G3_KILL_NO_SECONDARIES",
                detail="Kill-tier SKUs may not have any secondary intents.",
                user_message="Kill-tier SKUs cannot have secondary intents.",
            )
        return None
    if tier in ("hero", "support"):
        if count < 1:
            return FailureItem(
                error_code="G3_MIN_SECONDARIES",
                detail="Hero/Support SKUs require at least 1 secondary intent.",
                user_message="At least one secondary intent is required for Hero and Support tiers.",
            )
        if count > 3:
            return FailureItem(
                error_code="G3_MAX_SECONDARIES",
                detail=f"Hero/Support SKUs allow maximum 3 secondary intents. Found: {count}.",
                user_message="Maximum 3 secondary intents allowed",
            )
    return None


def run_g4(data: SkuValidateRequest) -> FailureItem | None:
    """G4: answer_block char-count check AND contains primary intent keyword. Harvest: SUSPENDED."""
    answer = (data.answer_block or "").strip()
    length = len(answer)
    min_chars = BusinessRules.get('gates.answer_block_min_chars')
    max_chars = BusinessRules.get('gates.answer_block_max_chars')
    if length < min_chars:
        return FailureItem(
            error_code="G4_ANSWER_TOO_SHORT",
            detail=f"answer_block has {length} characters; minimum is {min_chars}.",
            user_message=f"Answer block must be between {min_chars} and {max_chars} characters.",
        )
    if length > max_chars:
        return FailureItem(
            error_code="G4_ANSWER_TOO_LONG",
            detail=f"answer_block has {length} characters; maximum is {max_chars}.",
            user_message=f"Answer block must be between {min_chars} and {max_chars} characters.",
        )
    primary_norm = _norm_intent(data.primary_intent)
    keyword = INTENT_KEYWORDS.get(primary_norm, primary_norm.replace("_", "")[:6] if primary_norm else "")
    if keyword and keyword not in answer.lower():
        return FailureItem(
            error_code="G4_KEYWORD_MISSING",
            detail=f"answer_block must contain the primary intent keyword (e.g. '{keyword}').",
            user_message="Answer block must include wording that reflects the primary intent.",
        )
    return None


def run_g5(data: SkuValidateRequest) -> list[FailureItem]:
    """G5: at least 2 best_for AND at least 1 not_for. Harvest: SUSPENDED.
    Returns ALL failures simultaneously per §1.2 spec requirement."""
    failures: list[FailureItem] = []
    best = [x for x in (data.best_for or []) if x and str(x).strip()]
    not_f = [x for x in (data.not_for or []) if x and str(x).strip()]
    if len(best) < 2:
        failures.append(FailureItem(
            error_code="G5_BEST_FOR_MIN",
            detail=f"best_for has {len(best)} entries; minimum is 2.",
            user_message="At least 2 Best-For applications are required.",
        ))
    if len(not_f) < 1:
        failures.append(FailureItem(
            error_code="G5_NOT_FOR_MIN",
            detail=f"not_for has {len(not_f)} entries; minimum is 1.",
            user_message="At least 1 Not-For application is required.",
        ))
    return failures


def run_g6(data: SkuValidateRequest) -> list[FailureItem]:
    """
    SOURCE: CLAUDE.md §6 | CIE_Master_Developer_Build_Spec.docx §7
    G6 — Description Quality Gate
    Checks: (1) min word count from BusinessRules, (2) vector similarity >= gates.vector_similarity_min
    Returns list of failure dicts (empty list = PASS).
    Both failures returned simultaneously if both fail.
    Suspended for harvest and kill tiers.
    """
    failures: list[FailureItem] = []

    tier = (data.tier or "").strip().lower()
    if tier in ("harvest", "kill"):
        return []  # Gate suspended per CIE_Master_Developer_Build_Spec.docx §7

    description = (data.description or "") or ""

    # Check 1 — Word count (§5.3: gates.description_word_count_min not in 52 rules; hard-coded 50)
    min_words = 50
    actual_words = len(description.split())
    if actual_words < min_words:
        failures.append(FailureItem(
            error_code="CIE_G6_DESCRIPTION_TOO_SHORT",
            detail=f"Description has {actual_words} words. Minimum is {min_words}.",
            user_message=(
                f"Your description is {actual_words} words. "
                f"Add at least {min_words - actual_words} more words. "
                "Write to solve the problem this product addresses, "
                "not to list physical attributes."
            ),
        ))

    # Check 2 — Vector similarity
    # SOURCE: CIE_v2.3_Enforcement_Edition.pdf §1.2 | CIE_v2.3.1_Enforcement_Dev_Spec.pdf §8.2.2
    vector_min = BusinessRules.get("gates.vector_similarity_min")
    cluster_id = (data.cluster_id or "") or ""
    try:
        similarity = get_vector_similarity(description, cluster_id)
        if similarity < vector_min:
            failures.append(FailureItem(
                error_code="CIE_G6_SEMANTIC_MISMATCH",
                detail="Description semantic similarity is below the required threshold.",
                user_message=(
                    f"Description does not align with Cluster Intent [{cluster_id}]. "
                    "Rewrite to address the problem this product solves, "
                    "not its physical attributes."
                ),
            ))
    except Exception:
        # Fail-soft: vector service unavailable — warn but do not block save.
        # SOURCE: CIE_Master_Developer_Build_Spec.docx §7 VEC row
        log_audit_event(sku_id=data.sku_id, event="G6_VECTOR_FAIL_SOFT")

    return failures


def run_g61(data: SkuValidateRequest) -> FailureItem | None:
    """G6.1: intents match tier restrictions. Harvest: primary Specification + max 1 secondary. Kill: none."""
    tier = (data.tier or "").strip().lower()
    # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G6.1 + §7.3 Error Code Reference
    if tier == "kill":
        return FailureItem(
            error_code="CIE_G6_1_KILL_EDIT_BLOCKED",
            detail="Kill-tier SKU: all edits blocked.",
            user_message="This SKU is in Kill tier. No content edits are permitted. Contact your Portfolio Holder to request a tier review.",
        )
    if tier == "harvest":
        primary_norm = _norm_intent(data.primary_intent)
        if primary_norm != "specification":
            return FailureItem(
                error_code="G61_HARVEST_SPEC_PRIMARY",
                detail="Harvest tier requires primary intent to be Specification.",
                user_message="Harvest tier allows only Specification as primary intent.",
            )
        secondaries = [s for s in (data.secondary_intents or []) if s and str(s).strip()]
        if len(secondaries) > 1:
            return FailureItem(
                error_code="G61_HARVEST_MAX_ONE_SECONDARY",
                detail="Harvest tier allows at most 1 secondary intent.",
                user_message="Harvest tier allows at most one secondary intent.",
            )
    return None


def run_g7(data: SkuValidateRequest) -> FailureItem | dict | None:
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §7 + §7.1
    """G7: Expert Authority — non-empty + AI Agent specificity check for Hero/Support.
    Harvest: SUSPENDED. Kill: N/A."""
    from .ai_agent_service import check_specificity

    tier = (data.tier or "").strip().lower()

    if tier == "kill":
        return {"gate": "G7", "status": "na"}

    if tier == "harvest":
        return {"gate": "G7", "status": "suspended"}

    expert = (data.expert_authority or "").strip()

    if not expert:
        return FailureItem(
            error_code="EXPERT_AUTHORITY_MISSING",
            detail="expert_authority is required for Hero and Support tiers.",
            user_message=(
                "Add an Expert Authority statement. "
                "Example: Compliant with BS 7671 18th Edition. CE + UKCA marked. Rated to 3A/60W."
            ),
        )

    if not check_specificity(expert):
        return FailureItem(
            error_code="EXPERT_AUTHORITY_TOO_GENERIC",
            detail="expert_authority does not reference a specific standard, certification, or rated specification.",
            user_message=(
                "Your Expert Authority statement must reference a specific standard, "
                "certification, or rated specification. Replace phrases like 'high quality' "
                "with a standard name or certification mark."
            ),
        )

    return None


def run_all_gates(data: SkuValidateRequest, master_cluster_ids: set[str]) -> list[FailureItem]:
    """Run gates IN ORDER. Harvest: skip G4, G5, G7. Kill: only G1 and G6. Return all failures."""
    failures: list[FailureItem] = []
    tier = (data.tier or "").strip().lower()
    is_harvest = tier == "harvest"
    is_kill = tier == "kill"

    # G1: always (and for kill only G1 + G6)
    f = run_g1(data, master_cluster_ids)
    if f:
        failures.append(f)
    if is_kill:
        failures.extend(run_g6(data))
        # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G6.1 — Kill path must emit G6.1 block
        g61_result = run_g61(data)
        if g61_result is not None:
            failures.append(g61_result)
        return failures

    # G2
    f = run_g2(data)
    if f:
        failures.append(f)
    # G3 (returns dict {"status": "N/A"} for Harvest — not a failure)
    f = run_g3(data)
    if f and not isinstance(f, dict):
        failures.append(f)
    # G4: suspended for harvest
    if not is_harvest:
        f = run_g4(data)
        if f:
            failures.append(f)
    # G5: suspended for harvest — returns list of all failures (not early-return)
    if not is_harvest:
        failures.extend(run_g5(data))
    # G6 — Description Quality (returns list; both failures reported simultaneously)
    failures.extend(run_g6(data))
    # G6.1
    f = run_g61(data)
    if f:
        failures.append(f)
    # G7: Expert Authority (returns dict for N/A / suspended — not a failure)
    f = run_g7(data)
    if f and not isinstance(f, dict):
        failures.append(f)

    return failures
