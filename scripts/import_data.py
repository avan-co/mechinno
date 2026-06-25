from __future__ import annotations

from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from app.database import connect, get_db_path  # noqa: E402
from app.importer import import_all  # noqa: E402


def main() -> None:
    db_path = get_db_path()
    with connect(db_path) as conn:
        import_all(conn, ROOT)
    print(f"Imported Excel data into {db_path}")


if __name__ == "__main__":
    main()
