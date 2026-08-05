# Firmware Reconciliation

`pi/` was built on the `taaraa` branch, off the very first `Tapp` scaffold
commit, before it ever integrated against `backend/` — the branch predates
every backend and frontend PHP chunk that followed. Its 934 lines across 7
files (`attendance.py`, `config.py`, `database.py`, `employee.py`,
`logger.py`, `main.py`, `rfid_reader.py`) connect straight to MySQL via
`mysql.connector` and query an `employees` table keyed on `rfid_uid` — a
schema that was never actually applied; the real, migrated schema has
`rfid_id` on the single `users` table instead. `backend/`, merged in
alongside it, already implements the documented contract correctly
(`TokenController`, `ClockController::redeem`, `Hmac.php`,
`TokenService.php`, and `UserRepository.php` querying the real `rfid_id`
column) — only the firmware side had drifted. `pi/config.py`'s `DB_HOST`/
`DB_USER`/`DB_PASSWORD` were also left blank, so none of this had ever
actually been run against a live database. This pass relocates the firmware
to `firmware/src/` (matching `docs/architecture.md`'s scaffolded location and
file names) and reconfigures it to talk to `backend/` over HTTP only, per
that document's §2-3.

## Relocated

| Old path | New path | Why |
|---|---|---|
| `pi/config.py` | `firmware/src/config.py` | Same role (terminal settings), but re-shaped: no more DB connection settings — see Removed/Added below. |
| `pi/rfid_reader.py` | `firmware/src/reader.py` | Same conceptual role (read one scan), but rewritten — see Removed. The old file assumed a SPI-wired `mfrc522` module driven over `RPi.GPIO`; the actual hardware on hand (`docs/spec.md` §5) is a USB reader that behaves as an HID keyboard and needs no such wiring. |
| `pi/main.py` + `pi/attendance.py` + `pi/logger.py` | `firmware/src/scanner.py` | Merged into one file. The scaffolded layout in `docs/architecture.md` has no separate `main.py`/`attendance.py`/`logger.py` — just `scanner.py` as the entry point — so the main loop (`main.py`), the cooldown + per-scan orchestration (`attendance.py`), and the logging setup (`logger.py`) all live there now. |
| `pi/database.py` | `firmware/src/api_client.py` | **Replaced, not renamed.** The responsibility changes completely: from direct MySQL queries against a nonexistent schema, to an HTTP client that POSTs to `/api/clock/redeem` and lets the backend do every lookup. Nothing in `api_client.py` reuses `database.py`'s logic. |
| `pi/employee.py` | *(none)* | Removed outright — see Removed. |

## Removed

| What | Why |
|---|---|
| `mysql.connector` and every raw SQL query in `pi/database.py` — `get_employee_by_uid()` (`SELECT * FROM employees WHERE rfid_uid = %s`), `get_employee_status()`, `update_status()`, `get_last_scan()`, `update_last_scan()`, `save_attendance()` (`INSERT INTO attendance ...`), `card_registered()` | `docs/architecture.md` §2-3 documents `firmware/` as HTTP-only — "only `backend/app/Repositories/*` touch SQL." It's not just a style preference: on Xneelo, the database sits above the web root specifically so nothing external can reach it directly (§3, §7); a Pi terminal opening a MySQL connection over the network isn't a route this hosting model supports at all. |
| `pi/employee.py`'s `Employee` class (a local `employee_id`/name/`status`/`last_scan` model, populated by `get_employee_by_uid()`) | Firmware no longer looks up or holds employee identity locally. The backend's `/clock/redeem` response already returns `employee_name` for logging; everything else — matching `rfid_id`, checking `status` — is `backend/app/Repositories/UserRepository.php`'s job now, not the terminal's. |
| `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASSWORD` in `config.py` | No database connection exists to configure. |
| `RFID_RST_PIN` / `SPI_BUS` / `SPI_DEVICE` in `config.py` | GPIO/SPI wiring settings for the old `mfrc522` reader module, which isn't the hardware actually on hand (see Relocated → `reader.py`). |
| `TIME_FORMAT` / `DATE_FORMAT` / `DATETIME_FORMAT` in `config.py` | Dead config — defined but never imported or referenced anywhere across all 7 files of `pi/`. |

## Added

| What | Why |
|---|---|
| `firmware/src/api_client.py`'s `redeem()`, `RedeemRejected`, `RedeemUnreachable` | The HTTP client replacing direct DB access. Forwards whatever raw string the reader picked up as the `token` field of a JSON POST to `/api/clock/redeem` — no local parsing or format-checking, per `docs/spec.md` §5's "transport-agnostic" framing. Distinguishes a final backend rejection (bad/expired token — don't retry) from a transient network failure (retry-worthy), so `scanner.py` knows which is which. Sends JSON specifically because `backend/app/Http/Request.php::fromGlobals()` only parses `application/json` bodies — a form-encoded POST would arrive empty and always fail token verification. |
| `firmware/src/queue.py`'s `RetryQueue` | New — no equivalent existed in `pi/`. A short retry buffer for redeem attempts that failed with a transient network error. Deliberately bounded by the clock-in token's own ~30-second TTL (`TokenService::TTL_SECONDS`, mirrored as `TOKEN_TTL_SECONDS` in `config.py`): past that window a queued token can never redeem successfully no matter how many times it's retried, so entries are dropped once they're provably too old rather than held indefinitely like the PWAs' hourly-sync IndexedDB queue (`docs/spec.md` §11, a different problem with a much longer viable window). |
| `firmware/src/__init__.py` | Makes `firmware/src/` a proper Python package. Required specifically because the mandated filename `queue.py` shadows the stdlib `queue` module that `requests`'s `urllib3` dependency imports internally (`from queue import LifoQueue, ...`) — without this, plus running as `python -m src.scanner` from `firmware/` rather than `python scanner.py` from inside `firmware/src/`, `import requests` breaks the moment `firmware/src/` ends up on `sys.path`. Documented in `scanner.py`'s and `queue.py`'s headers. |
| `firmware/requirements.txt` (`requests`) | The new HTTP client's one dependency. `pi/` never had a requirements file at all, despite needing `mysql-connector-python` and `mfrc522`/`RPi.GPIO` — this is the first real dependency manifest firmware has had. |

## Unchanged / still open

This pass does **not** decide the physical credential mechanism — card vs.
phone-tap vs. anything else. `firmware/src/reader.py` forwards whatever
string the reader hands over regardless of which option wins, so that
decision was never actually a blocker for *this* change (fixing the
DB-vs-HTTP architecture); it only blocks filling in `reader.py`'s hardware
interaction in more detail later, exactly as `docs/spec.md` §5 already said.
Two documents cover the fuller picture and are already committed in this
repo: `docs/terminal-architecture-decision-brief.md` (the direct-DB-vs-HTTP
reconciliation this pass acts on) and `docs/clock-in-credential-decision-brief.md`
(the deeper look at the still-open credential/fraud-prevention question this
pass deliberately leaves alone). Neither is finalized; both end with an
explicit list of what the team still needs to decide.
