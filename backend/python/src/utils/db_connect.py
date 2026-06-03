"""
PostgreSQL connection from DATABASE_URL or DB_* environment variables.

Replaces PyMySQL for CIE v2.3.2. Uses psycopg2 RealDictCursor (dict rows like PyMySQL DictCursor).
"""

from __future__ import annotations

import os
from urllib.parse import unquote, urlparse

import psycopg2
import psycopg2.extras

_DOCKER_DB_HOSTNAMES = frozenset(
    {"host", "db", "postgres", "postgresql", "database", "dbserver"}
)


def _should_use_database_url(url: str) -> bool:
    url = url.strip()
    if not url:
        return False
    parsed = urlparse(url)
    host = (parsed.hostname or "").lower()
    if host in _DOCKER_DB_HOSTNAMES and os.environ.get("DB_HOST", "").strip():
        return False
    return True


def _connect_params_from_url(url: str) -> dict:
    parsed = urlparse(url)
    user = parsed.username
    pwd = parsed.password
    return {
        "host": parsed.hostname or os.environ.get("DB_HOST", "localhost"),
        "port": parsed.port or int(os.environ.get("DB_PORT", "5432")),
        "user": (unquote(user) if user else None)
        or os.environ.get("DB_USER")
        or os.environ.get("DB_USERNAME")
        or "postgres",
        "password": (unquote(pwd) if pwd else None) or os.environ.get("DB_PASSWORD", ""),
        "dbname": (parsed.path or "").lstrip("/") or os.environ.get("DB_DATABASE", "cie_v232"),
    }


def connect_dict_cursor():
    """PostgreSQL connection with dict rows."""
    url = os.environ.get("DATABASE_URL", "").strip()
    if _should_use_database_url(url):
        params = _connect_params_from_url(url)
    else:
        params = {
            "host": os.environ.get("DB_HOST", "localhost"),
            "port": int(os.environ.get("DB_PORT", "5432")),
            "user": os.environ.get("DB_USER") or os.environ.get("DB_USERNAME") or "postgres",
            "password": os.environ.get("DB_PASSWORD", ""),
            "dbname": os.environ.get("DB_DATABASE", "cie_v232"),
        }
    conn = psycopg2.connect(**params)
    # SOURCE: CLAUDE.md §9 — UTC session; must run outside an open transaction (psycopg2 set_session).
    prev_autocommit = conn.autocommit
    conn.autocommit = True
    try:
        with conn.cursor() as cur:
            cur.execute("SET TIME ZONE 'UTC'")
    finally:
        conn.autocommit = prev_autocommit
    return conn


def cursor_dict(conn):
    """Dict cursor for a connection."""
    return conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)


# Backward-compatible alias used by existing call sites.
pymysql_connect_dict_cursor = connect_dict_cursor
