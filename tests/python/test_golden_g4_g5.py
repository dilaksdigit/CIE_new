# SOURCE: GOLDEN§3.1 — Golden gate tests for G4 and G5 failure cases
"""
SHD-GLS-CNE-20: answer_block 242 chars → G4 FAIL with CIE_G4_CHAR_LIMIT
BLB-LED-B22-8W: not_for empty → G5 FAIL with CIE_G5_BESTFOR_COUNT
"""
import os
import sys

# Allow importing from backend/python
_backend = os.path.join(os.path.dirname(__file__), "..", "..", "backend", "python")
if _backend not in sys.path:
    sys.path.insert(0, _backend)

import pytest

# Mock BusinessRules before importing gates_validate
class MockBusinessRules:
    _data = {
        "gates.answer_block_min_chars": 250,
        "gates.answer_block_max_chars": 300,
    }
    @classmethod
    def get(cls, key, default=None):
        return cls._data.get(key, default)


@pytest.fixture(autouse=True)
def mock_business_rules(monkeypatch):
    import api.gates_validate as gv
    monkeypatch.setattr(gv, "BusinessRules", MockBusinessRules)


def test_golden_shd_gls_cne_20_g4_fail():
    """SOURCE: GOLDEN§3.1 — SHD-GLS-CNE-20: answer_block 242 chars must fail G4 with CIE_G4_CHAR_LIMIT."""
    from api.gates_validate import run_g4
    from api.schemas_validate import SkuValidateRequest

    payload = SkuValidateRequest(
        sku_id="SHD-GLS-CNE-20",
        cluster_id="test-cluster",
        tier="hero",
        primary_intent="Specification",
        answer_block="A" * 242,
        action="publish",
    )
    result = run_g4(payload)
    assert result is not None, "G4 must fail for 242-char answer block"
    assert result.error_code == "CIE_G4_CHAR_LIMIT", f"Expected CIE_G4_CHAR_LIMIT, got {result.error_code}"


def test_golden_blb_led_b22_8w_g5_fail():
    """SOURCE: GOLDEN§3.1 — BLB-LED-B22-8W: empty not_for must fail G5 with CIE_G5_BESTFOR_COUNT."""
    from api.gates_validate import run_g5
    from api.schemas_validate import SkuValidateRequest

    payload = SkuValidateRequest(
        sku_id="BLB-LED-B22-8W",
        cluster_id="test-cluster",
        tier="support",
        primary_intent="Specification",
        best_for=["Standard B22 pendant", "Bedside lamp"],
        not_for=[],
        action="publish",
    )
    failures = run_g5(payload)
    assert len(failures) > 0, "G5 must fail when not_for is empty"
    not_for_failure = next((f for f in failures if "not_for" in (f.detail or "").lower() or f.error_code == "CIE_G5_BESTFOR_COUNT"), failures[0])
    assert not_for_failure.error_code == "CIE_G5_BESTFOR_COUNT", f"Expected CIE_G5_BESTFOR_COUNT, got {not_for_failure.error_code}"
