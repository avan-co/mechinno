from __future__ import annotations

from pathlib import Path
from typing import Any

from fastapi import FastAPI, HTTPException
from fastapi.responses import HTMLResponse, StreamingResponse
from fastapi.staticfiles import StaticFiles

from .database import BASE_DIR, connect, get_db_path, init_db, rows_to_dicts
from .exporter import REPORTS, build_workbook
from .importer import import_all


app = FastAPI(title="Mechinno Management Panel", version="0.1.0")
app.mount("/static", StaticFiles(directory=BASE_DIR / "app" / "static"), name="static")


def ensure_database() -> None:
    db_path = get_db_path()
    with connect(db_path) as conn:
        init_db(conn)
        count = conn.execute("SELECT COUNT(*) FROM import_runs").fetchone()[0]
        if count == 0:
            import_all(conn, BASE_DIR)


@app.on_event("startup")
def startup() -> None:
    ensure_database()


def query_rows(sql: str, params: tuple[Any, ...] = ()) -> list[dict[str, Any]]:
    with connect() as conn:
        return rows_to_dicts(conn.execute(sql, params).fetchall())


@app.get("/", response_class=HTMLResponse)
def index() -> str:
    return (BASE_DIR / "app" / "templates" / "index.html").read_text(encoding="utf-8")


@app.get("/api/summary")
def summary() -> dict[str, Any]:
    with connect() as conn:
        one = lambda sql: conn.execute(sql).fetchone()[0] or 0
        locker_status = rows_to_dicts(
            conn.execute(
                """
                SELECT status, COUNT(*) AS count
                FROM lockers
                GROUP BY status
                ORDER BY count DESC
                """
            ).fetchall()
        )
        plan_status = rows_to_dicts(
            conn.execute(
                """
                SELECT status, COUNT(*) AS count
                FROM plans
                GROUP BY status
                ORDER BY count DESC
                """
            ).fetchall()
        )
        monthly_charges = rows_to_dicts(
            conn.execute(
                """
                SELECT fiscal_year, month_index, month_name, SUM(amount) AS amount
                FROM charges
                GROUP BY fiscal_year, month_index, month_name
                ORDER BY fiscal_year, month_index
                """
            ).fetchall()
        )
        finance_by_category = rows_to_dicts(
            conn.execute(
                """
                SELECT category, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount
                FROM transactions
                GROUP BY category
                ORDER BY amount DESC
                """
            ).fetchall()
        )
        latest_import = conn.execute(
            "SELECT imported_at, source_files FROM import_runs ORDER BY id DESC LIMIT 1"
        ).fetchone()
        return {
            "cards": {
                "members": one("SELECT COUNT(*) FROM members"),
                "teams": one("SELECT COUNT(*) FROM teams"),
                "lockers": one("SELECT COUNT(*) FROM lockers"),
                "available_lockers": one("SELECT COUNT(*) FROM lockers WHERE status = 'خالی'"),
                "reserved_lockers": one("SELECT COUNT(*) FROM lockers WHERE status = 'رزرو'"),
                "charge_total": one("SELECT COALESCE(SUM(amount), 0) FROM charges"),
                "income_total": one("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount > 0"),
                "expense_total": one("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount < 0"),
                "warnings": one("SELECT COUNT(*) FROM import_warnings"),
            },
            "locker_status": locker_status,
            "plan_status": plan_status,
            "monthly_charges": monthly_charges,
            "finance_by_category": finance_by_category,
            "latest_import": dict(latest_import) if latest_import else None,
        }


@app.get("/api/teams")
def teams() -> list[dict[str, Any]]:
    return query_rows("SELECT * FROM teams ORDER BY id")


@app.get("/api/members")
def members() -> list[dict[str, Any]]:
    return query_rows("SELECT * FROM members ORDER BY id")


@app.get("/api/lockers")
def lockers() -> list[dict[str, Any]]:
    return query_rows("SELECT * FROM lockers ORDER BY locker_number")


@app.get("/api/plans")
def plans() -> list[dict[str, Any]]:
    return query_rows("SELECT * FROM plans ORDER BY plan_number")


@app.get("/api/charges")
def charges() -> list[dict[str, Any]]:
    return query_rows(
        """
        SELECT fiscal_year, team_name, leader, desk_count, month_name,
               amount, note, charge_rate, rent_rate
        FROM charges
        ORDER BY fiscal_year, team_name, month_index
        """
    )


@app.get("/api/transactions")
def transactions() -> list[dict[str, Any]]:
    return query_rows(
        """
        SELECT t.*, b.sheet_name, b.petty_cash_holder
        FROM transactions t
        JOIN financial_batches b ON b.id = t.batch_id
        ORDER BY b.id, t.id
        """
    )


@app.get("/api/warnings")
def warnings() -> list[dict[str, Any]]:
    return query_rows("SELECT * FROM import_warnings ORDER BY id")


@app.post("/api/reimport")
def reimport() -> dict[str, Any]:
    with connect() as conn:
        import_all(conn, BASE_DIR)
    return {"ok": True}


@app.get("/exports/{report_key}.xlsx")
def export_report(report_key: str) -> StreamingResponse:
    if report_key != "all" and report_key not in REPORTS:
        raise HTTPException(status_code=404, detail="Report not found")
    with connect() as conn:
        output = build_workbook(conn, report_key)
    filename = "mechinno-management-report.xlsx" if report_key == "all" else f"mechinno-{report_key}.xlsx"
    return StreamingResponse(
        output,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}
