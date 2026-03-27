"""
Run before other imports in src.jobs.* when started via `python -m src.jobs.<name>`.

- Puts backend/python and backend/python/src on sys.path so `api`, `utils`, and
  `integrations` resolve without PYTHONPATH.
- Loads `.env` files walking up from this file. If both `backend/python/.env`
  (often Docker: `DB_HOST=host`) and the repo root `.env` exist, the **outermost**
  file wins so local `DB_HOST=127.0.0.1` is not shadowed.
"""

from __future__ import annotations

import sys
from pathlib import Path

_JOBS = Path(__file__).resolve().parent
_SRC = _JOBS.parent
_PY = _SRC.parent

for _d in (_PY, _SRC):
    _s = str(_d)
    if _s not in sys.path:
        sys.path.insert(0, _s)

try:
    from dotenv import load_dotenv
except ImportError:
    load_dotenv = None  # type: ignore[misc, assignment]

if load_dotenv:
    _here = Path(__file__).resolve()
    _env_files = []
    for _anc in _here.parents:
        _candidate = _anc / ".env"
        if _candidate.is_file():
            _env_files.append(_candidate)
    # Walk is inside-out (e.g. backend/python before repo root). Load inner first
    # without overriding, then outermost with override=True so root .env wins.
    for _env in _env_files[:-1]:
        load_dotenv(_env, override=False)
    if _env_files:
        load_dotenv(_env_files[-1], override=True)
