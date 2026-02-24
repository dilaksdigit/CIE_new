"""
Pydantic schemas for POST /api/v1/sku/validate (CIE v2.3.1 Section 7.2/7.3).
"""
from typing import Literal
from pydantic import BaseModel, Field


# Exactly 9 valid intents (locked taxonomy)
VALID_PRIMARY_INTENTS = frozenset({
    "Problem Solving", "Comparison", "Compatibility", "Specification",
    "Installation", "Troubleshooting", "Inspiration", "Regulatory", "Replacement",
})
# Normalized (lowercase, with optional hyphen/underscore) for comparison
VALID_PRIMARY_INTENTS_NORM = frozenset(
    s.lower().replace(" ", "_").replace("-", "_") for s in VALID_PRIMARY_INTENTS
)


class SkuValidateRequest(BaseModel):
    sku_id: str | None = None
    cluster_id: str | None = None
    tier: str | None = None
    primary_intent: str | None = None
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


class SkuValidateResponsePass(BaseModel):
    status: Literal["pass"] = "pass"
    message: str = "All gates passed."


class SkuValidateResponseFail(BaseModel):
    status: Literal["fail"] = "fail"
    failures: list[FailureItem]
    message: str = "One or more gates failed."
