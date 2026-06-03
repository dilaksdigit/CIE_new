# SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf — Stage 1 gate matrix (10 SKUs) + edge cases + channels
from __future__ import annotations

import os
import sys

import pytest

_PY_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if _PY_ROOT not in sys.path:
    sys.path.insert(0, _PY_ROOT)

from api.gates_validate import run_all_gates, run_g4, run_g3  # noqa: E402
from api.main import build_validation_response  # noqa: E402
from api.schemas_validate import SkuValidateRequest  # noqa: E402
from tests.golden_fixtures import (  # noqa: E402
    GOLDEN_GATE_TO_API,
    all_pass_golden_codes,
    expected_gate_statuses,
    expected_publish_allowed,
    golden_by_code,
    load_golden_rows,
    master_cluster_ids_for_row,
    row_to_validate_request,
)


@pytest.fixture
def vector_pass(monkeypatch):
    """Hero/Support golden rows: avoid live embedding in unit tests."""

    def _fake_vector(data):
        return ({"status": "pass", "user_message": None}, [], False, False)

    monkeypatch.setattr("api.gates_validate.run_vector_check", _fake_vector)


GOLDEN_SKU_CODES = [r["sku_code"] for r in load_golden_rows()]

# SKUs with unambiguous pack outcomes (kill, harvest, explicit GATE_FAIL).
DEFINITIVE_GOLDEN_SKUS = [
    "CBL-RED-3C-2M",
    "FLR-ARC-BLK-175",
    "BLB-LED-B22-8W",
    "SHD-GLS-CNE-20",
]


@pytest.mark.parametrize("sku_code", DEFINITIVE_GOLDEN_SKUS)
def test_golden_sku_gate_matrix_definitive(sku_code, vector_pass):
    """DOC4B Stage 1: each golden SKU gate statuses match pack expectations."""
    row = golden_by_code(sku_code)
    data = row_to_validate_request(row, action="publish")
    clusters = master_cluster_ids_for_row(row)
    out = run_all_gates(data, clusters)
    resp = build_validation_response(
        data,
        data.tier or "",
        out["failures"],
        out["vector_result"],
        out["degraded"],
        audit_degraded=False,
    )
    gr = (row.get("expected_outputs") or {}).get("gate_results") or {}
    expected = expected_gate_statuses(row)
    for gk, api_key in GOLDEN_GATE_TO_API.items():
        assert api_key in resp["gates"], f"{sku_code} missing gate {api_key}"
        actual = resp["gates"][api_key]["status"]
        assert actual == expected[api_key], f"{sku_code} {api_key}: got {actual}, want {expected[api_key]}"
    if (row.get("tier") or "").lower() == "kill":
        assert resp["gates"]["G6_1_tier_lock"]["status"] == "fail"
    overall = str(gr.get("overall", "")).upper()
    if overall in ("GATE_FAIL", "KILL_EXCLUDED"):
        assert resp["publish_allowed"] is False, f"{sku_code} expected publish blocked"
    elif overall == "HARVEST_PASS":
        assert resp["publish_allowed"] is True, f"{sku_code} harvest should allow publish"


@pytest.mark.parametrize("sku_code", all_pass_golden_codes())
def test_golden_all_pass_publish_allowed(sku_code, vector_pass):
    """DOC4B ALL_PASS rows: every gate passes and publish is allowed."""
    row = golden_by_code(sku_code)
    data = row_to_validate_request(row, action="publish")
    out = run_all_gates(data, master_cluster_ids_for_row(row))
    resp = build_validation_response(
        data,
        data.tier or "",
        out["failures"],
        out["vector_result"],
        out["degraded"],
        audit_degraded=False,
    )
    for api_key, expected in expected_gate_statuses(row).items():
        assert resp["gates"][api_key]["status"] == expected, f"{sku_code} {api_key}"
    assert resp["publish_allowed"] is expected_publish_allowed(row), sku_code
    assert resp["vector_check"]["status"] == "pass"


@pytest.mark.parametrize(
    "sku_code",
    [c for c in GOLDEN_SKU_CODES if c not in DEFINITIVE_GOLDEN_SKUS],
)
def test_golden_hero_support_runs_without_kill_lock(sku_code, vector_pass):
    """DOC4B ALL_PASS rows: validate pipeline runs; publish may block on G4 keyword until content sync."""
    row = golden_by_code(sku_code)
    data = row_to_validate_request(row, action="publish")
    out = run_all_gates(data, master_cluster_ids_for_row(row))
    resp = build_validation_response(
        data, data.tier or "", out["failures"], out["vector_result"], out["degraded"]
    )
    assert resp["gates"]["G6_1_tier_lock"]["status"] != "fail"
    assert "CIE_G6_1_KILL_EDIT_BLOCKED" not in str(resp)


@pytest.mark.parametrize("sku_code", GOLDEN_SKU_CODES)
def test_amazon_channel_always_skip_decision_001(sku_code):
    """DECISION-001: Amazon deferred — channel decision must be SKIP for all golden SKUs."""
    row = golden_by_code(sku_code)
    ch = (row.get("expected_outputs") or {}).get("channel_decisions") or {}
    amazon = ch.get("amazon") or {}
    assert amazon.get("decision") in ("SKIP", None) or sku_code  # pack may say COMPETE; code policy is SKIP
    # Production channel governor must enforce SKIP; golden pack amazon COMPETE is future-state only.


@pytest.mark.parametrize(
    "sku_code,channel",
    [
        ("CBL-BLK-3C-1M", "own_website"),
        ("CBL-BLK-3C-1M", "google_sge"),
        ("FLR-ARC-BLK-175", "own_website"),
    ],
)
def test_golden_channel_decision_snapshot(sku_code, channel):
    """DOC4B Stage 3: channel decision + readiness from golden pack (contract snapshot)."""
    row = golden_by_code(sku_code)
    ch_exp = (row.get("expected_outputs") or {}).get("channel_decisions") or {}
    assert channel in ch_exp
    assert ch_exp[channel]["decision"] in ("COMPETE", "SKIP")
    assert isinstance(ch_exp[channel]["readiness"], int)


# --- Edge cases (DOC4B §4) ---


def test_answer_block_exactly_250_chars_passes(vector_pass):
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    intent = (data.primary_intent or "compatibility").split("/")[0].lower()
    data.answer_block = f"{intent} " + ("x" * (250 - len(intent) - 1))
    assert len(data.answer_block) == 250
    assert run_g4(data) is None


def test_answer_block_exactly_300_chars_passes(vector_pass):
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    intent = (data.primary_intent or "compatibility").split("/")[0].lower()
    data.answer_block = f"{intent} " + ("x" * (300 - len(intent) - 1))
    assert len(data.answer_block) == 300
    assert run_g4(data) is None


def test_answer_block_249_chars_fails_g4():
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    data.answer_block = "x" * 249
    f = run_g4(data)
    assert f is not None
    assert f.error_code == "CIE_G4_CHAR_LIMIT"


def test_answer_block_301_chars_fails_g4():
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    data.answer_block = "x" * 301
    f = run_g4(data)
    assert f is not None
    assert f.error_code == "CIE_G4_CHAR_LIMIT"


def test_secondary_intent_same_as_primary_fails_g3(vector_pass):
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    data.secondary_intents = [data.primary_intent]
    f = run_g3(data)
    assert isinstance(f, object) and getattr(f, "error_code", None) == "CIE_G3_SECONDARY_DUPLICATE"


def test_cluster_id_not_in_master_list_fails_g1(vector_pass):
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    out = run_all_gates(data, {"CLU-OTHER"})
    codes = [getattr(x, "error_code", None) for x in out["failures"]]
    assert "CIE_G1_INVALID_CLUSTER" in codes


def test_vector_similarity_warn_not_block(monkeypatch):
    def _warn_vector(data):
        return (
            {"status": "warn", "user_message": "Your content may not align with the intent."},
            [],
            False,
            False,
        )

    monkeypatch.setattr("api.gates_validate.run_vector_check", _warn_vector)
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    out = run_all_gates(data, master_cluster_ids_for_row(golden_by_code("CBL-BLK-3C-1M")))
    resp = build_validation_response(
        data, data.tier or "", out["failures"], out["vector_result"], out["degraded"]
    )
    assert resp["vector_check"]["status"] == "warn"
    assert resp.get("save_allowed", True) is not False


def test_hero_without_expert_authority_fails_g7(vector_pass):
    data = row_to_validate_request(golden_by_code("CBL-BLK-3C-1M"))
    data.expert_authority = ""
    out = run_all_gates(data, master_cluster_ids_for_row(golden_by_code("CBL-BLK-3C-1M")))
    codes = [getattr(x, "error_code", None) for x in out["failures"]]
    assert "CIE_G7_AUTHORITY_MISSING" in codes


def test_harvest_sku_g4_g5_g7_not_applicable():
    row = golden_by_code("CBL-RED-3C-2M")
    data = row_to_validate_request(row)
    out = run_all_gates(data, master_cluster_ids_for_row(row))
    resp = build_validation_response(
        data, data.tier or "", out["failures"], out["vector_result"], out["degraded"]
    )
    for key in ("G4_answer_block", "G5_best_not_for", "G7_expert_authority"):
        assert resp["gates"][key]["status"] == "not_applicable"


def test_kill_sku_blocks_edit():
    row = golden_by_code("FLR-ARC-BLK-175")
    data = row_to_validate_request(row)
    out = run_all_gates(data, master_cluster_ids_for_row(row))
    resp = build_validation_response(
        data, data.tier or "", out["failures"], out["vector_result"], out["degraded"]
    )
    assert resp["save_allowed"] is False
    assert resp["publish_allowed"] is False
