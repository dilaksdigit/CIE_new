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
    resolve_canonical_intent_key,
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


def get_vector_similarity(description: str, cluster_id: str) -> float | None:
    """Call vector validation; returns cosine score or None when embedding/degraded pending."""
    from src.vector.embedding import get_embedding
    from src.vector.validation import validate_cluster_match

    sku_vector = get_embedding(description)
    threshold = float(BusinessRules.get("gates.vector_similarity_min"))
    result = validate_cluster_match(sku_vector, cluster_id, threshold=threshold)
    if result.get("status") == "pending":
        return None
    return result.get("similarity")


def log_audit_event(
    sku_id=None, event=None, detail=None, actor_id=None, actor_role=None, entity_type: str | None = None
) -> bool:
    """SOURCE: MASTER§17 — persist audit event to the audit_log table."""
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.5 — every validation must be auditable; failures must not be silent
    # SOURCE: CIE_v231_Developer_Build_Pack.pdf §7.1 — audit_log requires actor_id, actor_role NOT NULL
    aid = actor_id if actor_id is not None else "SYSTEM"
    role = actor_role if actor_role is not None else "system"
    etype = entity_type if entity_type is not None else "gate_status"
    _logger.warning("AUDIT %s sku_id=%s %s", event, sku_id, f"detail={detail}" if detail else "")
    try:
        db = _get_db()
        cur = db.cursor()
        action_val = event if not detail else f"{event}|{detail}"
        cur.execute(
            "INSERT INTO audit_log (entity_type, entity_id, action, actor_id, actor_role, timestamp, created_at) "
            "VALUES (%s, %s, %s, %s, %s, NOW(), NOW())",
            (etype, sku_id, action_val[:255] if action_val else event, str(aid)[:100], str(role)[:30]),
        )
        db.commit()
        cur.close()
        db.close()
        return True
    except Exception as e:
        _logger.error("Audit log insert failed: %s", e, exc_info=True)
        return False

# SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — keys must be 1:1 with intent taxonomy
INTENT_KEYWORDS = {
    "problem_solving": ["solut", "problem", "solve", "solution"],
    "comparison": ["compar", "compare", "versus", "alternative"],
    "compatibility": ["compat", "fit", "suitable", "works with"],
    "specification": ["spec", "technical", "rating", "dimension"],
    "installation": ["install", "setup", "fitting", "mount", "how to"],
    "troubleshooting": ["troubleshoot", "trouble", "fix", "issue", "problem", "repair", "diagnose"],
    "inspiration": ["inspir", "style", "decor", "look", "design", "aesthetic"],
    "regulatory": ["regulation", "safety", "compliance", "standard", "certified", "bs 7671", "ce", "ip rating"],
    "replacement": ["replac", "refill", "spare", "consumable"],
}


def _norm_intent(s: str | None) -> str:
    # SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — canonical key resolution (incl. legacy aliases)
    return resolve_canonical_intent_key(s)


def _primary_intent_valid(primary: str | None) -> bool:
    if not primary:
        return False
    # SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 + §2.1 G2 — enum check against locked nine keys
    return _norm_intent(str(primary)) in VALID_PRIMARY_INTENTS


def _get_db():
    """PEP-249 connection — same pattern as run_decay_escalation.py."""
    from src.utils.mysql_connect import pymysql_connect_dict_cursor

    return pymysql_connect_dict_cursor()


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
    """G1: cluster_id exists in master cluster list. SOURCE: ENF§7.2 — gate key G1_cluster_id."""
    cid = (data.cluster_id or "").strip()
    # SOURCE: ENF§Page18 — only CIE_G1_INVALID_CLUSTER defined for G1
    if not cid:
        return FailureItem(
            gate="G1_cluster_id",
            error_code="CIE_G1_INVALID_CLUSTER",
            detail="cluster_id is missing or empty.",
            user_message="Cluster assignment is required. Please select a cluster from the master list.",
        )
    # SOURCE: CIE_v2_3_Enforcement_Edition.pdf Section 1.1
    if cid not in master_ids:
        return FailureItem(
            gate="G1_cluster_id",
            error_code="CIE_G1_INVALID_CLUSTER",
            detail=f"cluster_id '{cid}' is not in the master cluster list.",
            user_message="Selected cluster is not in the master list. Please choose a valid cluster.",
        )
    return None


def run_g2(data: SkuValidateRequest) -> FailureItem | None:
    """G2: primary_intent is one of exactly 9 valid enums."""
    p = data.primary_intent
    # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 2.1 — "Exactly 1 Primary Intent"
    # SOURCE: ENF§Page18 — only CIE_G2_INVALID_INTENT defined for G2
    if isinstance(p, (list, tuple)):
        if len(p) > 1:
            return FailureItem(
                gate="G2_primary_intent",
                error_code="CIE_G2_INVALID_INTENT",
                detail=f"primary_intent contains {len(p)} values; exactly 1 is required.",
                user_message="Only 1 main search intent is allowed. Remove the extra intent.",
            )
        p = p[0] if len(p) == 1 else None
    if not p or not str(p).strip():
        return FailureItem(
            gate="G2_primary_intent",
            error_code="CIE_G2_INVALID_INTENT",
            detail="primary_intent is required.",
            user_message="Primary intent must be selected from the locked 9-intent taxonomy.",
        )
    if not _primary_intent_valid(p):
        return FailureItem(
            gate="G2_primary_intent",
            error_code="CIE_G2_INVALID_INTENT",
            detail=f"primary_intent '{p}' is not in the locked 9-intent taxonomy.",
            user_message=(
                "Primary intent must be one of: Problem-Solving, Comparison, Compatibility, Specification, "
                "Installation / How-To, Troubleshooting, Inspiration / Style, Regulatory / Safety, Replacement / Refill."
            ),
        )
    return None


def run_g3(data: SkuValidateRequest) -> FailureItem | dict | None:
    """G3: 1-3 secondary_intents, all different from primary, all valid enums.
    SOURCE: ENF§2.2, ENF§8.3 — Harvest G3 OPTIONAL, max 1 from allowed_intents [1,3,4].
    SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G3 — Harvest duplicate check uses normalized secondary vs primary."""
    tier = (data.tier or "").strip().lower()

    # SOURCE: ENF§2.2, ENF§8.3 — Harvest G3 OPTIONAL, max 1 from allowed_intents [1,3,4]
    if tier == "harvest":
        secondaries = [s for s in (data.secondary_intents or []) if s and str(s).strip()]
        if not secondaries or len(secondaries) == 0:
            return {"status": "N/A", "message": None}
        # SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — must throw on missing key.
        max_harvest = int(BusinessRules.get("gates.harvest_max_secondary"))
        if len(secondaries) > max_harvest:
            return FailureItem(
                gate="G3_secondary_intents",
                error_code="CIE_G3_SECONDARY_COUNT",
                detail=f"Harvest tier allows max {max_harvest} secondary intent, got {len(secondaries)}",
                user_message=f"Harvest products allow a maximum of {max_harvest} supporting intent.",
            )
        allowed_keys = ["problem_solving", "compatibility", "specification"]
        sec_norm = _norm_intent(secondaries[0])
        if sec_norm not in allowed_keys:
            # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1, Page 18 — tier-locked intent is G6.1, not G3 count
            return FailureItem(
                gate="G6_1_tier_lock",
                error_code="CIE_G6_1_TIER_INTENT_BLOCKED",
                detail=f"Harvest tier SKU attempted {sec_norm} intent. Only problem_solving, compatibility, specification allowed.",
                user_message="This SKU is Harvest tier. Only Specification + 1 other intent allowed.",
            )
        primary = _norm_intent(data.primary_intent)
        # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G3 — compare normalized forms (count + uniqueness)
        if sec_norm == primary:
            return FailureItem(
                gate="G3_secondary_intents",
                error_code="CIE_G3_SECONDARY_DUPLICATE",
                detail="Secondary intent same as primary",
                user_message="Your supporting intent cannot be the same as the main intent.",
            )
        return {"status": "pass", "message": None}

    primary = _norm_intent(data.primary_intent)
    secondaries = [s for s in (data.secondary_intents or []) if s and str(s).strip()]

    for s in secondaries:
        if _norm_intent(s) == primary:
            return FailureItem(
                gate="G3_secondary_intents",
                error_code="CIE_G3_SECONDARY_DUPLICATE",
                detail="A secondary intent cannot match the primary intent.",
                user_message="Secondary intents must all be different from the primary intent.",
            )
        if not _primary_intent_valid(s):
            # GAP_LOG: No spec code for invalid secondary enum value. SOURCE: ENF Page 18 — keep CIE_G3_SECONDARY_COUNT until architect extends table
            return FailureItem(
                gate="G3_secondary_intents",
                error_code="CIE_G3_SECONDARY_COUNT",
                detail=f"Secondary intent '{s}' is not in the locked 9-intent taxonomy.",
                user_message="Each secondary intent must be from the locked 9-intent taxonomy.",
            )

    count = len(secondaries)
    if tier == "kill":
        return {"status": "N/A", "message": None}
    # SOURCE: CIE_Master_Developer_Build_Spec §5 — gates.{tier}_max_secondary from business_rules
    if tier in ("hero", "support"):
        tier_max_key = f"gates.{tier}_max_secondary"
        # SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — no silent fallback defaults.
        max_secondary = int(BusinessRules.get(tier_max_key))
        if count < 1:
            return FailureItem(
                gate="G3_secondary_intents",
                error_code="CIE_G3_SECONDARY_COUNT",
                detail=f"Minimum 1 secondary intent required, got {count}",
                user_message="At least 1 supporting intent is required.",
            )
        if count > max_secondary:
            return FailureItem(
                gate="G3_secondary_intents",
                error_code="CIE_G3_SECONDARY_COUNT",
                detail=f"{tier.title()} tier allows max {max_secondary} secondary intents, got {count}",
                user_message=f"Maximum {max_secondary} supporting intents allowed for {tier.title()} products.",
            )
    return None


def run_g4(data: SkuValidateRequest) -> FailureItem | None:
    """G4: answer_block char-count check AND contains primary intent keyword. Harvest: SUSPENDED."""
    answer = (data.answer_block or "").strip()
    length = len(answer)
    min_chars = BusinessRules.get('gates.answer_block_min_chars')
    max_chars = BusinessRules.get('gates.answer_block_max_chars')
    # SOURCE: ENF§Page18 — CIE_G4_CHAR_LIMIT, CIE_G4_KEYWORD_MISSING
    if length < min_chars:
        return FailureItem(
            gate="G4_answer_block",
            error_code="CIE_G4_CHAR_LIMIT",
            detail=f"answer_block has {length} characters; minimum is {min_chars}.",
            user_message=f"Answer block must be between {min_chars} and {max_chars} characters.",
        )
    if length > max_chars:
        return FailureItem(
            gate="G4_answer_block",
            error_code="CIE_G4_CHAR_LIMIT",
            detail=f"answer_block has {length} characters; maximum is {max_chars}.",
            user_message=f"Answer block must be between {min_chars} and {max_chars} characters.",
        )
    primary_norm = _norm_intent(data.primary_intent)
    keyword = INTENT_KEYWORDS.get(primary_norm)
    if keyword:
        answer_lower = answer.lower()
        stems = keyword if isinstance(keyword, list) else [keyword]
        keyword_found = any(k in answer_lower for k in stems)
        if not keyword_found:
            return FailureItem(
                gate="G4_answer_block",
                error_code="CIE_G4_KEYWORD_MISSING",
                detail="answer_block must contain the primary intent keyword.",
                user_message="Answer block must include wording that reflects the primary intent.",
            )
    return None


def run_g5(data: SkuValidateRequest) -> list[FailureItem]:
    """G5: best_for / not_for minimum counts from BusinessRules. Harvest: SUSPENDED.
    Returns ALL failures simultaneously per §1.2 spec requirement."""
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5, §7 — G5 thresholds from BusinessRules (seed: 2 / 1)
    failures: list[FailureItem] = []
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — gate thresholds from BusinessRules only (no numeric fallbacks)
    best_for_min = int(BusinessRules.get("gates.best_for_min_entries"))
    not_for_min = int(BusinessRules.get("gates.not_for_min_entries"))
    best = [x for x in (data.best_for or []) if x and str(x).strip()]
    not_f = [x for x in (data.not_for or []) if x and str(x).strip()]
    # SOURCE: ENF§Page18 — only CIE_G5_BESTFOR_COUNT defined for G5
    if len(best) < best_for_min:
        failures.append(FailureItem(
            gate="G5_best_not_for",
            error_code="CIE_G5_BESTFOR_COUNT",
            detail=f"best_for has {len(best)} entries; minimum is {best_for_min}.",
            user_message=f"At least {best_for_min} Best-For applications are required.",
        ))
    if len(not_f) < not_for_min:
        failures.append(FailureItem(
            gate="G5_best_not_for",
            error_code="CIE_G5_BESTFOR_COUNT",
            detail=f"not_for has {len(not_f)} entries; minimum is {not_for_min}.",
            user_message=f"At least {not_for_min} Not-For application is required.",
        ))
    return failures


# SOURCE: ENF§2.2 — G6 = tier enum only (SKU Tier Tag)
def run_g6(data: SkuValidateRequest) -> FailureItem | None:
    """G6: Validate tier is a valid enum value. Vector check is separate (run_vector_check)."""
    tier = (data.tier or "").strip().lower()
    if tier not in ("hero", "support", "harvest", "kill"):
        return FailureItem(
            gate="G6_tier_tag",
            error_code="CIE_G6_MISSING_TIER",
            detail=f"SKU has no valid tier assignment (got: '{data.tier}')",
            user_message="This product has no tier assignment. Contact your administrator.",
        )
    return None


# SOURCE: ENF§2.3, openapi.yaml — vector_check is separate from gates. Hardening Addendum §1.1 fail-soft.
def run_vector_check(data: SkuValidateRequest) -> tuple[dict, list[FailureItem], bool, bool]:
    """
    VEC: Description word count + vector similarity. Returns (vector_result, failures_to_append, degraded, audit_log_failed).
    On exception: vector_result.status='pending', degraded=True, save_allowed=True, publish_allowed=False.
    NOTE (Audit #3 Fix 19): Raw /sku/similarity uses pass|fail|pending for wire; this pipeline maps similarity to warn + publish rules.
    """
    failures: list[FailureItem] = []
    vector_result = {"status": "pass", "user_message": None}
    degraded = False
    audit_log_failed = False
    tier = (data.tier or "").strip().lower()
    if tier not in ("hero", "support"):
        return vector_result, failures, degraded, audit_log_failed

    description = (data.description or "") or ""
    cluster_id = (data.cluster_id or "") or ""
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — word-count minimum from BusinessRules only
    min_words = int(BusinessRules.get("gates.description_word_count_min"))
    actual_words = len(description.split())
    if actual_words < min_words:
        # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Page 18 — CIE_VEC_SIMILARITY_LOW is for cosine similarity only; word count is a pre-check (fail-soft warn)
        # GAP_LOG: no distinct spec error code for description word-count pre-check vs similarity failure
        vector_result = {
            "status": "warn",
            "user_message": "Description too short for embedding. Add more content.",
        }
        return vector_result, failures, degraded, audit_log_failed

    try:
        from src.vector.embedding import get_embedding
        from src.vector.validation import validate_cluster_match

        threshold = float(BusinessRules.get("gates.vector_similarity_min"))
        sku_vector = get_embedding(description)
        vm = validate_cluster_match(sku_vector, cluster_id, threshold=threshold)
        # SOURCE: CIE_v232_Hardening_Addendum.pdf §1.1 — embedding None → pending + degraded (policy), not pass
        if vm.get("status") == "pending" or vm.get("degraded"):
            vector_result = {
                "status": "pending",
                "user_message": "Description validation temporarily unavailable. Your changes are saved but publishing is paused until validation completes.",
            }
            degraded = True
            if not log_audit_event(sku_id=data.sku_id, event="VECTOR_FAIL_SOFT", detail=vm.get("reason", "embedding_pending")):
                audit_log_failed = True
            try:
                _queue_vector_retry(data.sku_id, description, cluster_id)
            except Exception:
                pass
        elif vm.get("status") == "warn" or (
            not vm.get("valid") and vm.get("reason") == "cosine_similarity_below_threshold"
        ):
            # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Page 18, CLAUDE.md §11 — warn only; FailureItem for audit/error code (no score in user_message)
            vector_result = {
                "status": "warn",
                "user_message": "Your content may not align with the intent. Consider revising.",
            }
            failures.append(FailureItem(
                gate="vector_check",
                error_code="CIE_VEC_SIMILARITY_LOW",
                detail="Description semantic similarity below threshold",
                user_message="Your content may not align with the intent. Consider revising.",
            ))
            if not log_audit_event(sku_id=data.sku_id, event="VECTOR_WARN", detail="similarity below threshold"):
                audit_log_failed = True
        elif vm.get("valid"):
            vector_result = {"status": "pass", "user_message": None}
        else:
            vector_result = {
                "status": "pending",
                "user_message": "Description validation temporarily unavailable. Your changes are saved but publishing is paused until validation completes.",
            }
            degraded = True
            if not log_audit_event(sku_id=data.sku_id, event="VECTOR_FAIL_SOFT", detail=str(vm.get("reason", "vector_check"))):
                audit_log_failed = True
            try:
                _queue_vector_retry(data.sku_id, description, cluster_id)
            except Exception:
                pass
    except Exception as e:
        # SOURCE: Hardening Addendum §1.1 — fail-soft: pending, not pass
        if not log_audit_event(sku_id=data.sku_id, event="VECTOR_FAIL_SOFT", detail=str(e)):
            audit_log_failed = True
        vector_result = {
            "status": "pending",
            "user_message": "Description validation temporarily unavailable. Your changes are saved but publishing is paused until validation completes.",
        }
        degraded = True
        try:
            _queue_vector_retry(data.sku_id, description, cluster_id)
        except Exception:
            pass
    return vector_result, failures, degraded, audit_log_failed


def _queue_vector_retry(sku_id, description: str, cluster_id: str) -> None:
    """SOURCE: Hardening Addendum §1.3 — queue for retry when vector service unavailable."""
    try:
        db = _get_db()
        cur = db.cursor()
        cur.execute(
            "INSERT INTO vector_retry_queue (sku_id, description, cluster_id, retry_count, max_retries, next_retry_at, status, created_at) "
            "VALUES (%s, %s, %s, 0, 5, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'queued', NOW())",
            (sku_id or "", description[:65535], cluster_id or ""),
        )
        db.commit()
        cur.close()
        db.close()
    except Exception as ex:
        _logger.warning("vector_retry_queue insert failed: %s", ex)


def run_g61(data: SkuValidateRequest) -> FailureItem | None:
    """G6.1: intents match tier restrictions. Harvest: primary Specification + max 1 secondary from [1,3,4]. Kill: absolute block."""
    tier = (data.tier or "").strip().lower()
    # SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §2.1 G6.1 — Kill: "Any edit = violation"
    # SOURCE: CLAUDE.md DECISION-006 — Kill SKU = Total Lockout
    if tier == "kill":
        return FailureItem(
            gate="G6_1_tier_lock",
            error_code="CIE_G6_1_KILL_EDIT_BLOCKED",
            detail="Kill-tier SKU: all edits blocked.",
            user_message="This product is marked as Kill tier. No editing is permitted.",
        )
    # SOURCE: ENF§8.3 — Harvest secondaries must be from allowed_intents [1,3,4]
    if tier == "harvest":
        primary_norm = _norm_intent(data.primary_intent)
        if primary_norm != "specification":
            return FailureItem(
                gate="G6_1_tier_lock",
                error_code="CIE_G6_1_TIER_INTENT_BLOCKED",
                detail="Harvest tier requires primary intent to be Specification.",
                user_message="Harvest tier allows only Specification as primary intent.",
            )
        secondaries = [s for s in (data.secondary_intents or []) if s and str(s).strip()]
        if len(secondaries) > 1:
            return FailureItem(
                gate="G6_1_tier_lock",
                error_code="CIE_G6_1_TIER_INTENT_BLOCKED",
                detail="Harvest tier allows at most 1 secondary intent.",
                user_message="Harvest tier allows at most one secondary intent.",
            )
        allowed = ["problem_solving", "compatibility", "specification"]
        for sec in secondaries:
            sec_norm = _norm_intent(sec)
            if sec_norm not in allowed:
                return FailureItem(
                    gate="G6_1_tier_lock",
                    error_code="CIE_G6_1_TIER_INTENT_BLOCKED",
                    detail=f"Harvest secondary '{sec}' not in allowed intents",
                    user_message="This product only allows Problem-Solving, Compatibility, or Specification as supporting intents.",
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

    # SOURCE: ENF§Page18 — only CIE_G7_AUTHORITY_MISSING defined for G7. ENF§7.2 gate key G7_expert_authority
    if not expert:
        return FailureItem(
            gate="G7_expert_authority",
            error_code="CIE_G7_AUTHORITY_MISSING",
            detail="expert_authority is required for Hero and Support tiers.",
            user_message=(
                "Add an Expert Authority statement. "
                "Example: Compliant with BS 7671 18th Edition. CE + UKCA marked. Rated to 3A/60W."
            ),
        )

    if not check_specificity(expert):
        return FailureItem(
            gate="G7_expert_authority",
            error_code="CIE_G7_AUTHORITY_MISSING",
            detail="expert_authority does not reference a specific standard, certification, or rated specification.",
            user_message=(
                "Your Expert Authority statement must reference a specific standard, "
                "certification, or rated specification. Replace phrases like 'high quality' "
                "with a standard name or certification mark."
            ),
        )

    return None


def _log_gate_audit(sku_id, gate_id: str, passed: bool, error_code: str | None = None) -> bool:
    # SOURCE: CIE_Master_Developer_Build_Spec.docx §17, CIE_v231_Developer_Build_Pack.pdf §7.1 — gate_pass / gate_fail per gate
    if passed:
        return log_audit_event(
            sku_id=sku_id, event="gate_pass", detail=(gate_id or "")[:255], entity_type="gate"
        )
    detail = f"{gate_id}|{error_code}" if error_code else (gate_id or "fail")
    return log_audit_event(sku_id=sku_id, event="gate_fail", detail=detail[:255], entity_type="gate")


# SOURCE: MASTER§17 — audit_log entry for every validation request
def run_all_gates(data: SkuValidateRequest, master_cluster_ids: set[str]) -> dict:
    """
    Run gates IN ORDER. Returns dict with failures, vector_result, degraded, audit_degraded.
    SOURCE: openapi.yaml ValidationResponse — caller builds full response with gates keyed by id.
    """
    audit_degraded = not log_audit_event(
        sku_id=data.sku_id,
        event="VALIDATION_REQUESTED",
        detail=f"action={getattr(data, 'action', 'save')}, tier={data.tier}",
    )
    failures: list[FailureItem] = []
    vector_result = {"status": "pass", "user_message": None}
    degraded = False
    tier = (data.tier or "").strip().lower()
    is_harvest = tier == "harvest"
    is_kill = tier == "kill"
    sid = data.sku_id

    # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G6.1 — Kill: G1–G5, G7, VEC suspended; only G6 + G6.1 (kill block).
    # FIX: G1-03 + G6-03 — Do not run G1 before Kill path; align with PHP GateValidator.
    if is_kill:
        f = run_g6(data)
        audit_degraded |= not _log_gate_audit(sid, "G6_tier_tag", f is None, getattr(f, "error_code", None) if f else None)
        if f:
            failures.append(f)
        g61_result = run_g61(data)
        audit_degraded |= not _log_gate_audit(
            sid, "G6_1_tier_lock", g61_result is None, getattr(g61_result, "error_code", None) if g61_result else None
        )
        if g61_result is not None:
            failures.append(g61_result)
        return {"failures": failures, "vector_result": vector_result, "degraded": degraded, "audit_degraded": audit_degraded}

    # G1: non–Kill tiers only
    f = run_g1(data, master_cluster_ids)
    audit_degraded |= not _log_gate_audit(sid, "G1_cluster_id", f is None, getattr(f, "error_code", None) if f else None)
    if f:
        failures.append(f)

    # GAP_LOG: Harvest primary="specification" enforced in G6.1, not G2. Functionally blocked.
    # SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.2
    # G2
    f = run_g2(data)
    audit_degraded |= not _log_gate_audit(sid, "G2_primary_intent", f is None, getattr(f, "error_code", None) if f else None)
    if f:
        failures.append(f)
    # G3
    f = run_g3(data)
    if isinstance(f, FailureItem):
        audit_degraded |= not _log_gate_audit(sid, "G3_secondary_intents", False, f.error_code)
        failures.append(f)
    else:
        audit_degraded |= not _log_gate_audit(sid, "G3_secondary_intents", True)
    # G4: suspended for harvest
    if not is_harvest:
        f = run_g4(data)
        audit_degraded |= not _log_gate_audit(sid, "G4_answer_block", f is None, getattr(f, "error_code", None) if f else None)
        if f:
            failures.append(f)
    else:
        audit_degraded |= not _log_gate_audit(sid, "G4_answer_block", True)
    # G5: suspended for harvest
    if not is_harvest:
        g5f = run_g5(data)
        if g5f:
            audit_degraded |= not _log_gate_audit(sid, "G5_best_not_for", False, g5f[0].error_code)
            failures.extend(g5f)
        else:
            audit_degraded |= not _log_gate_audit(sid, "G5_best_not_for", True)
    else:
        audit_degraded |= not _log_gate_audit(sid, "G5_best_not_for", True)
    # G6 — tier enum only
    f = run_g6(data)
    audit_degraded |= not _log_gate_audit(sid, "G6_tier_tag", f is None, getattr(f, "error_code", None) if f else None)
    if f:
        failures.append(f)
    # G6.1
    f = run_g61(data)
    audit_degraded |= not _log_gate_audit(sid, "G6_1_tier_lock", f is None, getattr(f, "error_code", None) if f else None)
    if f:
        failures.append(f)
    # G7
    f = run_g7(data)
    if isinstance(f, FailureItem):
        audit_degraded |= not _log_gate_audit(sid, "G7_expert_authority", False, f.error_code)
        failures.append(f)
    else:
        audit_degraded |= not _log_gate_audit(sid, "G7_expert_authority", True)
    # Vector check (separate from G6 per ENF§2.3, openapi.yaml)
    if tier in ("hero", "support"):
        vec_result, vec_failures, deg, vec_audit_bad = run_vector_check(data)
        vector_result = vec_result
        degraded = deg
        failures.extend(vec_failures)
        audit_degraded = audit_degraded or vec_audit_bad
        vc = vector_result.get("status") or "pass"
        v_pass = vc in ("pass", "warn")
        # SOURCE: openapi.yaml ValidationResponse.vector_check.status — enum value is `pending`, not VECTOR_PENDING.
        # FIX: VEC-05 — Audit detail uses plain `pending`; wire status remains vector_result["status"].
        audit_degraded |= not _log_gate_audit(
            sid, "vector_check", v_pass, None if v_pass else "pending"
        )

    return {"failures": failures, "vector_result": vector_result, "degraded": degraded, "audit_degraded": audit_degraded}
