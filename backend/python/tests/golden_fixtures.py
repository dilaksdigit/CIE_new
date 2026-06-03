# SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf — fixture loader for acceptance tests
from __future__ import annotations

import json
from pathlib import Path

from api.schemas_validate import SkuValidateRequest

GOLDEN_PATH = Path(__file__).resolve().parents[3] / "database" / "seeds" / "golden_test_data.json"

GOLDEN_GATE_TO_API = {
    "G1": "G1_cluster_id",
    "G2": "G2_primary_intent",
    "G3": "G3_secondary_intents",
    "G4": "G4_answer_block",
    "G5": "G5_best_not_for",
    "G6": "G6_tier_tag",
    "G7": "G7_expert_authority",
}

GOLDEN_STATUS_TO_API = {
    "PASS": "pass",
    "FAIL": "fail",
    "N/A": "not_applicable",
}


def load_golden_rows() -> list[dict]:
    return json.loads(GOLDEN_PATH.read_text(encoding="utf-8"))


def golden_by_code(code: str) -> dict:
    for row in load_golden_rows():
        if row.get("sku_code") == code:
            return row
    raise KeyError(f"golden sku not found: {code}")


def row_to_validate_request(row: dict, *, action: str = "publish") -> SkuValidateRequest:
    """Build SkuValidateRequest from golden pack row (content + use_case + authority)."""
    uc = row.get("use_case") or {}
    content = row.get("content") or {}
    authority = row.get("authority") or {}
    tier = (row.get("tier") or "support").strip().lower()

    secondary = uc.get("secondary_intents") or []
    if isinstance(secondary, str):
        secondary = [secondary]

    description = (
        content.get("meta_description")
        or content.get("feed_title")
        or ("quality product description " * 12)
    )

    return SkuValidateRequest(
        sku_id=row["sku_code"],
        cluster_id=uc.get("cluster_id"),
        tier=tier,
        primary_intent=uc.get("primary_intent"),
        secondary_intents=list(secondary),
        title=content.get("shopify_title") or content.get("feed_title") or row.get("product_name") or "",
        description=description,
        answer_block=content.get("ai_answer_block") or "",
        best_for=list(uc.get("best_for") or []),
        not_for=list(uc.get("not_for") or []),
        expert_authority=authority.get("expert_statement") or "",
        action=action,
    )


def expected_gate_statuses(row: dict) -> dict[str, str]:
    """Map golden gate_results (G1..G7) to API gate key → expected status."""
    gr = (row.get("expected_outputs") or {}).get("gate_results") or {}
    tier = (row.get("tier") or "").strip().lower()
    out: dict[str, str] = {}
    for gk, api_key in GOLDEN_GATE_TO_API.items():
        raw = gr.get(gk, "PASS")
        out[api_key] = GOLDEN_STATUS_TO_API.get(str(raw).upper(), "pass")
    # SOURCE: ENF§2.2 — Kill tier suspends G1–G5, G7 (pack may label G1 PASS; API uses not_applicable)
    if tier == "kill":
        for key in (
            "G1_cluster_id",
            "G2_primary_intent",
            "G3_secondary_intents",
            "G4_answer_block",
            "G5_best_not_for",
            "G7_expert_authority",
        ):
            out[key] = "not_applicable"
        out["G6_tier_tag"] = "pass"
    return out


def expected_publish_allowed(row: dict) -> bool:
    gr = (row.get("expected_outputs") or {}).get("gate_results") or {}
    overall = str(gr.get("overall", "")).upper()
    if overall in ("KILL_EXCLUDED", "GATE_FAIL"):
        return False
    return bool(gr.get("submit_enabled", overall in ("ALL_PASS", "HARVEST_PASS")))


def all_pass_golden_codes() -> list[str]:
    codes: list[str] = []
    for row in load_golden_rows():
        overall = str(
            ((row.get("expected_outputs") or {}).get("gate_results") or {}).get("overall", "")
        ).upper()
        if overall == "ALL_PASS":
            codes.append(row["sku_code"])
    return codes


def master_cluster_ids_for_row(row: dict) -> set[str]:
    cid = (row.get("use_case") or {}).get("cluster_id")
    return {cid} if cid else set()
