#!/usr/bin/env python3
"""
CIE v2.3.2 — Backfill cluster centroid vectors from intent_statement (local dev / ops).

Usage (from backend/python):
  python run_cluster_vector_init.py
  python run_cluster_vector_init.py --force --limit 100

Requires OPENAI_API_KEY (or LOCAL_LLM_MODE=true for mock vectors).
"""
import argparse
import logging
import os
import sys

_root = os.path.dirname(os.path.abspath(__file__))
if _root not in sys.path:
    sys.path.insert(0, _root)

logging.basicConfig(level=logging.INFO)

try:
    from dotenv import load_dotenv

    _repo_root = os.path.dirname(os.path.dirname(_root))
    _backend = os.path.dirname(_root)
    load_dotenv(os.path.join(_repo_root, ".env"))
    load_dotenv(os.path.join(_backend, ".env"))
except ImportError:
    pass

from src.vector.cluster_init import init_missing_cluster_vectors  # noqa: E402


def main() -> int:
    parser = argparse.ArgumentParser(description="Initialize missing cluster intent vectors")
    parser.add_argument("--limit", type=int, default=50, help="Max clusters to process")
    parser.add_argument("--force", action="store_true", help="Re-embed even when vector exists")
    args = parser.parse_args()
    os.environ.setdefault("CIE_AUTO_INIT_CLUSTER_VECTORS", "true")
    n = init_missing_cluster_vectors(limit=args.limit, force=args.force)
    logging.info("cluster_vector_init: embedded %d cluster(s)", n)
    return 0 if n >= 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
