# AGENTS.md

## Cursor Cloud specific instructions

### What this is
A plain PHP 8 / cPanel management panel (Persian, RTL) for a mechanical-engineering innovation
center. No Composer, no framework, no build step, no automated test suite. PHP files are served
directly; `src/*.php` holds the classes, `assets/` holds the CSS/JS, and three `*.xlsx` files in the
repo root are the data source imported into the database.

### Local dev database (important)
- Production targets MySQL, but the code has a built-in **SQLite fallback** (`src/Database.php`,
  `src/Schema.php`). For local/cloud dev there is no need to install MySQL — use SQLite.
- `config.php` is **gitignored** and must exist for the app to run. The update script creates it
  automatically from `config.sample.php` if missing, pointing the `db` driver at
  `sqlite` with path `data/mechinno.sqlite3` and setting a dev admin login (`admin` / `admin1234`).
  If you change credentials, edit `config.php` directly (it is never committed).
- The SQLite file lives at `data/mechinno.sqlite3` (the `data/` dir is auto-created). Delete it to
  reset the database, then re-run the import.

### Running the app
- Start the dev server from the repo root: `php -S 127.0.0.1:8080`
- Entry points: `index.php` (dashboard), `login.php`, `install.php` (DB build + Excel import),
  `api.php?resource=summary` (JSON API), `export.php`, `report.php`.
- All pages except `login.php` require auth and redirect (302) to `login.php` until you log in.

### Bootstrapping data (first run)
The database is empty until the Excel files are imported. Either:
- Browser: log in, open `install.php`, tick the confirm checkbox, submit; **or**
- `api.php` auto-imports on first hit if `import_runs` is empty.
The scripted curl flow (login -> get CSRF -> POST `install.php` with `confirm_import=1`) also works
for headless setup. Expected import counts: ~88 members, 11 teams, 36 lockers, 177 charges.

### Lint / test / build
- No linter config and no test suite exist. The available syntax check is `php -l` on each PHP file,
  e.g. `for f in *.php src/*.php; do php -l "$f"; done`.
- There is no build step (no JS bundler/transpiler); `assets/app.js` and `styles.css` are served as-is.

### Gotchas
- Manually added/edited records are stored with `source_file = manual` and survive a re-import;
  Excel-sourced rows are rebuilt on every import.
- CSRF tokens are required for all POST/mutation requests (login, install, and `api.php` create/
  update/delete/status). Pull the token from the rendered form or session before POSTing.
