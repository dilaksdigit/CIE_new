"""
Backward-compatible import path.

Deprecated name retained to avoid breaking legacy imports; all runtime
connections are PostgreSQL via db_connect.
"""

from src.utils.db_connect import connect_dict_cursor, pymysql_connect_dict_cursor

__all__ = ["connect_dict_cursor", "pymysql_connect_dict_cursor"]
