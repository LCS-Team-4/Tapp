# api_client.py — the terminal's one job: forward whatever string the
# reader picked up to POST /api/clock/redeem, and interpret the response.
#
# Replaces pi/database.py's direct database-connector access entirely. Per
# docs/architecture.md §2-3, firmware/ talks to backend/ over HTTP only and
# never touches SQL — "only backend/app/Repositories/ may touch SQL" is the
# rule, not a suggestion. This file does not parse, decode, validate, or
# look up anything about the token it's sending — per docs/spec.md §5,
# that's entirely the backend's job
# (backend/app/Services/TokenService.php::verify(),
# backend/app/Repositories/UserRepository.php::findById()).
import requests

from .config import BACKEND_BASE_URL, DEVICE_NAME, REQUEST_TIMEOUT_SECONDS

REDEEM_PATH = "/api/clock/redeem"


class RedeemRejected(Exception):
    """The backend received the request and rejected it outright — bad,
    expired, or malformed token, inactive user, etc. (a 4xx JSON error body
    from ClockController::redeem, see backend/app/Http/Response.php's
    error() shape). Retrying the exact same token can never succeed; the
    caller should log this and move on, not queue it."""

    def __init__(self, message, status_code):
        super().__init__(message)
        self.status_code = status_code


class RedeemUnreachable(Exception):
    """The request never got a response back at all — network error,
    timeout, DNS failure, backend down. Transient, unlike RedeemRejected:
    safe to retry while the token is still within its TTL (see queue.py)."""


def redeem(token):
    """
    POSTs {token, device_name} as JSON to /api/clock/redeem — this MUST be
    JSON, not form-encoded: backend/app/Http/Request.php::fromGlobals() only
    populates the request body when Content-Type is application/json, so a
    form-encoded POST would arrive with an empty body and always fail
    token verification.

    Returns the 'data' payload on success — {action, employee_name, time},
    see ClockController::redeem. Raises RedeemRejected or RedeemUnreachable
    otherwise; never returns a falsy/error value for the caller to check.
    """
    url = BACKEND_BASE_URL.rstrip("/") + REDEEM_PATH

    try:
        response = requests.post(
            url,
            json={"token": token, "device_name": DEVICE_NAME},
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
    except requests.RequestException as error:
        raise RedeemUnreachable(str(error)) from error

    try:
        body = response.json()
    except ValueError:
        body = {}

    if response.status_code >= 400:
        message = body.get("error", {}).get("message", response.text)
        raise RedeemRejected(message, response.status_code)

    return body.get("data", {})
