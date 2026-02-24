"""
CIE v2.3.1 Gate validation for SKU validate endpoint.
8 gates IN ORDER: G1, G2, G3, G4, G5, G6, G6.1, G7.
Harvest: G4, G5, G7 SUSPENDED. Kill: only G1 and G6.
"""
from __future__ import annotations

from typing import Any

from .schemas_validate import (
    SkuValidateRequest,
    FailureItem,
    VALID_PRIMARY_INTENTS,
    VALID_PRIMARY_INTENTS_NORM,
)

# Primary intent -> keyword that must appear in answer_block (stemmed)
INTENT_KEYWORDS = {
    "compatibility": "compat",
    "comparison": "compar",
    "installation": "install",
    "inspiration": "inspir",
    "problem_solving": "solut",
    "regulatory": "safe",
    "replacement": "replac",
    "specification": "spec",
    "troubleshooting": "shoot",
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


def get_master_cluster_ids() -> set[str]:
    """Load master cluster list from env or default. CIE_MASTER_CLUSTER_IDS=id1,id2"""
    import os
    raw = os.environ.get("CIE_MASTER_CLUSTER_IDS", "")
    if not raw:
        return set()
    return {x.strip() for x in raw.split(",") if x.strip()}


def run_g1(data: SkuValidateRequest, master_ids: set[str]) -> FailureItem | None:
    """G1: cluster_id exists in master cluster list."""
    cid = (data.cluster_id or "").strip()
    if not cid:
        return FailureItem(
            error_code="G1_CLUSTER_REQUIRED",
            detail="cluster_id is missing or empty.",
            user_message="Cluster assignment is required. Please select a cluster from the master list.",
        )
    if master_ids and cid not in master_ids:
        return FailureItem(
            error_code="G1_CLUSTER_INVALID",
            detail=f"cluster_id '{cid}' is not in the master cluster list.",
            user_message="Selected cluster is not in the master list. Please choose a valid cluster.",
        )
    return None


def run_g2(data: SkuValidateRequest) -> FailureItem | None:
    """G2: primary_intent is one of exactly 9 valid enums."""
    p = data.primary_intent
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
            user_message="Primary intent must be one of: Problem Solving, Comparison, Compatibility, Specification, Installation, Troubleshooting, Inspiration, Regulatory, Replacement.",
        )
    return None


def run_g3(data: SkuValidateRequest) -> FailureItem | None:
    """G3: 1-3 secondary_intents, all different from primary, all valid enums."""
    primary = _norm_intent(data.primary_intent)
    secondaries = [s for s in (data.secondary_intents or []) if s and str(s).strip()]
    tier = (data.tier or "").strip().lower()

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
    if tier == "harvest":
        if count > 1:
            return FailureItem(
                error_code="G3_HARVEST_MAX_ONE",
                detail="Harvest-tier SKUs allow maximum 1 secondary intent.",
                user_message="Harvest tier allows at most 1 secondary intent.",
            )
        return None
    if tier in ("hero", "support"):
        if count < 1:
            return FailureItem(
                error_code="G3_MIN_SECONDARIES",
                detail="Hero/Support SKUs require at least 1 secondary intent.",
                user_message="At least one secondary intent is required for Hero and Support tiers.",
            )
        if tier == "hero" and count > 3:
            return FailureItem(
                error_code="G3_HERO_MAX_THREE",
                detail="Hero-tier SKUs allow maximum 3 secondary intents.",
                user_message="Hero tier allows at most 3 secondary intents.",
            )
        if tier == "support" and count > 2:
            return FailureItem(
                error_code="G3_SUPPORT_MAX_TWO",
                detail="Support-tier SKUs allow maximum 2 secondary intents.",
                user_message="Support tier allows at most 2 secondary intents.",
            )
    return None


def run_g4(data: SkuValidateRequest) -> FailureItem | None:
    """G4: answer_block 250-300 chars AND contains primary intent keyword. Harvest: SUSPENDED."""
    answer = (data.answer_block or "").strip()
    length = len(answer)
    if length < 250:
        return FailureItem(
            error_code="G4_ANSWER_TOO_SHORT",
            detail=f"answer_block has {length} characters; minimum is 250.",
            user_message="Answer block must be between 250 and 300 characters.",
        )
    if length > 300:
        return FailureItem(
            error_code="G4_ANSWER_TOO_LONG",
            detail=f"answer_block has {length} characters; maximum is 300.",
            user_message="Answer block must be between 250 and 300 characters.",
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


def run_g5(data: SkuValidateRequest) -> FailureItem | None:
    """G5: at least 2 best_for AND at least 1 not_for. Harvest: SUSPENDED."""
    best = [x for x in (data.best_for or []) if x and str(x).strip()]
    not_f = [x for x in (data.not_for or []) if x and str(x).strip()]
    if len(best) < 2:
        return FailureItem(
            error_code="G5_BEST_FOR_MIN",
            detail=f"best_for has {len(best)} entries; minimum is 2.",
            user_message="At least 2 Best-For applications are required.",
        )
    if len(not_f) < 1:
        return FailureItem(
            error_code="G5_NOT_FOR_MIN",
            detail="not_for has 0 entries; minimum is 1.",
            user_message="At least 1 Not-For application is required.",
        )
    return None


def run_g6(data: SkuValidateRequest) -> FailureItem | None:
    """G6: tier is one of hero, support, harvest, kill."""
    t = (data.tier or "").strip().lower()
    valid = {"hero", "support", "harvest", "kill"}
    if t not in valid:
        return FailureItem(
            error_code="G6_INVALID_TIER",
            detail=f"tier must be one of: {', '.join(sorted(valid))}.",
            user_message="Tier must be Hero, Support, Harvest, or Kill.",
        )
    return None


def run_g61(data: SkuValidateRequest) -> FailureItem | None:
    """G6.1: intents match tier restrictions. Harvest: primary Specification + max 1 secondary. Kill: none."""
    tier = (data.tier or "").strip().lower()
    if tier == "kill":
        # Kill: no intents (handled in G3 for secondaries; primary can be present but we only run G1+G6 for kill)
        return None
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


def run_g7(data: SkuValidateRequest) -> FailureItem | None:
    """G7: expert_authority non-empty for hero/support. Harvest: SUSPENDED."""
    tier = (data.tier or "").strip().lower()
    if tier not in ("hero", "support"):
        return None
    expert = (data.expert_authority or "").strip()
    if not expert:
        return FailureItem(
            error_code="G7_EXPERT_REQUIRED",
            detail="expert_authority is required for Hero and Support tiers.",
            user_message="Expert authority is required for Hero and Support tiers.",
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
        f = run_g6(data)
        if f:
            failures.append(f)
        return failures

    # G2
    f = run_g2(data)
    if f:
        failures.append(f)
    # G3
    f = run_g3(data)
    if f:
        failures.append(f)
    # G4: suspended for harvest
    if not is_harvest:
        f = run_g4(data)
        if f:
            failures.append(f)
    # G5: suspended for harvest
    if not is_harvest:
        f = run_g5(data)
        if f:
            failures.append(f)
    # G6
    f = run_g6(data)
    if f:
        failures.append(f)
    # G6.1
    f = run_g61(data)
    if f:
        failures.append(f)
    # G7: suspended for harvest
    if not is_harvest:
        f = run_g7(data)
        if f:
            failures.append(f)

    return failures
