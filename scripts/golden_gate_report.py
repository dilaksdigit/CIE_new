#!/usr/bin/env python3
"""Run all gates against golden_test_data.json and print a results table."""
from __future__ import annotations

import os
import sys

_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
_PY = os.path.join(_ROOT, "backend", "python")
if _PY not in sys.path:
    sys.path.insert(0, _PY)

from api.gates_validate import BusinessRules, run_all_gates  # noqa: E402
from api.main import build_validation_response  # noqa: E402
from tests.golden_fixtures import (  # noqa: E402
    GOLDEN_GATE_TO_API,
    expected_gate_statuses,
    expected_publish_allowed,
    load_golden_rows,
    master_cluster_ids_for_row,
    row_to_validate_request,
)

# Match conftest BusinessRules cache
_vec_thr = 7 / 10 + 2 / 100
BusinessRules._cache = {
    "gates.answer_block_min_chars": 250,
    "gates.answer_block_max_chars": 300,
    "gates.best_for_min_entries": 2,
    "gates.not_for_min_entries": 1,
    "gates.vector_similarity_min": _vec_thr,
    "gates.description_word_count_min": 50,
    "gates.hero_max_secondary": 3,
    "gates.support_max_secondary": 2,
    "gates.harvest_max_secondary": 1,
}


def _fake_vector(data):
    return ({"status": "pass", "user_message": None}, [], False, False)


import api.gates_validate as gv  # noqa: E402

gv.run_vector_check = _fake_vector
gv.log_audit_event = lambda *a, **k: True

GATE_COLS = ["G1", "G2", "G3", "G4", "G5", "G6", "G7", "G6.1", "VEC"]
STATUS_SYMBOL = {
    "pass": "PASS",
    "fail": "FAIL",
    "not_applicable": "N/A",
    "warn": "WARN",
}


def sym(status: str) -> str:
    return STATUS_SYMBOL.get(status, status.upper())


def main() -> int:
    rows_out: list[dict] = []
    mismatches = 0

    for row in load_golden_rows():
        code = row["sku_code"]
        tier = (row.get("tier") or "").lower()
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
        expected = expected_gate_statuses(row)
        exp_pub = expected_publish_allowed(row)

        gate_vals = {
            "G1": sym(resp["gates"]["G1_cluster_id"]["status"]),
            "G2": sym(resp["gates"]["G2_primary_intent"]["status"]),
            "G3": sym(resp["gates"]["G3_secondary_intents"]["status"]),
            "G4": sym(resp["gates"]["G4_answer_block"]["status"]),
            "G5": sym(resp["gates"]["G5_best_not_for"]["status"]),
            "G6": sym(resp["gates"]["G6_tier_tag"]["status"]),
            "G7": sym(resp["gates"]["G7_expert_authority"]["status"]),
            "G6.1": sym(resp["gates"]["G6_1_tier_lock"]["status"]),
            "VEC": sym(resp["vector_check"]["status"]),
        }

        pack_overall = (
            (row.get("expected_outputs") or {}).get("gate_results") or {}
        ).get("overall", "")

        match_ok = True
        for gk, api_key in GOLDEN_GATE_TO_API.items():
            exp = expected[api_key]
            act = resp["gates"][api_key]["status"]
            if act != exp:
                match_ok = False
                mismatches += 1
        if resp["publish_allowed"] != exp_pub:
            match_ok = False
            mismatches += 1

        rows_out.append(
            {
                "sku": code,
                "tier": tier,
                "gates": gate_vals,
                "publish": "YES" if resp["publish_allowed"] else "NO",
                "pack": pack_overall,
                "match": "OK" if match_ok else "MISMATCH",
            }
        )

    # Print markdown table
    hdr = "| SKU | Tier | G1 | G2 | G3 | G4 | G5 | G6 | G7 | G6.1 | VEC | Publish | Pack Expected | vs Pack |"
    sep = "|-----|------|----|----|----|----|----|----|----|------|-----|---------|---------------|---------|"
    print("GOLDEN GATE VALIDATION REPORT")
    print("SOURCE: database/seeds/golden_test_data.json")
    print("Engine: backend/python/api/gates_validate.py (vector mocked pass)")
    print()
    print(hdr)
    print(sep)
    for r in rows_out:
        g = r["gates"]
        print(
            f"| {r['sku']} | {r['tier']} | {g['G1']} | {g['G2']} | {g['G3']} | {g['G4']} | "
            f"{g['G5']} | {g['G6']} | {g['G7']} | {g['G6.1']} | {g['VEC']} | "
            f"{r['publish']} | {r['pack']} | {r['match']} |"
        )

    print()
    print(f"SKUs: {len(rows_out)} | Pack mismatches: {mismatches}")
    return 0 if mismatches == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
