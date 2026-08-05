# Terminal Architecture Reconciliation — Decision Brief

**Why this exists:** the main build (`Attendance-tracking-system`) and this PHP
migration (`Tapp`) were both built against a documented token contract between
phone, backend, and terminal. The `taaraa` branch on `Tapp` — which holds the
only real Raspberry Pi firmware written so far — was built in isolation from
that contract, against its own database assumptions. This document lays out
what was actually decided already, what was genuinely left open, what
`taaraa` implements instead, and the specific decisions that now need an
explicit answer before any of it gets merged.

This is a decision brief, not an ADR — nothing here is resolved yet. Once a
call is made, it's a natural candidate to become ADR-0004, alongside the
existing three.

---

## Part 1 — What's already settled (not up for debate)

`docs/spec.md` and `docs/architecture.md` are unambiguous about the contract
itself, independent of how the token physically reaches the terminal:

> Backend issues a **signed, single-use token, valid for 30 seconds**, tied
> to the employee's authenticated session. That token is redeemed at a
> terminal (the Raspberry Pi at the door), which forwards it to the backend.
> Backend validates the token's signature, expiry, and single-use status,
> then records the attendance event and immediately invalidates the token.
> — `spec.md` §4

`architecture.md` describes the terminal's role in the request lifecycle in
one line: **"The Pi's Python scanner does `POST /api/clock/redeem` with the
token in the body."** Everything downstream of that — routing, middleware,
business rules, the actual database write — happens inside `backend/`. The
document calls the boundary between the three programs (PHP, JS, Python) *"the
most important thing in the whole project,"* specifically because a single
repo lets the shared token contract change in one atomic commit across all
three languages, "so the three can never silently drift out of sync."

The repo also already documents where Pi code is supposed to live and what it
touches:

| Deploy target | Runs where | Touches the database? |
|---|---|---|
| `backend/` | Xneelo | Yes — only `backend/app/Repositories/*` touch SQL |
| `frontend/` | Xneelo | No — talks to `backend/` over HTTP |
| `firmware/` | The Raspberry Pi | **No** — talks to `backend/` over HTTP, same as `frontend/` |

None of this is in question. What's in question is only how the token gets
from the phone to the terminal.

---

## Part 2 — What was actually left open

`spec.md` §5 is explicit that this, and only this, was unresolved:

> **OPEN ITEM — physical token transport at the terminal.** Original design
> assumed a camera-based QR scan... What's actually on hand: a Raspberry Pi
> 4, a USB RFID reader that behaves as an HID keyboard, and an NFC
> sticker/tag. Two directions were explored... **(a) Tap-to-trigger:** the
> phone taps the NFC tag, which deep-links into the PWA; the phone completes
> the token flow over WiFi. **(b) Tap-to-write:** the phone writes the signed
> token to the tag; the RFID reader picks it up like a card UID and the Pi
> forwards it to the backend.

Both candidates keep the phone as the credential and the backend as the sole
validator — they only differ in *how the token physically travels*. The same
section also floats RFID cards as a **separate, explicitly V2 idea**: "register
a card's factory UID against an employee profile... useful for staff without
smartphones" — but frames it as an addition to the token contract, not a
replacement for backend validation.

`main`'s `firmware/` folder reflects exactly this undecided state: `config.py`,
`api_client.py`, `queue.py`, `reader.py`, `scanner.py` all exist as 0-byte
files, named for the HTTP-client role the docs describe, waiting on this
decision before anyone writes into them.

---

## Part 3 — What `taaraa`'s firmware actually implements

`taaraa` branches off the very first `Tapp` scaffold commit and has never been
touched since — it predates every frontend PHP chunk. Its `pi/` folder (934
lines across 7 files) implements something that answers *both* open questions
at once, in a direction the docs never proposed:

- **`pi/database.py`** imports `mysql.connector` and connects **directly** to
  a MySQL/MariaDB instance from the Pi itself, with `DB_HOST`/`DB_USER`/
  `DB_PASSWORD` read from `pi/config.py` — all currently blank, meaning this
  has never actually been run against a live database.
- **`get_employee_by_uid()`** runs `SELECT * FROM employees WHERE rfid_uid =
  %s` directly against the database — no token, no HTTP request, no backend
  involvement of any kind.
- **`save_attendance()` / `update_status()`** run raw `INSERT`/`UPDATE`
  statements directly from Python.
- A repo-wide search for `token`, `hmac`, or `signature` inside `pi/` returns
  **zero matches**.
- The schema it assumes — an `employees` table with an `rfid_uid` column —
  doesn't exist anywhere in the applied migrations. The real schema has
  `rfid_id` (nullable, `UNIQUE`) on the single `users` table, added
  specifically to leave room for an RFID-based credential later.

Notably, **`taaraa`'s `backend/` doesn't share this problem** — it has a real
`TokenController`, `ClockController`, `Hmac.php`, and `TokenService.php`, and
`routes/api.php` registers `POST /clock/redeem` exactly as documented. The
divergence is isolated to the Pi code specifically — it was written without
integrating against the backend API that, on the very same branch, already
implements the documented contract correctly.

One more small thing worth noting: the docs call this folder `firmware/`;
`taaraa` calls it `pi/`. Minor on its own, but one more sign the two were
never reconciled.

---

## Part 4 — The decisions that need to be made

### Decision 1: Does the terminal talk to the backend API, or to the database directly?

| | **Backend-mediated (documented)** | **Direct database access (`taaraa`)** |
|---|---|---|
| What it does | Pi POSTs the token to `/api/clock/redeem`; `AttendanceService` validates and writes | Pi runs SQL directly against MariaDB over the network |
| Stated/evident benefit | One place for business logic (grace-period status, single-use enforcement) — can't drift between PHP and Python. Terminal never holds DB credentials, so a stolen device exposes only a scoped, short-lived token capability, not full read/write access. Fits Xneelo's shared-hosting model — the Pi makes an ordinary outbound HTTPS call to a domain that's already public; no inbound database exposure to configure or secure. | *(Inferred — not stated anywhere in `taaraa`)* Faster to build standalone while the backend API was still being scaffolded — plausible given this branch dates to the very first scaffold commit. Fewer moving parts: no token-issuance flow to build on the phone side, no HTTP client to write in Python. A familiar, well-trodden pattern for a small RFID-attendance project. |
| Cost / risk | Requires the token-transport question (Decision 2) to be resolved before `firmware/` can be finished. | Needs the Pi to reach MariaDB directly over the network — on Xneelo's shared hosting that likely means exposing the database to inbound connections from a device sitting on a school's local network, a materially different and riskier configuration than anything else in this stack. Duplicates business logic (status/grace-period rules) in Python, separate from `AttendanceService.php` and `cron/mark_absences.php` — the exact drift the single-repo/shared-contract decision was meant to prevent. Never actually configured or run (`config.py`'s DB fields are blank), so whatever benefits it has are theoretical, not demonstrated. |

### Decision 2: What is the physical credential — the employee's phone, or a registered RFID card?

This is genuinely the question `spec.md` §5 left open, and it's separable
from Decision 1.

| | **Phone + NFC tag (the two documented candidates)** | **Registered RFID card (`taaraa`'s implicit choice)** |
|---|---|---|
| What it does | Employee's own phone either triggers the PWA via an NFC deep-link (a) or writes the signed token to the tag for the reader to pick up (b) | Employee taps a physical card pre-registered against their profile; no phone involved |
| Benefit | Keeps "the employee's authenticated session on their phone" as the credential for the whole V1 flow, consistent with the rest of the system (leave, history, profile all already assume phone access) | No smartphone dependency — `spec.md` itself notes this matters for staff without one. Removes the NFC-tag-write complexity neither (a) nor (b) had fully resolved. Likely faster tap-and-go at a single door during a rush. |
| Cost / risk | Both candidate implementations were still unresolved as of this writing — neither was working code | `spec.md` filed this as a **V2 addition on top of** the token contract, not a replacement for it. As implemented in `taaraa`, it arrives bundled with Decision 1's direct-DB approach — but it doesn't have to be: a Pi that reads an RFID UID and POSTs *that* to a new backend endpoint (which looks up the employee, applies the same `AttendanceService` rules, and writes the record) gets the card-based UX without giving up backend mediation. |

### Decision 3: Schema — `users.rfid_id` (current) vs. a separate `employees` table with `rfid_uid` (`taaraa`)

Mostly a downstream consequence of Decisions 1–2, not an independent
motivation — but it needs its own explicit close-out before any of `taaraa`'s
code merges anywhere. Worth noting directly: the *card-based credential idea*
already has a home in the current schema. `users.rfid_id` was added
specifically anticipating this. What doesn't fit is specifically `taaraa`'s
separate `employees` table and its `rfid_uid` naming — an independently
invented shape that was never reconciled against the applied migrations,
`schema-notes.md`, or the `users`-is-the-single-table principle already
locked in for both the frontend and backend work. If Decision 1 goes to
backend-mediated, this one mostly resolves itself — the Pi never touches the
schema directly either way, and a `TerminalRepository`-style lookup can use
the existing `users.rfid_id` column as-is.

---

## What resolving this unblocks

Per `spec.md` §5, this was always going to gate `firmware/scanner.py` and
`firmware/reader.py` — that's not new. What's new is that there's now a real,
934-line reference implementation of one possible direction, built without
ever seeing the backend it should have integrated with. That makes it useful
evidence for this decision, not a finished answer to it.
