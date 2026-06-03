#!/usr/bin/env python3
"""
GAP-ROUTES-01 — Ensure backend/php/routes/api.php paths are documented in OpenAPI.

Exits 0 when every runtime route appears in cie_v231_openapi.yaml (canonical contract).
SOURCE: docs/ARCHITECT_REVIEW_GAP_ROUTES_API.md
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ROUTES_FILE = ROOT / "backend" / "php" / "routes" / "api.php"
OPENAPI_FILE = ROOT / "cie_v231_openapi.yaml"

# Routes intentionally excluded from contract (Python-only or not implemented in PHP table)
EXCLUDE_PATHS = {
    ("POST", "/sku/embed"),  # legacy openapi engine path; PHP uses validate proxy
    ("POST", "/sku/similarity"),
}

# Normalize Laravel param names to OpenAPI style
PARAM_ALIASES = {
    "skuCode": "sku_code",
    "id": "id",
    "sku_id": "sku_id",
    "brief_id": "brief_id",
    "suggestion_id": "suggestion_id",
    "batch_date": "batch_date",
    "key": "key",
    "category": "category",
}


def parse_api_routes() -> list[tuple[str, str, str]]:
    """Return list of (scope, method, path) where scope is 'v1' or 'api'."""
    text = ROUTES_FILE.read_text(encoding="utf-8")
    scope = "api"
    prefix_stack: list[str] = []
    routes: list[tuple[str, str, str]] = []

    for line in text.splitlines():
        if "prefix('v1')" in line:
            scope = "v1"
            prefix_stack = []
        pm = re.search(r"prefix\(\s*['\"]([^'\"]+)['\"]\s*\)", line)
        if pm and "group(function" in line:
            seg = pm.group(1).strip("/")
            # v1 is the OpenAPI server base, not a path segment
            if seg != "v1":
                prefix_stack.append(seg)
        if re.match(r"\s*\}\);\s*$", line) and prefix_stack:
            prefix_stack.pop()

        m = re.search(
            r"Route::(get|post|put|delete|patch)\s*\(\s*['\"]([^'\"]+)['\"]",
            line,
            re.I,
        )
        if not m:
            continue
        method = m.group(1).upper()
        raw = m.group(2).strip()
        if not raw.startswith("/"):
            raw = "/" + raw
        for segment in prefix_stack:
            seg = "/" + segment.strip("/")
            if not raw.startswith(seg + "/") and raw != seg:
                raw = seg + raw
        path = re.sub(
            r"\{(\w+)\}",
            lambda m: "{" + PARAM_ALIASES.get(m.group(1), m.group(1)) + "}",
            raw,
        )
        routes.append((scope, method, path))

    return routes


def parse_openapi_paths(yaml_text: str) -> set[str]:
    """Extract path keys from OpenAPI paths section (simple line parser)."""
    paths: set[str] = set()
    in_paths = False
    for line in yaml_text.splitlines():
        if line.strip() == "paths:":
            in_paths = True
            continue
        if in_paths:
            if line.startswith("components:"):
                break
            m = re.match(r"^  (/[^\s:]+):\s*$", line)
            if m:
                paths.add(m.group(1))
    return paths


def contract_path(scope: str, path: str) -> str:
    """Path as documented under /api/v1 server (v1) or /api server (api scope)."""
    return path


def main() -> int:
    if not ROUTES_FILE.is_file():
        print(f"ERROR: missing {ROUTES_FILE}", file=sys.stderr)
        return 2
    if not OPENAPI_FILE.is_file():
        print(f"ERROR: missing {OPENAPI_FILE}", file=sys.stderr)
        return 2

    openapi_paths = parse_openapi_paths(OPENAPI_FILE.read_text(encoding="utf-8"))
    routes = parse_api_routes()
    missing: list[str] = []

    for scope, method, path in routes:
        key = (method, path)
        if key in EXCLUDE_PATHS:
            continue
        if path not in openapi_paths:
            missing.append(f"  [{scope}] {method} {path}")

    if missing:
        print("FAIL: routes in api.php missing from cie_v231_openapi.yaml paths:")
        for line in sorted(missing):
            print(line)
        print(f"\nDocumented paths: {len(openapi_paths)} | Runtime routes: {len(routes)}")
        return 1

    print(f"OK: all {len(routes)} PHP routes documented in {OPENAPI_FILE.name}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
