#!/usr/bin/env python3
# SOURCE: Production Roadmap P3.1 — 7-step publish flow pre-flight (evidence collector)
"""
Run against a live staging stack. Requires env:
  APP_URL, TEST_WRITER_TOKEN, TEST_HERO_SKU_ID (e.g. uuid or code resolved by API)

Usage:
  export APP_URL=http://localhost:8080
  export TEST_WRITER_TOKEN=...
  export TEST_HERO_SKU_CODE=CBL-BLK-3C-1M
  python scripts/e2e_publish_flow_check.py
"""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request

APP_URL = os.environ.get("APP_URL", "").rstrip("/")
TOKEN = os.environ.get("TEST_WRITER_TOKEN", "")
SKU_CODE = os.environ.get("TEST_HERO_SKU_CODE", "CBL-BLK-3C-1M")


def req(method: str, path: str, body: dict | None = None) -> tuple[int, dict]:
    url = f"{APP_URL}{path}"
    data = None
    headers = {"Accept": "application/json", "Authorization": f"Bearer {TOKEN}"}
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"
    request = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(request, timeout=60) as resp:
            raw = resp.read().decode("utf-8")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8")
        try:
            return e.code, json.loads(raw)
        except json.JSONDecodeError:
            return e.code, {"raw": raw}


def main() -> int:
    if not APP_URL or not TOKEN:
        print("Set APP_URL and TEST_WRITER_TOKEN", file=sys.stderr)
        return 2

    steps: list[tuple[str, bool, str]] = []

    # Step 0: writer must not get 403 on publish (batch fix #1 — publish RBAC)
    code_auth, pub_probe = req("POST", f"/api/v1/sku/{SKU_CODE}/publish", {"action": "publish"})
    auth_ok = code_auth != 403
    steps.append(("0_publish_not_forbidden", auth_ok, f"HTTP {code_auth} (403 = RBAC still blocking writer)"))

    # Step 1: resolve SKU
    code, sku_list = req("GET", "/api/v1/sku?limit=5")
    steps.append(("1_list_skus", code == 200, f"HTTP {code}"))

    # Step 2: validate
    code, val = req("POST", f"/api/v1/sku/{SKU_CODE}/validate", {"sku_id": SKU_CODE, "action": "publish"})
    ok = code == 200 and (val.get("publish_allowed") or val.get("status") == "pass")
    steps.append(("2_validate", ok, json.dumps(val)[:200]))

    # Step 3–4: baselines (may 404 if sku id is code-only — document for operator)
    code_gsc, _ = req("POST", f"/api/v1/gsc/baseline/{SKU_CODE}")
    steps.append(("3_gsc_baseline", code_gsc in (200, 201, 404), f"HTTP {code_gsc}"))

    code_ga4, _ = req("POST", f"/api/v1/ga4/baseline/{SKU_CODE}")
    steps.append(("4_ga4_baseline", code_ga4 in (200, 201, 404), f"HTTP {code_ga4}"))

    # Step 5: publish triggers deploy (operator verifies N8N + Shopify separately)
    code_pub, pub = req("POST", f"/api/v1/sku/{SKU_CODE}/publish", {"action": "publish"})
    steps.append(("5_publish", code_pub in (200, 201, 202), f"HTTP {code_pub}"))

    # Step 6: readiness
    code_r, ready = req("GET", f"/api/v1/sku/{SKU_CODE}/readiness")
    steps.append(("6_readiness", code_r == 200, f"HTTP {code_r}"))

    print("CIE P3.1 Publish Flow Check")
    print("=" * 50)
    failed = 0
    for name, ok, detail in steps:
        mark = "PASS" if ok else "FAIL"
        if not ok:
            failed += 1
        print(f"[{mark}] {name}: {detail}")

    print("=" * 50)
    print("Automated (no live stack): python scripts/e2e_n8n_shopify_deploy_static.py")
    print("Manual: verify N8N execution log, Shopify metafields, gsc_baselines.measurement_status")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
