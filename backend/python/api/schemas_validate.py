"""
Pydantic schemas for POST /api/v1/sku/validate (CIE v2.3.1 Section 7.2/7.3).
SOURCE: openapi.yaml ValidationResponse, Hardening Addendum §1.4
"""
from typing import Literal, Optional, Dict
from pydantic import BaseModel, Field


# SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — locked 9-intent taxonomy keys
VALID_PRIMARY_INTENTS = frozenset({
    'problem_solving',
    'comparison',
    'compatibility',
    'specification',
    'installation',
    'troubleshooting',
    'inspiration',
    'regulatory',
    'replacement',
})


def _base_norm(value: str) -> str:
    # SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — normalize label or API key to snake_case stem
    if not value or not str(value).strip():
        return ''
    s = str(value).strip().lower().replace(' ', '_').replace('-', '_').replace('/', '_')
    while '__' in s:
        s = s.replace('__', '_')
    return s.strip('_')


# SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — legacy API keys from pre-§8.3 seeds map to canonical keys
# FIX: G2-01 — bulk_trade is not in the locked 9-intent taxonomy; do not alias to another intent.
_LEGACY_INTENT_KEY_ALIASES = {
    'safety_compliance': 'regulatory',
}

# SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — collapse normalized labels to canonical keys
_LABEL_COLLAPSE_TO_KEY = {
    'regulatory_safety': 'regulatory',
    'inspiration_style': 'inspiration',
    'installation_how_to': 'installation',
    'replacement_refill': 'replacement',
}


def resolve_canonical_intent_key(value: str | None) -> str:
    # SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 + §2.1 G2 — single canonical key for enum check
    s = _base_norm(str(value) if value is not None else '')
    if not s:
        return ''
    s = _LEGACY_INTENT_KEY_ALIASES.get(s, s)
    s = _LABEL_COLLAPSE_TO_KEY.get(s, s)
    return s


# SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — normalized allow-list (same as keys; used by G2/G3)
VALID_PRIMARY_INTENTS_NORM = frozenset(VALID_PRIMARY_INTENTS)


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
    action: Literal['save', 'publish'] = 'save'


class FailureItem(BaseModel):
    # SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §7.3 — error_code is from closed Page 18 set or null when omitted by gate bug
    error_code: str | None = None
    detail: str
    user_message: str
    gate: Optional[str] = None  # SOURCE: ENF§7.2 — gate key for response (e.g. G1_cluster_id)


class SkuValidateResponsePass(BaseModel):
    status: Literal['pass'] = 'pass'
    message: str = 'All gates passed.'


class SkuValidateResponseFail(BaseModel):
    status: Literal['fail'] = 'fail'
    failures: list[FailureItem]
    message: str = 'One or more gates failed.'


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
