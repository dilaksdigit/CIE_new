"""
PyMySQL connection from DATABASE_URL or DB_* environment variables.

If DATABASE_URL uses a Docker-only hostname (host, db, mysql, …) and DB_HOST is
set, DB_* vars are used instead so local runs work without unsetting DATABASE_URL.
"""

from __future__ import annotations

import os
from urllib.parse import unquote, urlparse

import pymysql

_DOCKER_DB_HOSTNAMES = frozenset(
    {"host", "db", "mysql", "mariadb", "database", "dbserver"}
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


def pymysql_connect_dict_cursor():
    """New PyMySQL connection with DictCursor (legacy _get_db contract)."""
    url = os.environ.get("DATABASE_URL", "").strip()
    if _should_use_database_url(url):
        parsed = urlparse(url)
        user = parsed.username
        pwd = parsed.password
        return pymysql.connect(
            host=parsed.hostname or os.environ.get("DB_HOST", "localhost"),
            port=parsed.port or int(os.environ.get("DB_PORT", "3306")),
            user=(unquote(user) if user else None)
            or os.environ.get("DB_USER")
            or os.environ.get("DB_USERNAME")
            or "root",
            password=(unquote(pwd) if pwd else None) or os.environ.get("DB_PASSWORD", ""),
            database=(parsed.path or "").lstrip("/") or os.environ.get("DB_DATABASE", "cie"),
            cursorclass=pymysql.cursors.DictCursor,
        )
    return pymysql.connect(
        host=os.environ.get("DB_HOST", "localhost"),
        port=int(os.environ.get("DB_PORT", "3306")),
        user=os.environ.get("DB_USER") or os.environ.get("DB_USERNAME") or "root",
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_DATABASE", "cie"),
        cursorclass=pymysql.cursors.DictCursor,
    )
