#!/usr/bin/env python3
"""
Run the unified CIE Python API (FastAPI). All endpoints on one main port.
Usage: from backend/python:  python run_validate_api.py
       or:  uvicorn api.main:app --host 0.0.0.0 --port 8000
"""
import os
import sys

# Run from backend/python so that 'api' package is found
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("PORT", 8000))
    uvicorn.run(
        "api.main:app",
        host="127.0.0.1",
        port=port,
        reload=os.environ.get("DEBUG", "").lower() in ("1", "true"),
    )
