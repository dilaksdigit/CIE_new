# SOURCE: CIE_v231_Developer_Build_Pack.pdf §4.1 — golden SKU gate expectations (automated checks)
from __future__ import annotations

import os
import sys

import pytest

# Resolve package root (backend/python)
_PY_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if _PY_ROOT not in sys.path:
    sys.path.insert(0, _PY_ROOT)

from api.gates_validate import BusinessRules, run_all_gates, run_g4, run_g5  # noqa: E402
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


def test_kill_flr_arc_blk_175_g61_kill_edit_blocked(rules_cache):
    """FLR-ARC-BLK-175 (Kill): G6.1 must block with CIE_G6_1_KILL_EDIT_BLOCKED."""
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
