#!/usr/bin/env python3
"""
P3 — Static E2E for N8N + Shopify deploy path (no live Shopify/N8N required).

Validates: workflow files, PHP webhook paths, payload schema, HMAC contract, import script.
Live staging proof remains: APP_URL + TEST_WRITER_TOKEN + scripts/e2e_publish_flow_check.py
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
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


def fail(msg: str) -> None:
    print(f"FAIL: {msg}", file=sys.stderr)
    raise SystemExit(1)


def ok(msg: str) -> None:
    print(f"OK: {msg}")


def main() -> int:
    errors: list[str] = []

    shopify_wf = ROOT / "n8n/workflows/shopify_deploy.json"
    gmc_wf = ROOT / "n8n/workflows/gmc_deploy.json"
    php_svc = ROOT / "backend/php/src/Services/ChannelDeployService.php"
    import_sh = ROOT / "scripts/import_n8n_workflows.sh"
    sample = ROOT / "tests/fixtures/n8n/hero_deploy_payload_sample.json"

    for path in (shopify_wf, gmc_wf, php_svc, import_sh, sample):
        if not path.is_file():
            errors.append(f"missing file: {path.relative_to(ROOT)}")

    if errors:
        for e in errors:
            print(f"FAIL: {e}", file=sys.stderr)
        return 1

    shopify_text = shopify_wf.read_text(encoding="utf-8")
    if '"path": "shopify-deploy"' not in shopify_text:
        errors.append("shopify_deploy.json webhook path must be shopify-deploy")
    if "SHOPIFY_ADMIN_ACCESS_TOKEN" not in shopify_text:
        errors.append("shopify_deploy.json must use SHOPIFY_ADMIN_ACCESS_TOKEN env")
    if "graphql.json" not in shopify_text.lower():
        errors.append("shopify_deploy.json must call Shopify Admin GraphQL")

    gmc_text = gmc_wf.read_text(encoding="utf-8")
    if '"path": "gmc-deploy"' not in gmc_text and '"path":"gmc-deploy"' not in gmc_text:
        errors.append("gmc_deploy.json webhook path must be gmc-deploy")

    php_text = php_svc.read_text(encoding="utf-8")
    if "N8N_SHOPIFY_WEBHOOK_PATH" not in php_text or "shopify-deploy" not in php_text:
        errors.append("ChannelDeployService must default N8N_SHOPIFY_WEBHOOK_PATH to shopify-deploy")
    if "X-N8N-Signature" not in php_text or "hash_hmac('sha256'" not in php_text:
        errors.append("ChannelDeployService must HMAC-sign N8N payloads")
    for key in REQUIRED_KEYS:
        if f"'{key}'" not in php_text:
            errors.append(f"buildDeployPayload missing key: {key}")

    import_text = import_sh.read_text(encoding="utf-8")
    if "shopify_deploy.json" not in import_text or "gmc_deploy.json" not in import_text:
        errors.append("import_n8n_workflows.sh must import shopify_deploy + gmc_deploy")

    routes = (ROOT / "backend/php/routes/api.php").read_text(encoding="utf-8")
    if "channel-deployed" not in routes or "channel-failed" not in routes:
        errors.append("api.php must define channel-deployed and channel-failed callbacks")

    payload = json.loads(sample.read_text(encoding="utf-8"))
    for key in REQUIRED_KEYS:
        if key not in payload:
            errors.append(f"fixture missing key: {key}")
    for spot in ("title", "answer_block", "meta_title"):
        if not str(payload.get(spot, "")).strip():
            errors.append(f"fixture empty: {spot}")

    ok("HMAC sha256 + X-N8N-Signature contract in ChannelDeployService")

    if errors:
        for e in errors:
            print(f"FAIL: {e}", file=sys.stderr)
        return 1

    ok("N8N/Shopify static E2E — workflows, PHP deploy service, payload fixture, callbacks")
    print("Live proof: set APP_URL + TEST_WRITER_TOKEN and run scripts/e2e_publish_flow_check.py")
    return 0


if __name__ == "__main__":
    sys.exit(main())
