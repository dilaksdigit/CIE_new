#!/usr/bin/env python3
# SOURCE: GAP-P7-3 / STAGING_E2E — read-only N8N outbound payload checklist (no business logic)
"""
Verify ChannelDeployService outbound shape for staging sign-off.

Usage:
  python scripts/verify_n8n_deploy_payload.py
  python scripts/verify_n8n_deploy_payload.py --sku-code CBL-BLK-3C-1M

Optional: pass --json-file path/to/captured_n8n_webhook.json to validate a captured payload.
"""
from __future__ import annotations

import argparse
import json
import sys

REQUIRED_KEYS = (
    "sku_id",
    "shopify_product_id",
    "title",
    "meta_title",
    "meta_description",
    "answer_block",
    "best_for",
    "not_for",
    "faq",
    "json_ld",
    "alt_text",
)

HERO_SPOTCHECK = ("title", "answer_block", "meta_title")


def validate_payload(payload: dict) -> list[str]:
    errors: list[str] = []
    for key in REQUIRED_KEYS:
        if key not in payload:
            errors.append(f"missing key: {key}")
    for key in HERO_SPOTCHECK:
        val = payload.get(key)
        if val is None or (isinstance(val, str) and not val.strip()):
            errors.append(f"empty Hero field: {key}")
    if "faq" in payload and not isinstance(payload["faq"], list):
        errors.append("faq must be an array")
    return errors


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--sku-code", default="CBL-BLK-3C-1M", help="Hero SKU for operator checklist")
    parser.add_argument("--json-file", help="Captured N8N webhook JSON from execution log")
    args = parser.parse_args()

    print("CIE N8N Deploy Payload Verification (read-only)")
    print("=" * 55)
    print(f"Hero SKU code (operator): {args.sku_code}")
    print(f"Required keys ({len(REQUIRED_KEYS)}): {', '.join(REQUIRED_KEYS)}")
    print(f"Hero spot-check (non-empty): {', '.join(HERO_SPOTCHECK)}")
    print()
    print("Source: ChannelDeployService::buildDeployPayload (PHP)")
    print("Manual: After publish, compare N8N execution input to Shopify metafields.")
    print()

    if not args.json_file:
        print("No --json-file provided — schema checklist only.")
        print("Export webhook body from N8N execution and re-run:")
        print("  python scripts/verify_n8n_deploy_payload.py --json-file payload.json")
        return 0

    with open(args.json_file, encoding="utf-8") as f:
        data = json.load(f)
    payload = data.get("body", data) if isinstance(data, dict) else data
    if not isinstance(payload, dict):
        print("Invalid JSON: expected object", file=sys.stderr)
        return 2

    errors = validate_payload(payload)
    if errors:
        for e in errors:
            print(f"[FAIL] {e}")
        return 1

    print("[PASS] All required keys present; Hero spot-check fields non-empty")
    for key in HERO_SPOTCHECK:
        preview = str(payload.get(key, ""))[:80]
        print(f"  {key}: {preview!r}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
