# SOURCE: CIE_v231_Developer_Build_Pack.pdf §4.1 — golden SKU gate expectations (automated checks)
from __future__ import annotations

import json
import os
import sys
from pathlib import Path

import pytest

# Resolve package root (backend/python)
_PY_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if _PY_ROOT not in sys.path:
    sys.path.insert(0, _PY_ROOT)

from api.gates_validate import BusinessRules, run_all_gates, run_g4, run_g5  # noqa: E402
from api.main import build_validation_response  # noqa: E402
from api.schemas_validate import SkuValidateRequest  # noqa: E402


@pytest.fixture
def rules_cache(monkeypatch):
    """In-memory BusinessRules so tests do not require MySQL."""
    cache = {
        "gates.answer_block_min_chars": 250,
        "gates.answer_block_max_chars": 300,
        "gates.best_for_min_entries": 2,
        "gates.not_for_min_entries": 1,
        "gates.vector_similarity_min": 0.72,
        "gates.description_word_count_min": 50,
    }
    monkeypatch.setattr(BusinessRules, "_cache", cache)


def test_harvest_g3_gate_row_not_applicable(rules_cache):
    """SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.1 — Harvest G3 is N/A in API gate surface."""
    data = SkuValidateRequest(
        sku_id="CBL-RED-3C-2M",
        cluster_id="CLU-CBL-P-E27",
        tier="harvest",
        primary_intent="Specification",
        secondary_intents=[],
        title="Red Twisted Pendant Cable",
        description="",
        answer_block="",
        best_for=[],
        not_for=[],
        expert_authority="",
        action="save",
    )
    out = run_all_gates(data, {"CLU-CBL-P-E27"})
    resp = build_validation_response(
        data, "harvest", out["failures"], out["vector_result"], out["degraded"], audit_degraded=False
    )
    assert resp["gates"]["G3_secondary_intents"]["status"] == "not_applicable"


def test_harvest_cbl_red_3c_2m_no_gate_failures(rules_cache):
    """CBL-RED-3C-2M (Harvest): G1/G2/G6 path; G4/G5/G7 suspended — expect no blocking failures."""
    data = SkuValidateRequest(
        sku_id="CBL-RED-3C-2M",
        cluster_id="CLU-CBL-P-E27",
        tier="harvest",
        primary_intent="Specification",
        secondary_intents=[],
        title="Red Twisted Pendant Cable",
        description="",
        answer_block="",
        best_for=[],
        not_for=[],
        expert_authority="",
        action="save",
    )
    out = run_all_gates(data, {"CLU-CBL-P-E27"})
    assert out["failures"] == [], f"unexpected failures: {out['failures']}"


def test_harvest_cbl_red_3c_2m_validation_response_gate_matrix(rules_cache):
    """SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.1 Harvest row — FIX: TS-08 explicit gate statuses."""
    data = SkuValidateRequest(
        sku_id="CBL-RED-3C-2M",
        cluster_id="CLU-CBL-P-E27",
        tier="harvest",
        primary_intent="Specification",
        secondary_intents=[],
        title="Red Twisted Pendant Cable",
        description="",
        answer_block="",
        best_for=[],
        not_for=[],
        expert_authority="",
        action="save",
    )
    out = run_all_gates(data, {"CLU-CBL-P-E27"})
    resp = build_validation_response(
        data, "harvest", out["failures"], out["vector_result"], out["degraded"], audit_degraded=False
    )
    gates = resp["gates"]
    assert gates["G1_cluster_id"]["status"] == "pass"
    assert gates["G2_primary_intent"]["status"] == "pass"
    assert gates["G6_tier_tag"]["status"] == "pass"
    assert gates["G3_secondary_intents"]["status"] == "not_applicable"
    assert gates["G4_answer_block"]["status"] == "not_applicable"
    assert gates["G5_best_not_for"]["status"] == "not_applicable"
    assert gates["G7_expert_authority"]["status"] == "not_applicable"
    assert resp["publish_allowed"] is True


def test_kill_flr_arc_blk_175_any_validate_g61_blocked(rules_cache):
    """FLR-ARC-BLK-175 (Kill): ENF§2.1 G6.1 — any edit / validate attempt is blocked (CIE_G6_1_KILL_EDIT_BLOCKED)."""
    data = SkuValidateRequest(
        sku_id="FLR-ARC-BLK-175",
        cluster_id="CLU-FLR-ARC",
        tier="kill",
        primary_intent=None,
        secondary_intents=[],
        title="",
        description="",
        answer_block="",
        best_for=[],
        not_for=[],
        expert_authority="",
        action="save",
    )
    out = run_all_gates(data, {"CLU-FLR-ARC"})
    codes = [getattr(f, "error_code", None) for f in out["failures"]]
    assert "CIE_G6_1_KILL_EDIT_BLOCKED" in codes


def test_kill_flr_arc_validation_response_fixture_surface(rules_cache):
    """SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf Kill fixture — FIX: TS-09 publish + suspended gates + channel contract."""
    data = SkuValidateRequest(
        sku_id="FLR-ARC-BLK-175",
        cluster_id="CLU-FLR-ARC",
        tier="kill",
        primary_intent=None,
        secondary_intents=[],
        title="",
        description="",
        answer_block="",
        best_for=[],
        not_for=[],
        expert_authority="",
        action="save",
    )
    out = run_all_gates(data, {"CLU-FLR-ARC"})
    resp = build_validation_response(
        data, "kill", out["failures"], out["vector_result"], out["degraded"], audit_degraded=False
    )
    assert resp["publish_allowed"] is False
    assert resp["save_allowed"] is False
    assert resp["status"] == "fail"
    assert resp["gates"]["G6_1_tier_lock"]["status"] == "fail"
    assert resp["gates"]["G6_1_tier_lock"]["error_code"] == "CIE_G6_1_KILL_EDIT_BLOCKED"
    for key in ("G1_cluster_id", "G2_primary_intent", "G3_secondary_intents", "G4_answer_block", "G5_best_not_for", "G7_expert_authority"):
        assert resp["gates"][key]["status"] == "not_applicable"
    # SOURCE: database/seeds/golden_test_data.json Kill row — channel SKIP/readiness 0 (mirrors PHP ChannelGovernorService)
    golden_path = Path(__file__).resolve().parents[3] / "database" / "seeds" / "golden_test_data.json"
    rows = json.loads(golden_path.read_text(encoding="utf-8"))
    flr = next(r for r in rows if r.get("sku_code") == "FLR-ARC-BLK-175")
    ch_exp = flr["expected_outputs"]["channel_decisions"]
    for ch in ("google_sge", "amazon", "ai_assistants", "own_website"):
        assert ch_exp[ch]["decision"] == "SKIP"
        assert ch_exp[ch]["readiness"] == 0
    assert ch_exp["active_channels"] == 0


def test_kill_sku_validation_completeness(rules_cache):
    """SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf Kill fixture
    FIX: TS-09 — API vs UI: publish/save flags and golden channel expectations."""
    data = SkuValidateRequest(
        sku_id="FLR-ARC-BLK-175",
        cluster_id="CLU-FLR-ARC",
        tier="kill",
        primary_intent=None,
        secondary_intents=[],
        title="",
        description="",
        answer_block="",
        best_for=[],
        not_for=[],
        expert_authority="",
        action="save",
    )
    out = run_all_gates(data, {"CLU-FLR-ARC"})
    resp = build_validation_response(
        data, "kill", out["failures"], out["vector_result"], out["degraded"], audit_degraded=False
    )

    assert resp["publish_allowed"] is False
    assert resp["save_allowed"] is False
    assert "CIE_G6_1_KILL_EDIT_BLOCKED" in str(resp["gates"])

    # UI mapping (not API fields — WriterEdit.jsx):
    # submit_enabled ~= publish_allowed (False for Kill)
    # submit_visible: tier == kill hides primary submit (not returned on validate)
    # effort_hours_permitted: golden_test_data.json reference only; enforced by G6.1 / tier lock

    golden_path = Path(__file__).resolve().parents[3] / "database" / "seeds" / "golden_test_data.json"
    rows = json.loads(golden_path.read_text(encoding="utf-8"))
    flr = next(r for r in rows if r.get("sku_code") == "FLR-ARC-BLK-175")
    ch_exp = flr["expected_outputs"]["channel_decisions"]
    for ch in ("google_sge", "amazon", "ai_assistants", "own_website"):
        assert ch_exp[ch]["decision"] == "SKIP"
        assert ch_exp[ch]["readiness"] == 0
    assert ch_exp["active_channels"] == 0


def test_kill_with_content_fields_g61_blocked(rules_cache):
    """Kill tier with content mutation → CIE_G6_1_KILL_EDIT_BLOCKED (ENF§2.1 G6.1)."""
    data = SkuValidateRequest(
        sku_id="KILL-TEST",
        cluster_id="CLU-FLR-ARC",
        tier="kill",
        primary_intent=None,
        secondary_intents=[],
        title="",
        description="",
        answer_block="any content",
        best_for=[],
        not_for=[],
        expert_authority="",
        action="save",
    )
    out = run_all_gates(data, {"CLU-FLR-ARC"})
    codes = [getattr(f, "error_code", None) for f in out["failures"]]
    assert "CIE_G6_1_KILL_EDIT_BLOCKED" in codes


def test_hero_shd_gls_cne_20_g4_char_limit(rules_cache):
    """SHD-GLS-CNE-20: answer_block 242 chars < 250 → CIE_G4_CHAR_LIMIT."""
    # Golden fixture documents 242 chars < 250 — use exact length so test is stable
    answer = "x" * 242
    data = SkuValidateRequest(
        sku_id="SHD-GLS-CNE-20",
        cluster_id="CLU-SHD-GLS",
        tier="hero",
        primary_intent="Comparison",
        secondary_intents=["Problem-Solving", "Specification"],
        title="Opal Glass Cone Shade 20cm",
        description="x " * 50,
        answer_block=answer,
        best_for=["Kitchen pendant lighting", "Reading nooks"],
        not_for=["Outdoor use"],
        expert_authority="BS EN 60598-1 compliant.",
        action="save",
    )
    f = run_g4(data)
    assert f is not None
    assert f.error_code == "CIE_G4_CHAR_LIMIT"


def test_support_blb_led_b22_8w_g5_bestfor_count(rules_cache):
    """BLB-LED-B22-8W: empty not_for → CIE_G5_BESTFOR_COUNT."""
    data = SkuValidateRequest(
        sku_id="BLB-LED-B22-8W",
        cluster_id="CLU-BLB-LED",
        tier="support",
        primary_intent="Specification",
        secondary_intents=["Compatibility"],
        title="LED GLS Bulb B22 8W",
        description="x " * 50,
        answer_block="x" * 260,
        best_for=["B22 ceiling fittings", "Kitchen and workspace lighting"],
        not_for=[],
        expert_authority="CE and RoHS compliant per BS standards.",
        action="save",
    )
    fails = run_g5(data)
    assert any(getattr(x, "error_code", None) == "CIE_G5_BESTFOR_COUNT" for x in fails)
