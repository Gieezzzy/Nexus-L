from __future__ import annotations

import json
from contextlib import contextmanager
from typing import Any, Iterator

import psycopg
from psycopg.rows import dict_row
from psycopg_pool import ConnectionPool

from app.config import ROOT, settings

_pool: ConnectionPool | None = None


def pool() -> ConnectionPool:
    global _pool
    if _pool is None:
        _pool = ConnectionPool(settings.pg_dsn, min_size=1, max_size=8, kwargs={"row_factory": dict_row, "autocommit": True}, open=True)
    return _pool


@contextmanager
def conn() -> Iterator[psycopg.Connection]:
    with pool().connection() as c:
        yield c


def migrate() -> None:
    """Applies sql/schema.sql (idempotent) and makes sure there is a partition for the current and next month."""
    sql = (ROOT / "sql" / "schema.sql").read_text(encoding="utf-8")
    with conn() as c:
        c.execute(sql)
        c.execute("INSERT INTO settings_versions (threshold, note, active) SELECT %s, 'initial default', true WHERE NOT EXISTS (SELECT 1 FROM settings_versions)", (settings.threshold,))


def query(sql: str, params: tuple[Any, ...] | list[Any] = ()) -> list[dict[str, Any]]:
    with conn() as c:
        return list(c.execute(sql, params).fetchall())


def one(sql: str, params: tuple[Any, ...] | list[Any] = ()) -> dict[str, Any] | None:
    rows = query(sql, params)
    return rows[0] if rows else None


def run(sql: str, params: tuple[Any, ...] | list[Any] = ()) -> None:
    with conn() as c:
        c.execute(sql, params)


def j(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, default=str)


def active_threshold() -> float:
    row = one("SELECT threshold FROM settings_versions WHERE active")
    return float(row["threshold"]) if row else settings.threshold
