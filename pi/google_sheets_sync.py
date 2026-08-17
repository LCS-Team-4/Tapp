import json
import urllib.request
import urllib.error
from datetime import datetime

from config import (
    GOOGLE_SHEETS_SYNC_ENABLED,
    GOOGLE_SHEETS_WEBHOOK_URL
)

# Google Sheets sync for the Pi terminal.
# Sends clock-in/clock-out events to the Google Apps Script Web App URL
# configured in the TAPP admin portal. The Apps Script appends a row to
# the Google Sheet for each event.
#
# The webhook URL is read from the GOOGLE_SHEETS_WEBHOOK_URL environment
# variable (set in the Pi's .env file). Sync can be toggled on/off via
# GOOGLE_SHEETS_SYNC_ENABLED.
#
# All failures are logged but never break the clock flow.


class GoogleSheetsSync:

    def __init__(self, logger=None):
        self.logger = logger
        self.webhook_url = (GOOGLE_SHEETS_WEBHOOK_URL or "").strip()
        self.sync_enabled = str(GOOGLE_SHEETS_SYNC_ENABLED or "0").strip() in ("1", "true", "True", "TRUE", "yes")

    def log_event(self, employee_id, employee_name, event_type, source="device"):
        """Send a clock event to the Google Sheet. Returns True on success."""
        if not self.sync_enabled:
            return False

        if not self.webhook_url:
            self._log("Google Sheets sync enabled but no webhook URL configured")
            return False

        # Validate the URL looks like a Google Apps Script Web App.
        if not self.webhook_url.startswith("https://script.google.com/macros/s/") or not self.webhook_url.endswith("/exec"):
            self._log("Google Sheets sync: invalid webhook URL")
            return False

        payload = {
            "timestamp": datetime.now().astimezone().isoformat(),
            "employee_id": employee_id,
            "employee_name": employee_name,
            "event_type": event_type,
            "source": source,
        }

        data = json.dumps(payload).encode("utf-8")

        req = urllib.request.Request(
            self.webhook_url,
            data=data,
            headers={
                "Content-Type": "text/plain",
                "User-Agent": "TAPP-Sync/1.0",
            },
            method="POST",
        )

        try:
            with urllib.request.urlopen(req, timeout=10) as resp:
                status = resp.getcode()
                body = resp.read().decode("utf-8", errors="replace")
                if status >= 400:
                    self._log(f"Google Sheets sync failed: HTTP {status} - {body[:200]}")
                    return False
                self._log(f"Google Sheets sync OK: {event_type} for {employee_id}")
                return True
        except urllib.error.HTTPError as e:
            self._log(f"Google Sheets sync HTTP error: {e.code} - {e.reason}")
            return False
        except urllib.error.URLError as e:
            self._log(f"Google Sheets sync connection error: {e.reason}")
            return False
        except Exception as e:
            self._log(f"Google Sheets sync error: {e}")
            return False

    def _log(self, message):
        if self.logger:
            self.logger.info(message)
        else:
            print(message)