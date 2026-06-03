"""Database driver error helpers (PostgreSQL primary; includes legacy compatibility checks)."""

from __future__ import annotations


def is_unknown_column(exc: BaseException) -> bool:
    """Column does not exist — retry alternate SELECT (schema drift)."""
    pgcode = getattr(exc, "pgcode", None)
    if pgcode == "42703":
        return True
    args = getattr(exc, "args", ())
    if args and args[0] == 1054:
        return True
    return "unknown column" in str(exc).lower()


def is_missing_table(exc: BaseException) -> bool:
    """Table does not exist — fail-soft skip."""
    pgcode = getattr(exc, "pgcode", None)
    if pgcode == "42P01":
        return True
    args = getattr(exc, "args", ())
    if args and args[0] == 1146:
        return True
    return "does not exist" in str(exc).lower() and "relation" in str(exc).lower()
