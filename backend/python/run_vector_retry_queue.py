#!/usr/bin/env python3
"""
CIE v2.3.2 — Entry point for vector_retry_queue processor (cron every 5 minutes).
Run from repo: python run_vector_retry_queue.py (cwd: backend/python)
"""
import logging
import os
import sys

_root = os.path.dirname(os.path.abspath(__file__))
if _root not in sys.path:
    sys.path.insert(0, _root)

logging.basicConfig(level=logging.INFO)

# Match api/main.py so manual runs see the same OPENAI_* / LOCAL_LLM_* as Artisan (which passes env explicitly).
try:
    from dotenv import load_dotenv

    _repo_root = os.path.dirname(os.path.dirname(_root))
    _backend = os.path.dirname(_root)
    load_dotenv(os.path.join(_repo_root, ".env"))
    load_dotenv(os.path.join(_backend, ".env"))
except ImportError:
    pass

from src.jobs.vector_retry_queue import run  # noqa: E402

if __name__ == "__main__":
    run()
