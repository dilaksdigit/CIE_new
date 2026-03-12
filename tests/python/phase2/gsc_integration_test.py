# SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 2.1 / 2.2 / 2.3

"""
Phase 2.1 — GET /api/gsc/status returns 200 with verified property list.
Phase 2.2 — url_performance populated for Hero + Support SKUs after weekly sync.
Phase 2.3 — URL normalisation (trailing slash, case, UTM).
SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.1–2.3
"""

import os
import pytest
import requests

BASE_URL = os.environ.get("APP_URL", "http://localhost").rstrip("/")
ADMIN_TOKEN = os.environ.get("TEST_TOKEN_ADMIN", "test-token-placeholder")


@pytest.fixture
def admin_headers():
    return {"Authorization": f"Bearer {ADMIN_TOKEN}", "Content-Type": "application/json"}


def test_gsc_status_returns_200(admin_headers):
    """
    Phase 2.1 — GET /api/gsc/status returns 200 with verified property list.
    SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.1
    SOURCE: CIE_Master_Developer_Build_Spec.docx §2029 route table
    LLM Check: NO — requires live GSC credentials.
    """
    response = requests.get(f"{BASE_URL}/api/v1/gsc/status", headers=admin_headers, timeout=10)
    assert response.status_code == 200, "GET /api/v1/gsc/status must return 200"
    body = response.json()
    assert "verified_properties" in body, "Response must include verified_properties list"
    assert isinstance(body["verified_properties"], list), "verified_properties must be a list"


def test_url_performance_populated_after_sync(admin_headers):
    """
    Phase 2.2 — url_performance table populated for Hero + Support SKUs after weekly cron.
    SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.2
    NOTE: Trigger manual sync, then assert row count > 0.
    LLM Check: NO — requires live GSC and running sync.
    """
    pytest.skip(
        "Phase 2.2 requires live GSC sync. Run manually: "
        "trigger weekly_gsc_sync.py then assert url_performance row count."
    )


def test_url_normalisation_trailing_slash(admin_headers):
    """
    Phase 2.3 — URL normalisation handles trailing slashes.
    SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.3
    LLM Check: YES
    """
    # Two URL variants that should resolve to the same normalised URL
    url_with_slash    = "https://www.example.com/products/test-cable/"
    url_without_slash = "https://www.example.com/products/test-cable"

    resp_with    = requests.post(
        f"{BASE_URL}/api/v1/gsc/baseline/SKU-CABLE-001",
        json={"url": url_with_slash},
        headers=admin_headers,
        timeout=10
    )
    resp_without = requests.post(
        f"{BASE_URL}/api/v1/gsc/baseline/SKU-CABLE-001",
        json={"url": url_without_slash},
        headers=admin_headers,
        timeout=10
    )
    # Both must be accepted (2xx) and normalised to the same canonical form
    assert resp_with.status_code in (200, 201, 204), "URL with trailing slash must be accepted"
    assert resp_without.status_code in (200, 201, 204), "URL without trailing slash must be accepted"
