from __future__ import annotations

import os
import sqlite3
from pathlib import Path
from typing import Any, Iterable


BASE_DIR = Path(__file__).resolve().parent.parent
DEFAULT_DB_PATH = BASE_DIR / "data" / "mechinno.sqlite3"


def get_db_path() -> Path:
    return Path(os.environ.get("MECHINNO_DB_PATH", DEFAULT_DB_PATH))


def connect(db_path: str | Path | None = None) -> sqlite3.Connection:
    path = Path(db_path) if db_path else get_db_path()
    path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(path)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


def init_db(conn: sqlite3.Connection) -> None:
    conn.executescript(
        """
        CREATE TABLE IF NOT EXISTS import_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            source_files TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            row_number INTEGER,
            name TEXT,
            leader TEXT,
            phone TEXT,
            desk_count INTEGER,
            lockers TEXT,
            power_strips TEXT,
            joined_at TEXT,
            warning TEXT,
            notes TEXT,
            source_file TEXT,
            source_sheet TEXT
        );

        CREATE TABLE IF NOT EXISTS members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            row_number INTEGER,
            code TEXT,
            full_name TEXT NOT NULL,
            team_name TEXT,
            desks TEXT,
            lockers TEXT,
            power_strips TEXT,
            phone TEXT,
            national_id TEXT,
            notes TEXT,
            source_file TEXT,
            source_sheet TEXT
        );

        CREATE TABLE IF NOT EXISTS lockers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            locker_number INTEGER,
            status TEXT,
            assigned_to TEXT,
            delivered_at TEXT,
            key_number TEXT,
            spare_key TEXT,
            notes TEXT,
            source_file TEXT,
            source_sheet TEXT
        );

        CREATE TABLE IF NOT EXISTS plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plan_number INTEGER,
            status TEXT,
            title TEXT,
            proposed_budget INTEGER,
            cost_type TEXT,
            schedule TEXT,
            notes TEXT,
            source_file TEXT,
            source_sheet TEXT
        );

        CREATE TABLE IF NOT EXISTS charges (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fiscal_year TEXT,
            team_name TEXT,
            leader TEXT,
            desk_count INTEGER,
            month_index INTEGER,
            month_name TEXT,
            amount INTEGER,
            note TEXT,
            charge_rate INTEGER,
            rent_rate INTEGER,
            source_file TEXT,
            source_sheet TEXT
        );

        CREATE TABLE IF NOT EXISTS financial_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sheet_name TEXT,
            petty_cash_holder TEXT,
            petty_cash_number TEXT,
            previous_balance INTEGER,
            new_deposit INTEGER,
            total_balance INTEGER,
            received_at TEXT,
            from_date TEXT,
            to_date TEXT,
            source_file TEXT
        );

        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_id INTEGER NOT NULL REFERENCES financial_batches(id) ON DELETE CASCADE,
            row_number INTEGER,
            invoice_count TEXT,
            tx_date TEXT,
            description TEXT,
            amount INTEGER,
            notes TEXT,
            category TEXT,
            suspected_amount_note INTEGER
        );

        CREATE TABLE IF NOT EXISTS import_warnings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_name TEXT,
            sheet_name TEXT,
            row_number INTEGER,
            message TEXT NOT NULL,
            payload TEXT
        );
        """
    )
    conn.commit()


def reset_imported_data(conn: sqlite3.Connection) -> None:
    for table in (
        "transactions",
        "financial_batches",
        "charges",
        "plans",
        "lockers",
        "members",
        "teams",
        "import_warnings",
        "import_runs",
    ):
        conn.execute(f"DELETE FROM {table}")
    conn.commit()


def insert_many(conn: sqlite3.Connection, table: str, rows: Iterable[dict[str, Any]]) -> None:
    rows = list(rows)
    if not rows:
        return
    columns = list(rows[0].keys())
    placeholders = ", ".join("?" for _ in columns)
    col_sql = ", ".join(columns)
    values = [[row.get(column) for column in columns] for row in rows]
    conn.executemany(f"INSERT INTO {table} ({col_sql}) VALUES ({placeholders})", values)


def rows_to_dicts(rows: Iterable[sqlite3.Row]) -> list[dict[str, Any]]:
    return [dict(row) for row in rows]
