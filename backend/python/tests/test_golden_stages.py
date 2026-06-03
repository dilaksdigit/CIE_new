# SOURCE: CIE_Doc4b — Stages 3–6 contract snapshots (maturity, channels, titles)
from __future__ import annotations

import json
from pathlib import Path

import pytest

from tests.golden_fixtures import golden_by_code, load_golden_rows

GOLDEN_PATH = Path(__file__).resolve().parents[3] / "database" / "seeds" / "golden_test_data.json"


@pytest.mark.parametrize("sku_code", [r["sku_code"] for r in load_golden_rows()])
def test_golden_maturity_total_matches_pack(sku_code):
    row = golden_by_code(sku_code)
    mat = (row.get("expected_outputs") or {}).get("maturity") or {}
    if mat.get("total") is None:
        pytest.skip(f"{sku_code} maturity excluded (kill)")
    parts = [
        mat.get("core_fields", 0),
        mat.get("authority", 0),
        mat.get("channel_readiness", 0),
        mat.get("ai_visibility", 0),
    ]
    assert sum(parts) == mat["total"], f"{sku_code} maturity components must sum to total"


@pytest.mark.parametrize("sku_code", [r["sku_code"] for r in load_golden_rows()])
def test_golden_active_channels_count(sku_code):
    row = golden_by_code(sku_code)
    ch = (row.get("expected_outputs") or {}).get("channel_decisions") or {}
    if "active_channels" not in ch:
        pytest.skip(f"{sku_code} no channel block")
    expected = int(ch["active_channels"])
    compete = sum(
        1
        for key, val in ch.items()
        if key != "active_channels" and isinstance(val, dict) and val.get("decision") == "COMPETE"
    )
    assert compete == expected, f"{sku_code} active_channels mismatch (pack channel model)"


@pytest.mark.parametrize(
    "sku_code",
    ["CBL-BLK-3C-1M", "CBL-GLD-3C-1M", "SHD-TPE-DRM-35", "PND-SET-BRS-3L"],
)
def test_golden_hero_shopify_compete(sku_code):
    ch = golden_by_code(sku_code)["expected_outputs"]["channel_decisions"]
    assert ch["own_website"]["decision"] == "COMPETE"
    assert ch["own_website"]["readiness"] >= 85


def test_golden_kill_zero_active_channels():
    ch = golden_by_code("FLR-ARC-BLK-175")["expected_outputs"]["channel_decisions"]
    assert ch["active_channels"] == 0
    for key in ("google_sge", "amazon", "ai_assistants", "own_website"):
        assert ch[key]["decision"] == "SKIP"


def test_golden_title_validation_flags_present_for_hero():
    row = golden_by_code("CBL-BLK-3C-1M")
    tv = row["expected_outputs"].get("title_validation") or {}
    assert tv.get("starts_with_intent") is True
    assert tv.get("shopify_length_valid") is True


def test_doc4b_pack_has_ten_skus():
    rows = load_golden_rows()
    assert len(rows) == 10


def test_amazon_in_pack_is_not_production_channel():
    """DECISION-001: production deploy channels are shopify+gmc; pack may still list amazon for audit."""
    rows = load_golden_rows()
    for row in rows:
        ch = (row.get("expected_outputs") or {}).get("channel_decisions") or {}
        if "amazon" in ch:
            assert ch["amazon"]["decision"] in ("SKIP", "COMPETE")
