# config.py — terminal settings.
#
# Replaces the old pi/config.py's DB_HOST/DB_PORT/DB_NAME/DB_USER/
# DB_PASSWORD entirely: the terminal never talks to a database (see
# docs/architecture.md §2-3 — firmware/ is HTTP-only, database/ is applied
# to MariaDB and read only through backend/app/Repositories/). What used to
# be direct database connection settings is now just the backend's base URL.

# COMPANY SETTINGS
COMPANY_NAME = "TAPP"

# BACKEND SETTINGS
# The one thing this terminal ever talks to. Set this to wherever
# backend/public/ is actually deployed (see backend/.env.example's
# APP_BASE_URL for the matching server-side value) — api_client.py appends
# the /api/clock/redeem path itself.
BACKEND_BASE_URL = "http://localhost:8080"

# Sent as ClockController::redeem's optional device_name field. Accepted but
# not yet persisted server-side (no TerminalRepository write in this pass —
# see backend/app/Http/Controllers/ClockController.php's comment); still
# worth sending now so terminal logs/HTTP logs are identifiable per-device
# once that lands.
DEVICE_NAME = "front-door-pi"

REQUEST_TIMEOUT_SECONDS = 5

# Mirrors TokenService::TTL_SECONDS in
# backend/app/Services/TokenService.php — a queued redeem attempt older
# than this can never succeed no matter how many times it's retried, so
# queue.py uses this to know when to give up on an entry rather than hold it
# forever. Keep in sync with the backend value; there's no way to fetch it
# at runtime since the terminal has no other backend endpoint to ask.
TOKEN_TTL_SECONDS = 30

# RFID SETTINGS
# (Old SPI/GPIO pin settings — RFID_RST_PIN/SPI_BUS/SPI_DEVICE — are gone
# too: they were for a different, SPI-wired RFID module than what's
# actually on hand. See docs/firmware-reconciliation.md.)

# ATTENDANCE SETTINGS
# Prevent accidental double scans
COOLDOWN_MINUTES = 15

# LOGGING
LOG_FILE = "logs/attendance.log"
LOG_LEVEL = "INFO"
