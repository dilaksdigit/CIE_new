"""
Pydantic schemas for POST /api/v1/sku/validate (CIE v2.3.1 Section 7.2/7.3).
SOURCE: openapi.yaml ValidationResponse, Hardening Addendum §1.4
"""
from typing import Literal, Optional, Dict
from pydantic import BaseModel, Field


# Exactly 9 valid intents (locked taxonomy). SOURCE: CLAUDE.md §6 — 9-intent taxonomy includes Safety/Compliance and Bulk/Trade
VALID_PRIMARY_INTENTS = frozenset({
    "Compatibility",
    "Comparison",
    "Problem-Solving",
    "Inspiration",
    "Specification",
    "Installation",
    "Safety/Compliance",
    "Replacement",
    "Bulk/Trade",
})


def _norm_intent(value: str) -> str:
    # SOURCE: CLAUDE.md §6 — taxonomy includes Safety/Compliance and Bulk/Trade; normalization must accept both label form and API key form
    return value.strip().lower().replace(" ", "_").replace("-", "_").replace("/", "_")


# Normalized set: Safety/Compliance → safety_compliance, Bulk/Trade → bulk_trade, etc.
VALID_PRIMARY_INTENTS_NORM = frozenset(_norm_intent(s) for s in VALID_PRIMARY_INTENTS)


class SkuValidateRequest(BaseModel):
    sku_id: str | None = None
    cluster_id: str | None = None
    tier: str | None = None
    primary_intent: str | list[str] | None = None
    secondary_intents: list[str] = Field(default_factory=list)
    title: str | None = None
    description: str | None = None
    answer_block: str | None = None
    best_for: list[str] = Field(default_factory=list)
    not_for: list[str] = Field(default_factory=list)
    expert_authority: str | None = None
    action: Literal["save", "publish"] = "save"


class FailureItem(BaseModel):
    error_code: str
    detail: str
    user_message: str
    gate: Optional[str] = None  # SOURCE: ENF§7.2 — gate key for response (e.g. G1_cluster_id)


class SkuValidateResponsePass(BaseModel):
    status: Literal["pass"] = "pass"
    message: str = "All gates passed."


class SkuValidateResponseFail(BaseModel):
    status: Literal["fail"] = "fail"
    failures: list[FailureItem]
    message: str = "One or more gates failed."


# SOURCE: openapi.yaml ValidationResponse, Hardening Addendum §1.4
class GateResultSchema(BaseModel):
    status: str  # pass | fail | not_applicable | pending
    error_code: Optional[str] = None
    detail: Optional[str] = None
    user_message: Optional[str] = None


class VectorCheckSchema(BaseModel):
    """SOURCE: CLAUDE.md §11, openapi.yaml — NO numeric similarity in response."""
    status: str  # pass | fail | warn | pending
    user_message: Optional[str] = None


class ValidationResponseSchema(BaseModel):
    """SOURCE: openapi.yaml ValidationResponse, ENF§7.2"""
    status: str  # pass | fail | pending
    gates: Dict[str, GateResultSchema]
    vector_check: VectorCheckSchema
    degraded_mode: bool = False
    save_allowed: bool = True
    publish_allowed: bool = False
    message: Optional[str] = None
    failures: Optional[list[FailureItem]] = None
