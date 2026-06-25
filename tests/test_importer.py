from __future__ import annotations

import sqlite3
from pathlib import Path

from app.importer import import_all


ROOT = Path(__file__).resolve().parent.parent


def test_import_all_loads_core_entities() -> None:
    conn = sqlite3.connect(":memory:")
    import_all(conn, ROOT)

    assert conn.execute("SELECT COUNT(*) FROM members").fetchone()[0] == 88
    assert conn.execute("SELECT COUNT(*) FROM teams").fetchone()[0] == 11
    assert conn.execute("SELECT COUNT(*) FROM lockers").fetchone()[0] == 36
    assert conn.execute("SELECT COUNT(*) FROM plans").fetchone()[0] == 16
    assert conn.execute("SELECT COUNT(*) FROM charges").fetchone()[0] > 170
    assert conn.execute("SELECT COUNT(*) FROM transactions").fetchone()[0] == 25


def test_import_records_data_quality_warnings() -> None:
    conn = sqlite3.connect(":memory:")
    import_all(conn, ROOT)

    warnings = conn.execute("SELECT message FROM import_warnings").fetchall()
    messages = [row[0] for row in warnings]
    assert "تاریخ تحویل کمد معتبر به نظر نمی‌رسد." in messages
    assert "یک مبلغ احتمالی در ستون توضیحات پیدا شد." in messages
